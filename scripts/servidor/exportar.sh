#!/usr/bin/env bash
# Troca de servidor, passo 1 (no servidor ANTIGO, como root):
#   sudo bash exportar.sh                 # ensaio: o sistema continua no ar
#   sudo bash exportar.sh --manutencao    # corte final: coloca em manutenção antes do backup
# Opcional: pasta do sistema como 2º argumento (padrão /var/www/torque).
#
# Gera /root/torque-migracao-AAAA-MM-DD-HHMM.tar.gz com:
#   torque/        código, .env (APP_KEY!) e storage (fotos das OS, XML das compras)
#   banco.sql.gz   backup do banco feito agora (php artisan torque:backup)
# O arquivo tem senhas e dados de clientes: só root lê, e apague depois da troca.
set -euo pipefail

MANUTENCAO=0
if [ "${1:-}" = "--manutencao" ]; then MANUTENCAO=1; shift; fi
APP="${1:-/var/www/torque}"

[ "$(id -u)" = 0 ] || { echo "Rode como root (sudo)."; exit 1; }
[ -f "$APP/artisan" ] && [ -f "$APP/.env" ] || { echo "Não achei o Torque em $APP."; exit 1; }

DONO=$(stat -c %U "$APP/artisan")
artisan() { sudo -u "$DONO" -H php "$APP/artisan" "$@"; }

if [ "$MANUTENCAO" = 1 ]; then
  echo "== Modo manutenção (ninguém grava nada a partir de agora)"
  artisan down --retry=60
fi

echo "== Backup do banco"
artisan torque:backup
DUMP=$(ls -1t "$APP"/storage/app/backups/torque-*.sql.gz 2>/dev/null | head -1 || true)
[ -n "$DUMP" ] || { echo "Backup não encontrado em storage/app/backups (o banco é MySQL/MariaDB?)."; exit 1; }

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
cp "$DUMP" "$TMP/banco.sql.gz"
crontab -l > "$TMP/crontab-root.txt" 2>/dev/null || true
crontab -u www-data -l >> "$TMP/crontab-root.txt" 2>/dev/null || true

B=$(basename "$APP")
SAIDA="/root/torque-migracao-$(date +%Y-%m-%d-%H%M).tar.gz"
echo "== Empacotando em $SAIDA"
umask 077
tar -czf "$SAIDA" \
  --exclude="$B/node_modules" \
  --exclude="$B/storage/logs/*" \
  --exclude="$B/storage/app/backups/*" \
  --exclude="$B/storage/framework/cache/data/*" \
  --exclude="$B/storage/framework/sessions/*" \
  --exclude="$B/storage/framework/views/*" \
  --exclude="$B/bootstrap/cache/*.php" \
  --transform="s,^$B,torque," \
  -C "$(dirname "$APP")" "$B" \
  -C "$TMP" banco.sql.gz crontab-root.txt
chmod 600 "$SAIDA"

echo
echo "Pronto: $SAIDA ($(du -h "$SAIDA" | cut -f1))"
echo "sha256: $(sha256sum "$SAIDA" | cut -d' ' -f1)"
echo
echo "Copie para o servidor novo, por exemplo:"
echo "  scp $SAIDA root@IP_DO_SERVIDOR_NOVO:/root/"
if [ "$MANUTENCAO" = 1 ]; then
  echo
  echo "ATENÇÃO: este servidor ficou em manutenção. Para voltar atrás: sudo -u $DONO php $APP/artisan up"
fi
