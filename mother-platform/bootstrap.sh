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
  if [ -f "$EXT/gd.so" ]; then apk add --no-cache libwebp libjpeg-turbo libpng freetype >/dev/null 2>&1 || true; echo "extension=gd" > /usr/local/etc/php/conf.d/docker-php-ext-gd.ini; fi
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
# Release layout (zero-downtime deploys): /srv/app is a symlink to /srv/releases/<id>; storage, .env and uploads are shared between releases.
mkdir -p /srv/releases /srv/shared /srv/deploy; chmod 777 /srv/deploy
if [ -d /srv/app ] && [ ! -L /srv/app ]; then
  echo "ADOPT release layout"
  [ -d /srv/app/storage ] && [ ! -e /srv/shared/storage ] && mv /srv/app/storage /srv/shared/storage
  [ -f /srv/app/.env ] && [ ! -e /srv/shared/.env ] && mv /srv/app/.env /srv/shared/.env
  if [ -d /srv/app/public/uploads ] && [ ! -L /srv/app/public/uploads ]; then [ -e /srv/shared/uploads ] || mv /srv/app/public/uploads /srv/shared/uploads; rm -rf /srv/app/public/uploads; fi
  mkdir -p /srv/shared/uploads /srv/shared/storage
  if [ ! -e /srv/releases/initial ]; then mv /srv/app /srv/releases/initial; else rm -rf /srv/app; fi
  [ -f /srv/shared/.env ] && ln -sfn /srv/shared/.env /srv/releases/initial/.env
  rm -rf /srv/releases/initial/storage; ln -sfn /srv/shared/storage /srv/releases/initial/storage
  rm -rf /srv/releases/initial/public/uploads; ln -sfn /srv/shared/uploads /srv/releases/initial/public/uploads
  echo initial > /srv/releases/initial/RELEASE
  ln -sfn /srv/releases/initial /srv/app
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
php -m | grep -qi '^gd$' || { apk add --no-cache libwebp-dev libjpeg-turbo-dev libpng-dev freetype-dev >/dev/null 2>&1 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp >/dev/null 2>&1 && docker-php-ext-install -j2 gd >/dev/null 2>&1 || echo "GD install failed"; }
mkdir -p "$EXT"; cp "$ED/gd.so" "$EXT/" 2>/dev/null || true; cp "$ED/pdo_pgsql.so" "$EXT/" 2>/dev/null || true; cp "$ED/opcache.so" "$EXT/" 2>/dev/null || true; cp /usr/local/bin/composer /srv/.ext/composer 2>/dev/null || true
# A plain container restart must not downgrade code that zero-downtime deploys already put live.
SKIPOVERLAY=""; [ "$(cat /srv/deploy/boot_ref 2>/dev/null)" = "${REF:-main}" ] && [ -f /srv/deploy/current ] && SKIPOVERLAY=1
echo "${REF:-main}" > /srv/deploy/boot_ref
if [ -z "$SKIPOVERLAY" ]; then
for f in $(wget -qO- $R/manifest.txt); do
  mkdir -p "$(dirname "$f")"; wget -q -O "$f" "$R/overlay/$f"
