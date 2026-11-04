#!/bin/sh
# NK Corporation admin (Laravel + PostgreSQL) bootstrap: install Laravel once, overlay our code, migrate, serve.
set -e
R=https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/${REF}/nk-erp
echo "NK BOOT ref=${REF}"
cd /srv
EXT=/srv/.ext/$(php -r 'echo PHP_VERSION;'); ED=$(php -r 'echo ini_get("extension_dir");')
if [ -f "$EXT/pdo_pgsql.so" ]; then
  apk add --no-cache libpq >/dev/null 2>&1 || true
  cp "$EXT"/*.so "$ED"/
  echo "extension=pdo_pgsql" > /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini
  [ -f "$EXT/opcache.so" ] && echo "zend_extension=opcache" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
  echo "NK BOOT: cached extensions"
fi
[ -f /srv/.ext/composer ] && cp /srv/.ext/composer /usr/local/bin/composer && chmod +x /usr/local/bin/composer
if ! php -m | grep -q pdo_pgsql; then
  apk add --no-cache postgresql-dev >/dev/null
  docker-php-ext-install pdo_pgsql >/dev/null
fi
php -m | grep -qi 'zend opcache' || docker-php-ext-install opcache >/dev/null
if ! command -v composer >/dev/null; then
  apk add --no-cache git unzip >/dev/null
  wget -qO /usr/local/bin/composer https://getcomposer.org/download/latest-stable/composer.phar && chmod +x /usr/local/bin/composer
fi
mkdir -p "$EXT"; cp "$ED/pdo_pgsql.so" "$EXT/" 2>/dev/null || true; cp "$ED/opcache.so" "$EXT/" 2>/dev/null || true; cp /usr/local/bin/composer /srv/.ext/composer 2>/dev/null || true
if [ ! -f /srv/app/artisan ]; then
  apk add --no-cache git unzip >/dev/null
  rm -rf /srv/app
  COMPOSER_ALLOW_SUPERUSER=1 composer create-project laravel/laravel app --no-interaction --prefer-dist --no-scripts
  cd /srv/app && COMPOSER_ALLOW_SUPERUSER=1 composer run-script post-autoload-dump --no-interaction || true
  cd /srv
fi
cd /srv/app
php artisan --version
for f in $(wget -qO- "$R/manifest.txt"); do
  mkdir -p "$(dirname "$f")"; wget -q -O "$f" "$R/overlay/$f"
done
[ -s /srv/.appkey ] || php -r 'echo "base64:".base64_encode(random_bytes(32));' > /srv/.appkey
cat > .env <<ENV
APP_NAME="NK Corporation"
APP_ENV=production
APP_KEY=$(cat /srv/.appkey)
APP_DEBUG=false
APP_URL=https://admin.nklpgbd.com
APP_TIMEZONE=Asia/Dhaka
LOG_CHANNEL=stderr
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=nkerp
DB_USERNAME=nkerp
DB_PASSWORD=${DB_PASSWORD}
SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=admin.nklpgbd.com
CACHE_STORE=database
QUEUE_CONNECTION=database
NK_ADMIN_EMAIL=${ADMIN_EMAIL}
NK_ADMIN_PASSWORD=${ADMIN_PASSWORD}
ENV
cat > /usr/local/etc/php/conf.d/zz-nk.ini <<'INI'
opcache.enable=1
opcache.validate_timestamps=0
expose_php=0
memory_limit=256M
upload_max_filesize=10M
post_max_size=12M
date.timezone=Asia/Dhaka
INI
cat > /usr/local/etc/php-fpm.d/zz-pool.conf <<'POOL'
[www]
pm = dynamic
pm.max_children = 12
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000
POOL
i=0; until php -r 'new PDO("pgsql:host=db;dbname=nkerp","nkerp",getenv("DB_PASSWORD"));' 2>/dev/null; do i=$((i+1)); [ $i -gt 30 ] && break; echo "waiting for db"; sleep 2; done
php artisan config:clear
php artisan migrate --force
php artisan nk:install
php artisan config:cache
php artisan route:cache || echo "NK WARN: route cache failed"
php artisan view:clear
php artisan view:cache || echo "NK WARN: view cache failed"
chown -R www-data:www-data storage bootstrap/cache
echo "NK BOOT: ready"
exec php-fpm
