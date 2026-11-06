#!/usr/bin/env bash
# Prepara o sistema em modo demonstração (SQLite + dados de exemplo).
set -e
composer install --no-interaction --prefer-dist --no-progress
[ -f .env ] || cp .env.example .env
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/; s/^TRUSTED_PROXIES=.*/TRUSTED_PROXIES=*/' .env
php artisan key:generate --force
touch database/database.sqlite
php artisan migrate:fresh --seed --force
npm ci
npm run build
echo "Pronto. Login: dono@demo.com.br / senha12345"