done
echo "$(readlink -f /srv/app)" > /srv/deploy/current
else echo "BOOT: keeping the live release (container restart only)"; fi
wget -qO /srv/deploy/deploy.sh "$R/deploy.sh" || true
rm -f database/migrations/0001_01_01_000000_create_users_table.php.bak
[ -e .env ] || cp .env.example .env
if [ ! -L .env ]; then mv .env /srv/shared/.env; ln -sfn /srv/shared/.env .env; fi
ENVF=$(readlink -f .env)
sed -i "s|^APP_ENV=.*|APP_ENV=production|; s|^APP_DEBUG=.*|APP_DEBUG=false|; s|^LOG_LEVEL=.*|LOG_LEVEL=warning|; s|^APP_NAME=.*|APP_NAME=\"Mother Commerce\"|; s|^APP_URL=.*|APP_URL=${APP_URL}|; s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|; s|^# DB_HOST=.*|DB_HOST=db|; s|^DB_HOST=.*|DB_HOST=db|; s|^# DB_PORT=.*|DB_PORT=5432|; s|^DB_PORT=.*|DB_PORT=5432|; s|^# DB_DATABASE=.*|DB_DATABASE=mother|; s|^DB_DATABASE=.*|DB_DATABASE=mother|; s|^# DB_USERNAME=.*|DB_USERNAME=mother|; s|^DB_USERNAME=.*|DB_USERNAME=mother|; s|^# DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|; s|^SESSION_DRIVER=.*|SESSION_DRIVER=cookie|; s|^CACHE_STORE=.*|CACHE_STORE=file|; s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" "$ENVF"
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
until php -r 'new PDO("pgsql:host=db;dbname=mother","mother",getenv("DB_PASSWORD"));' 2>/dev/null; do echo waiting for db; sleep 2; done
php artisan migrate --force
php artisan mother:install || echo "INSTALL STEP FAILED (site keeps running)"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction -q
php artisan config:cache
php artisan route:cache
php artisan view:cache
for f in storage/framework/views/*.php; do php -l "$f" >/dev/null 2>&1 || echo "VIEWLINT FAIL $(head -c 220 "$f" | grep -o 'PATH [^ ]*') :: $(php -l "$f" 2>&1 | head -1)"; done
php artisan mother:smoke || true
mkdir -p /srv/shared/uploads
chown -R www-data:www-data /srv/shared/storage bootstrap/cache /srv/shared/uploads
# Product feed: import now and every hour (runs as www-data so cache files stay writable)
( su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:sync-feed'; while true; do sleep 3600; su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:sync-feed'; done ) > /proc/1/fd/1 2>&1 &
# Guard: every 10 minutes re-assert the owner's standing settings (WhatsApp confirmation, AI chat always on) and product photos
( sleep 240; while true; do su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:guard'; sleep 600; done ) > /proc/1/fd/1 2>&1 &
# Courier: check open parcels every 10 minutes so order stages update by themselves
( sleep 120; while true; do su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:courier-sync'; sleep 600; done ) > /proc/1/fd/1 2>&1 &
( sleep 45; while true; do su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:push-worker'; sleep 30; done ) > /proc/1/fd/1 2>&1 &
# Images: optimise every upload and copy outside-hosted product/category/banner images into our own storage, quietly, every 2 minutes
( sleep 90; while true; do su -s /bin/sh www-data -c 'cd /srv/app && php artisan mother:images'; sleep 120; done ) > /proc/1/fd/1 2>&1 &
# Zero-downtime deploys: the admin Deployments page (or Claude) drops a request file; this root loop builds and switches a new release without stopping the site.
mkdir -p /srv/deploy
cat > /srv/deploy/loop.sh <<'LOOP'
#!/bin/sh
# Deploy worker: heartbeat every cycle so the watchdog (and the admin alert bell) can tell it is alive.
while true; do
  touch /srv/deploy/hb 2>/dev/null; chmod 666 /srv/deploy/hb 2>/dev/null
  if [ -f /srv/deploy/request ]; then
    REQ=$(cat /srv/deploy/request); rm -f /srv/deploy/request
    if echo "$REQ" | grep -Eq '^([0-9a-f]{40}( allow-breaking)?|rollback)$'; then
      SHA=${REQ%% *}
      [ "$SHA" != rollback ] && wget -q -T 20 -t 3 -O /srv/deploy/deploy.new "https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/$SHA/mother-platform/deploy.sh" && mv /srv/deploy/deploy.new /srv/deploy/deploy.sh
      [ -f /srv/deploy/deploy.sh ] && sh /srv/deploy/deploy.sh $REQ > /srv/deploy/last.log 2>&1
      chmod 666 /srv/deploy/last.log /srv/deploy/status 2>/dev/null
    fi
  fi
  sleep 3
done
LOOP
chmod 755 /srv/deploy/loop.sh
# Zero-downtime deploys: the admin Deployments page (or Claude) drops a request file; this root loop builds and switches a new release without stopping the site.
( sh /srv/deploy/loop.sh ) > /proc/1/fd/1 2>&1 &
# Watchdog: if the deploy worker stops beating for 2 minutes while no deploy is running, restart it by itself (a deploy can take minutes, so only restart when no deploy holds the lock).
( sleep 20; while true; do
  NOW=$(date +%s); HB=$(stat -c %Y /srv/deploy/hb 2>/dev/null || echo 0)
  if [ $((NOW - HB)) -gt 120 ] && flock -n /srv/deploy/lock true 2>/dev/null; then
    pkill -f /srv/deploy/loop.sh 2>/dev/null; sleep 1
    ( sh /srv/deploy/loop.sh ) > /proc/1/fd/1 2>&1 &
  fi
  sleep 10
done ) > /proc/1/fd/1 2>&1 &
exec php-fpm
