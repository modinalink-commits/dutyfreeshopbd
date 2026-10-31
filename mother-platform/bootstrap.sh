#!/bin/sh
# Mother Commerce Platform bootstrap: installs Laravel once, overlays our code, migrates, serves.
set -e
R=https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/${REF:-main}/mother-platform
echo "BOOTSTRAP v8 ref=${REF:-main}"
cd /srv
# Fast boot: reuse extensions and composer compiled on an earlier boot (same PHP version).
EXT=/srv/.ext/$(php -r 'echo PHP_VERSION;'); ED=$(php -r 'echo ini_get("extension_dir");')
if [ -f "$EXT/pdo_pgsql.so" ]; then
  apk add --no-cache libpq >/dev/null 2>&1 || true
  cp "$EXT"/*.so "$ED"/
  echo "extension=pdo_pgsql" > /usr/local/etc/php/conf.d/docker-php-ext-pdo_pgsql.ini
  [ -f "$EXT/opcache.so" ] && echo "zend_extension=opcache" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini
  echo "BOOT fast path: cached extensions"
fi
[ -f /srv/.ext/composer ] && cp /srv/.ext/composer /usr/local/bin/composer && chmod +x /usr/local/bin/composer
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
php -m | grep -qi 'zend opcache' || { docker-php-ext-install opcache >/dev/null; }
cat > /usr/local/etc/php/conf.d/zz-prod.ini <<'INI'
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.jit=tracing
opcache.jit_buffer_size=64M
realpath_cache_size=4096K
realpath_cache_ttl=600
expose_php=0
upload_max_filesize=25M
post_max_size=30M
memory_limit=512M
INI
cat > /usr/local/etc/php-fpm.d/zz-pool.conf <<'POOL'
[www]
pm = dynamic
pm.max_children = 60
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 24
pm.max_requests = 1000
request_terminate_timeout = 60s
POOL
php -m | grep -q pdo_pgsql || { apk add --no-cache postgresql-dev >/dev/null; docker-php-ext-install pdo_pgsql >/dev/null; }
mkdir -p "$EXT"; cp "$ED/pdo_pgsql.so" "$EXT/" 2>/dev/null || true; cp "$ED/opcache.so" "$EXT/" 2>/dev/null || true; cp /usr/local/bin/composer /srv/.ext/composer 2>/dev/null || true
for f in $(wget -qO- $R/manifest.txt); do
  mkdir -p "$(dirname "$f")"; wget -q -O "$f" "$R/overlay/$f"
done
rm -f database/migrations/0001_01_01_000000_create_users_table.php.bak
[ -f .env ] || cp .env.example .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|; s|^APP_DEBUG=.*|APP_DEBUG=false|; s|^LOG_LEVEL=.*|LOG_LEVEL=warning|; s|^APP_NAME=.*|APP_NAME=\"Mother Commerce\"|; s|^APP_URL=.*|APP_URL=${APP_URL}|; s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|; s|^# DB_HOST=.*|DB_HOST=db|; s|^DB_HOST=.*|DB_HOST=db|; s|^# DB_PORT=.*|DB_PORT=5432|; s|^DB_PORT=.*|DB_PORT=5432|; s|^# DB_DATABASE=.*|DB_DATABASE=mother|; s|^DB_DATABASE=.*|DB_DATABASE=mother|; s|^# DB_USERNAME=.*|DB_USERNAME=mother|; s|^DB_USERNAME=.*|DB_USERNAME=mother|; s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^SESSION_DRIVER=.*|SESSION_DRIVER=cookie|; s|^CACHE_STORE=.*|CACHE_STORE=file|; s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
until php -r 'new PDO("pgsql:host=db;dbname=mother","mother",getenv("DB_PASSWORD"));' 2>/dev/null; do echo waiting for db; sleep 2; done
php artisan migrate --force
php artisan mother:install || echo "INSTALL STEP FAILED (site keeps running)"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction -q
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan mother:smoke || true
chown -R www-data:www-data storage bootstrap/cache
# Product feed: import now and every hour (runs as www-data so cache files stay writable)
( su -s /bin/sh www-data -c 'php artisan mother:sync-feed'; while true; do sleep 3600; su -s /bin/sh www-data -c 'php artisan mother:sync-feed'; done ) > /proc/1/fd/1 2>&1 &
exec php-fpm
