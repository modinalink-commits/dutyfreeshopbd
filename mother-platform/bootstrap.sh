#!/bin/sh
# Mother Commerce Platform bootstrap: installs Laravel once, overlays our code, migrates, serves.
set -e
R=https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/main/mother-platform
cd /srv
if ! command -v composer >/dev/null; then
  apk add --no-cache postgresql-dev git unzip >/dev/null
  wget -qO /usr/local/bin/composer https://getcomposer.org/download/latest-stable/composer.phar && chmod +x /usr/local/bin/composer
fi
if [ ! -f /srv/app/artisan ]; then
  apk add --no-cache postgresql-dev git unzip >/dev/null
  docker-php-ext-install pdo_pgsql >/dev/null
  COMPOSER_ALLOW_SUPERUSER=1 composer create-project laravel/laravel app --no-interaction --prefer-dist
fi
cd /srv/app
php -m | grep -q pdo_pgsql || { apk add --no-cache postgresql-dev >/dev/null; docker-php-ext-install pdo_pgsql >/dev/null; }
for f in $(wget -qO- $R/manifest.txt); do
  mkdir -p "$(dirname "$f")"; wget -q -O "$f" "$R/overlay/$f"
done
rm -f database/migrations/0001_01_01_000000_create_users_table.php.bak
[ -f .env ] || cp .env.example .env
sed -i "s|^APP_NAME=.*|APP_NAME=\"Mother Commerce\"|; s|^APP_URL=.*|APP_URL=${APP_URL}|; s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|; s|^# DB_HOST=.*|DB_HOST=db|; s|^DB_HOST=.*|DB_HOST=db|; s|^# DB_PORT=.*|DB_PORT=5432|; s|^DB_PORT=.*|DB_PORT=5432|; s|^# DB_DATABASE=.*|DB_DATABASE=mother|; s|^DB_DATABASE=.*|DB_DATABASE=mother|; s|^# DB_USERNAME=.*|DB_USERNAME=mother|; s|^DB_USERNAME=.*|DB_USERNAME=mother|; s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|; s|^CACHE_STORE=.*|CACHE_STORE=file|; s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
until php -r 'new PDO("pgsql:host=db;dbname=mother","mother",getenv("DB_PASSWORD"));' 2>/dev/null; do echo waiting for db; sleep 2; done
php artisan migrate --force
php artisan mother:install
php artisan config:clear
exec php artisan serve --host=0.0.0.0 --port=8000
