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
# ---- Release layout: /srv/app -> /srv/releases/<ref>. Deploys build a new release and swap the link, so the site never goes down.
mkdir -p /srv/releases /srv/deploy
if [ -d /srv/app ] && [ ! -L /srv/app ]; then
  mv /srv/app /srv/releases/base && ln -sfn /srv/releases/base /srv/app
  echo "NK BOOT: moved app to release layout"
fi
cd /srv/app
php artisan --version
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
[global]
pid = /run/php-fpm.pid
[www]
pm = dynamic
pm.max_children = 12
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000
POOL
i=0; until php -r 'new PDO("pgsql:host=db;dbname=nkerp","nkerp",getenv("DB_PASSWORD"));' 2>/dev/null; do i=$((i+1)); [ $i -gt 30 ] && break; echo "waiting for db"; sleep 2; done
[ -s /srv/.appkey ] || php -r 'echo "base64:".base64_encode(random_bytes(32));' > /srv/.appkey

if [ ! -s /srv/deploy/current_ref ]; then
  # First install of the release system: build the release for the compose REF in place.
  wget -qO /tmp/d.sh "$R/deploy.sh" && sh /tmp/d.sh "$REF" boot
fi
# Make sure the live release has caches for this container (plain restart keeps the live code).
cd /srv/app && php artisan config:cache >/dev/null && php artisan route:cache >/dev/null || true
chown -R www-data:www-data /srv/app/storage /srv/app/bootstrap/cache
echo "NK BOOT: ready, live ref $(cat /srv/deploy/current_ref 2>/dev/null)"

php-fpm -F &
FPM=$!
# Watch for new releases. The pointer file nk-erp/LIVE on the branch names the commit to run.
RAWB=https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd
while true; do
  sleep 45
  kill -0 $FPM 2>/dev/null || { echo "NK: php-fpm stopped"; exit 1; }
  L=$(wget -qO- "$RAWB/nklpgbd/nk-erp/LIVE?ts=$(date +%s)" 2>/dev/null | tr -dc 'a-f0-9' | head -c 40)
  [ ${#L} -eq 40 ] || continue
  [ "$L" = "$(cat /srv/deploy/current_ref 2>/dev/null)" ] && continue
  [ "$L" = "$(cat /srv/deploy/failed_ref 2>/dev/null)" ] && continue
  echo "NK DEPLOY: starting $L"
  if wget -qO /tmp/d.sh "$RAWB/$L/nk-erp/deploy.sh" && sh /tmp/d.sh "$L"; then
    kill -USR2 $FPM && echo "NK DEPLOY: live $L"
  else
    echo "$L" > /srv/deploy/failed_ref; echo "NK DEPLOY: FAILED $L — the previous release stays live"
  fi
done
