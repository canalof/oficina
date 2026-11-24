#!/usr/bin/env bash
# Troca de servidor, passo 2 (no servidor NOVO, Ubuntu 24.04 limpo, como root):
#   sudo bash importar.sh /root/torque-migracao-AAAA-MM-DD-HHMM.tar.gz [dominio]
# Padrão do domínio: torque.softmaxbr.com.br
#
# Instala Nginx, PHP 8.3 e MariaDB; restaura código, .env (mesma APP_KEY), storage e banco;
# configura o site e o agendador; prepara o deploy automático (usuário deploy + segredos do GitHub).
# O HTTPS (certbot) só funciona depois que o DNS apontar para cá: o script mostra o comando.
set -euo pipefail

ARQ="${1:-}"
DOMINIO="${2:-torque.softmaxbr.com.br}"
APP=/var/www/torque
PHPV=8.3
AQUI="$(cd "$(dirname "$0")" && pwd)"

[ "$(id -u)" = 0 ] || { echo "Rode como root (sudo)."; exit 1; }
[ -f "$ARQ" ] || { echo "Uso: sudo bash importar.sh /root/torque-migracao-....tar.gz [dominio]"; exit 1; }
[ ! -e "$APP" ] || { echo "$APP já existe. Para refazer a importação: mv $APP $APP.antigo e rode de novo."; exit 1; }
. /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || echo "Aviso: testado no Ubuntu 24.04 (este é ${PRETTY_NAME:-desconhecido})."

echo "== 1/8 Pacotes"
export DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a
apt-get update -qq
apt-get install -y -qq nginx mariadb-server mariadb-client rsync acl unzip cron certbot python3-certbot-nginx \
  php$PHPV-fpm php$PHPV-cli php$PHPV-mysql php$PHPV-mbstring php$PHPV-xml php$PHPV-curl php$PHPV-zip \
  php$PHPV-bcmath php$PHPV-intl php$PHPV-gd php$PHPV-sqlite3 >/dev/null
timedatectl set-timezone America/Sao_Paulo || true

echo "== 2/8 Arquivos"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
tar -xzf "$ARQ" -C "$TMP"
[ -f "$TMP/torque/artisan" ] && [ -f "$TMP/torque/.env" ] && [ -f "$TMP/banco.sql.gz" ] || { echo "Pacote incompleto (precisa de torque/, torque/.env e banco.sql.gz)."; exit 1; }
mkdir -p /var/www
mv "$TMP/torque" "$APP"
mkdir -p "$APP"/storage/{logs,app/backups,app/private,framework/{cache/data,sessions,views}} "$APP/bootstrap/cache"

env_get() { grep -E "^$1=" "$APP/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
env_set() { if grep -qE "^$1=" "$APP/.env"; then sed -i "s|^$1=.*|$1=$2|" "$APP/.env"; else echo "$1=$2" >> "$APP/.env"; fi; }
DB=$(env_get DB_DATABASE); DBU=$(env_get DB_USERNAME); DBP=$(env_get DB_PASSWORD); DBH=$(env_get DB_HOST)
[ -n "$DB" ] && [ -n "$DBU" ] || { echo "DB_DATABASE/DB_USERNAME vazios no .env."; exit 1; }
case "$DBH" in
  ""|127.0.0.1|localhost) env_set DB_HOST 127.0.0.1 ;;
  *) echo "O .env aponta para um banco em outro servidor ($DBH). Ajuste o DB_HOST no .env antes; este script usa o banco local."; exit 1 ;;
esac
[ -n "$(env_get APP_KEY)" ] || { echo "APP_KEY vazia: sem ela os tokens fiscais e as 2 etapas não abrem. Use o .env do servidor antigo."; exit 1; }

echo "== 3/8 Banco ($DB)"
systemctl enable --now mariadb >/dev/null
SQLP=$(printf "%s" "$DBP" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DBU'@'localhost' IDENTIFIED BY '$SQLP';
CREATE USER IF NOT EXISTS '$DBU'@'127.0.0.1' IDENTIFIED BY '$SQLP';
ALTER USER '$DBU'@'localhost' IDENTIFIED BY '$SQLP';
ALTER USER '$DBU'@'127.0.0.1' IDENTIFIED BY '$SQLP';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBU'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DBU'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
TABELAS=$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB'")
[ "$TABELAS" = 0 ] || { echo "O banco $DB já tem tabelas. Não vou sobrescrever."; exit 1; }
gunzip -c "$TMP/banco.sql.gz" | mariadb "$DB"
echo "   $(mariadb -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB'") tabelas restauradas"

echo "== 4/8 PHP"
INI=/etc/php/$PHPV/fpm/conf.d/90-torque.ini
cat > "$INI" <<'EOF'
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_input_vars = 5000
date.timezone = America/Sao_Paulo
EOF
cp "$INI" /etc/php/$PHPV/cli/conf.d/90-torque.ini
systemctl enable --now php$PHPV-fpm >/dev/null
systemctl reload php$PHPV-fpm

echo "== 5/8 Nginx ($DOMINIO)"
cat > /etc/nginx/sites-available/torque <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMINIO;
    root $APP/public;
    index index.php;
    client_max_body_size 25m;
    charset utf-8;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    location ~ ^/index\.php(/|\$) {
        fastcgi_pass unix:/run/php/php$PHPV-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location /build/ { expires 30d; access_log off; }
}
EOF
ln -sf /etc/nginx/sites-available/torque /etc/nginx/sites-enabled/torque
rm -f /etc/nginx/sites-enabled/default
nginx -t -q && systemctl reload nginx

echo "== 6/8 Agendador (backup 02:30, notas a cada 5 min, lembretes 9h)"
cat > /etc/cron.d/torque <<EOF
* * * * * www-data cd $APP && php artisan schedule:run >> /dev/null 2>&1
EOF
chmod 644 /etc/cron.d/torque

echo "== 7/8 Deploy automático (usuário deploy e permissões)"
bash "$AQUI/../deploy/preparar-servidor.sh" "$APP" "$DOMINIO" | tee "$TMP/segredos.txt"

echo "== 8/8 Caches e conferência"
D() { sudo -u deploy -H php "$APP/artisan" "$@"; }
D config:cache >/dev/null && D route:cache >/dev/null && D view:cache >/dev/null
D migrate:status >/dev/null && echo "   banco OK (migrations lidas)"
[ -f "$APP/storage/framework/down" ] && ESTADO="EM MANUTENÇÃO (veio assim do servidor antigo)" || ESTADO="no ar"
IP=$(curl -s -4 --max-time 5 https://ifconfig.me || hostname -I | cut -d' ' -f1)

cat <<EOF

=====================================================================
Importação concluída. Sistema: $ESTADO

1) Testar ANTES de mudar o DNS: no seu computador, adicione ao arquivo hosts
   $IP  $DOMINIO
   e abra http://$DOMINIO (sem https). Confira login, uma OS, uma foto e uma nota.
   Se estiver em manutenção: sudo -u deploy php $APP/artisan up

2) Mudar o DNS: registro A de $DOMINIO -> $IP

3) Depois que o DNS propagar:
   certbot --nginx -d $DOMINIO --redirect -m SEU_EMAIL --agree-tos -n

4) GitHub: troque os segredos DEPLOY_HOST, DEPLOY_PORT, DEPLOY_KNOWN_HOSTS e
   DEPLOY_SSH_KEY pelos mostrados acima (passo 7/8).

5) Apague o pacote: shred -u $ARQ
=====================================================================
EOF
