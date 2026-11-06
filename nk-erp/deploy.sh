#!/bin/sh
# Build release $1 next to the live one, then switch /srv/app to it in one step.
set -e
REF=$1
MODE=$2
R=https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/$REF/nk-erp
CUR=$(readlink -f /srv/app)
NEW=/srv/releases/$REF
[ "$CUR" = "$NEW" ] && { echo "$REF" > /srv/deploy/current_ref; exit 0; }
# Build at the final path: Laravel caches absolute paths, so the release must not be renamed afterwards.
rm -rf "$NEW"
cp -a "$CUR" "$NEW"
rm -rf "$NEW/storage/framework/views/"*
cd "$NEW"
wget -qO /tmp/manifest.txt "$R/manifest.txt"
[ -s /tmp/manifest.txt ] || { echo "no manifest"; exit 1; }
for f in $(cat /tmp/manifest.txt); do
  mkdir -p "$(dirname "$f")"; wget -q -O "$f.part" "$R/overlay/$f" && mv "$f.part" "$f"
done
cat > .env <<ENV
APP_NAME="NK Corporation"
APP_ENV=production
APP_KEY=$(cat /srv/.appkey)
APP_DEBUG=false
APP_URL=https://admin.nklpgbd.com
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
rm -f bootstrap/cache/*.php
COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -o --no-interaction >/dev/null 2>&1 || true
php artisan migrate --force
php artisan nk:install
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
# Uploaded pictures (logo, signature, hero, products, brands) live in public/uploads; the web user must be able to write there.
# Copy over anything uploaded while this release was building, so no upload is lost at the switch.
mkdir -p public/uploads
cp -an "$CUR/public/uploads/." public/uploads/ 2>/dev/null || true
chown -R www-data:www-data public/uploads
ln -sfn "$NEW" /srv/app
echo "$REF" > /srv/deploy/current_ref
rm -f /srv/deploy/failed_ref
# Keep the three newest releases.
ls -1dt /srv/releases/*/ | tail -n +4 | while read d; do [ "$(readlink -f /srv/app)/" = "$d" ] || rm -rf "$d"; done
echo "NK DEPLOY: built $REF"
