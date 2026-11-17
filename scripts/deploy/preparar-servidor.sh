#!/usr/bin/env bash
# Prepara o servidor para o deploy automático do Torque. Rodar UMA vez, como root:
#   sudo bash preparar-servidor.sh [pasta] [dominio]
# Padrão: /var/www/torque e torque.softmaxbr.com.br
#
# O que faz:
#  - cria o usuário "deploy" (sem senha, entra só com chave) no grupo www-data;
#  - passa a pasta do sistema para o deploy (o PHP continua lendo pelo grupo www-data);
#  - libera storage e bootstrap/cache para o deploy e o www-data escreverem;
#  - permite ao deploy só um comando como root: recarregar o PHP-FPM;
#  - gera a chave SSH e mostra o que colar nos Segredos do GitHub (a chave privada é apagada do servidor em seguida).
set -euo pipefail

APP="${1:-/var/www/torque}"
DOMINIO="${2:-torque.softmaxbr.com.br}"
USUARIO=deploy
PHPFPM=php8.3-fpm

[ "$(id -u)" = 0 ] || { echo "Rode como root (sudo)."; exit 1; }
[ -f "$APP/artisan" ] && [ -f "$APP/.env" ] || { echo "Não achei o Torque em $APP."; exit 1; }
systemctl list-unit-files "$PHPFPM.service" >/dev/null || { echo "Não achei o serviço $PHPFPM."; exit 1; }
command -v mysqldump >/dev/null || { echo "Instale o mysqldump antes (apt install mariadb-client)."; exit 1; }

echo "== 1/6 Pacotes (rsync e acl)"
apt-get install -y -qq rsync acl >/dev/null

echo "== 2/6 Cópia das permissões atuais (para desfazer: zcat /root/torque-permissoes-antes.acl.gz | setfacl --restore=-)"
getfacl -R -p "$APP" 2>/dev/null | gzip > /root/torque-permissoes-antes.acl.gz

echo "== 3/6 Usuário $USUARIO"
id "$USUARIO" >/dev/null 2>&1 || adduser --disabled-password --gecos "Deploy automático (GitHub Actions)" "$USUARIO" >/dev/null
usermod -aG www-data "$USUARIO"
passwd -l "$USUARIO" >/dev/null

echo "== 4/6 Permissões de $APP"
chown -R "$USUARIO":www-data "$APP"
find "$APP" -path "$APP/storage" -prune -o -path "$APP/bootstrap/cache" -prune -o -type d -exec chmod 2750 {} +
find "$APP" -path "$APP/storage" -prune -o -path "$APP/bootstrap/cache" -prune -o -type f -exec chmod 640 {} +
chmod 640 "$APP/.env"
for d in "$APP/storage" "$APP/bootstrap/cache"; do
  find "$d" -type d -exec chmod 2770 {} +
  find "$d" -type f -exec chmod 660 {} +
  setfacl -R -m "u:$USUARIO:rwX,u:www-data:rwX,g:www-data:rwX" "$d"
  setfacl -R -d -m "u:$USUARIO:rwX,u:www-data:rwX,g:www-data:rwX" "$d"
done
# A pasta-mãe precisa deixar o deploy entrar.
setfacl -m "u:$USUARIO:x" "$(dirname "$APP")"

echo "== 5/6 Recarregar o PHP-FPM sem senha (só esse comando)"
cat > /etc/sudoers.d/torque-deploy <<EOF
$USUARIO ALL=(root) NOPASSWD: /usr/bin/systemctl reload $PHPFPM
EOF
chmod 440 /etc/sudoers.d/torque-deploy
visudo -cf /etc/sudoers.d/torque-deploy >/dev/null

echo "== 6/6 Chave SSH"
CASA=$(getent passwd "$USUARIO" | cut -d: -f6)
install -d -m 700 -o "$USUARIO" -g "$USUARIO" "$CASA/.ssh"
TMP=$(mktemp -d)
ssh-keygen -q -t ed25519 -N "" -C "github-actions-torque" -f "$TMP/chave"
# "restrict": sem terminal interativo e sem túneis. Só comandos e rsync.
echo "restrict $(cat "$TMP/chave.pub")" > "$CASA/.ssh/authorized_keys"
chown "$USUARIO":"$USUARIO" "$CASA/.ssh/authorized_keys"
chmod 600 "$CASA/.ssh/authorized_keys"

PORTA=$(sshd -T 2>/dev/null | awk '/^port /{print $2; exit}')
PORTA=${PORTA:-22}
HOSTKEY=$(cut -d' ' -f1,2 /etc/ssh/ssh_host_ed25519_key.pub)
if [ "$PORTA" = 22 ]; then NOME="$DOMINIO"; else NOME="[$DOMINIO]:$PORTA"; fi

# Teste: o deploy consegue rodar o artisan e fazer backup.
sudo -u "$USUARIO" -H bash -c "cd '$APP' && php artisan --version" >/dev/null \
  || { echo "ATENÇÃO: o usuário deploy não conseguiu rodar o artisan."; }

cat <<EOF

=====================================================================
Pronto. Agora no GitHub: canalof/oficina > Settings > Secrets and
variables > Actions > New repository secret. Crie estes 4 segredos:

DEPLOY_HOST
$DOMINIO

DEPLOY_PORT
$PORTA

DEPLOY_KNOWN_HOSTS
$NOME $HOSTKEY

DEPLOY_SSH_KEY   (copie TUDO, da linha BEGIN até a linha END)
$(cat "$TMP/chave")
=====================================================================
A chave privada acima NÃO fica guardada no servidor. Se perder, rode
este script de novo (gera outra e invalida a anterior).
Para cortar o acesso do GitHub: rm $CASA/.ssh/authorized_keys
EOF

shred -u "$TMP/chave" 2>/dev/null || rm -f "$TMP/chave"
rm -rf "$TMP"
