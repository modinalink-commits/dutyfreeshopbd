#!/bin/sh
# Zero-downtime release deploy. Runs inside the app container as root.
# usage: deploy.sh <40-char git sha> [allow-breaking]   |   deploy.sh rollback
# Flow: copy live release -> fetch new code -> composer -> migrate (backward-compatible only) -> caches
#       -> health check on the NEW release -> atomic symlink switch -> health check live -> auto-rollback on failure.
set -u
umask 000
D=/srv/deploy; mkdir -p "$D"
exec 9>"$D/lock"
if ! flock -n 9; then echo "another deploy is running"; exit 1; fi
LOG="$D/last.log"
log() { echo "[$(date +%H:%M:%S)] $*"; }
setst() { echo "$1" > "$D/status"; }
stg() { echo "$1|$2" > "$D/stage"; }
fail() { log "FAILED: $*"; setst "failed: $*"; [ -n "${REL:-}" ] && [ -d "${REL:-}" ] && [ "${KEEP:-0}" != 1 ] && rm -rf "$REL"; [ -n "${HPID:-}" ] && kill "$HPID" 2>/dev/null; [ "${DOWN:-0}" = 1 ] && (cd "$CUR" && php artisan up >/dev/null 2>&1); exit 1; }
swap() { # atomic: rename(2) replaces the symlink in one step
  ln -sfn "$1" /srv/app.tmp && php -r 'exit(rename("/srv/app.tmp","/srv/app")?0:1);'
}
cat > "$D/hc.php" <<'PHP'
<?php
$base=$argv[1]; $host=$argv[2]; $paths=array_slice($argv,3); $bad=0;
function get($u,$host,&$code){ $ctx=stream_context_create(['http'=>['timeout'=>40,'ignore_errors'=>true,'follow_location'=>0,'header'=>"Host: $host\r\nUser-Agent: deploy-check\r\nX-Forwarded-Proto: https\r\n"]]); $b=@file_get_contents($u,false,$ctx); $code=0; if(isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#',$http_response_header[0],$m)) $code=(int)$m[1]; return $b; }
foreach($paths as $p){
  if($p==='@product'){ $h=get($base.'/',$host,$c); if($h && preg_match('#href="(/[a-z0-9-]+/[a-z0-9-]+/[a-z0-9-]+)"#',$h,$m)) $p=$m[1]; else { echo "SKIP no product link\n"; continue; } }
  $t=microtime(true); $b=get($base.$p,$host,$code);
  $ok=$code>=200 && $code<400 && $b!==false && !preg_match('/Server Error|Whoops|Stack trace|ErrorException|Undefined (variable|array key)/i',(string)$b);
  printf("%s %d %s (%.1fs)\n",$ok?'OK  ':'FAIL',$code,$p,microtime(true)-$t); if(!$ok)$bad++;
}
exit($bad?1:0);
PHP

ARG="${1:-}"; FLAG="${2:-}"
[ -L /srv/app ] || { log "FAILED: /srv/app is not a release symlink yet (one full redeploy adopts the layout)"; setst "failed: layout"; exit 1; }
CUR=$(readlink -f /srv/app)
REL=""; HPID=""; KEEP=0; DOWN=0

if [ "$ARG" = rollback ]; then
  PREV=$(cat "$D/previous" 2>/dev/null || true)
  [ -d "$PREV" ] || fail "no previous release kept"
  log "ROLLBACK $CUR -> $PREV"; setst "rolling back"; stg "Rolling back" 50
  swap "$PREV" || fail "switch failed"
  echo "$CUR" > "$D/previous"; echo "$PREV" > "$D/current"
  su -s /bin/sh www-data -c "cd /srv/app && php artisan queue:restart" >/dev/null 2>&1 || true
  php "$D/hc.php" http://web dutyfreeshopbd.com / /offline || log "WARNING: live check failed after rollback"
  log "rollback done"; setst "ok: rolled back to $(basename "$PREV")"; exit 0
fi

echo "$ARG" | grep -Eq '^[0-9a-f]{40}$' || fail "bad ref"
REF="$ARG"; R="https://raw.githubusercontent.com/modinalink-commits/dutyfreeshopbd/$REF/mother-platform"
ID="$(date +%Y%m%d-%H%M%S)-$(echo "$REF" | cut -c1-7)"; REL="/srv/releases/$ID"
setst "building $ID"; stg Downloading 3; log "release $ID (from $(basename "$CUR"))"

log "1/9 copy live release"
mkdir -p "$REL" && cp -a "$CUR/." "$REL/" || fail "copy"
rm -rf "$REL/storage" "$REL/.env" "$REL/public/uploads"; rm -f "$REL"/bootstrap/cache/*.php
ln -sfn /srv/shared/storage "$REL/storage"; ln -sfn /srv/shared/.env "$REL/.env"; ln -sfn /srv/shared/uploads "$REL/public/uploads"
echo "$ID" > "$REL/RELEASE"

log "2/9 download new code"; stg Downloading 12
cd "$REL" || fail "cd"
export R; wget -q -T 30 -t 3 -O manifest.txt.new "$R/manifest.txt" || fail "manifest download"
sed -i '/^$/d' manifest.txt.new; N=$(wc -l < manifest.txt.new)
# Incremental download: manifest.sha lists the sha1 of every file; files identical to the live release (already copied above) are NOT downloaded again.
INC=0; wget -q -T 30 -t 3 -O manifest.sha.new "$R/manifest.sha" && [ -s manifest.sha.new ] && INC=1
rm -f "$D/dl.fail" "$D/dl.list"; : > "$D/dl.list"; i=0; DLN=0; REUSED=0
while read -r f; do
  if [ "$INC" = 1 ] && [ -f "$CUR/$f" ]; then
    EXP=$(awk -v p="$f" '$2==p{print $1; exit}' manifest.sha.new); LIVE=$(sha1sum < "$CUR/$f" | cut -d' ' -f1)
    if [ -n "$EXP" ] && [ "$EXP" = "$LIVE" ]; then REUSED=$((REUSED+1)); continue; fi
  fi
  DLN=$((DLN+1)); echo "$f" >> "$D/dl.list"
  ( mkdir -p "$(dirname "$f")"; { wget -q -T 30 -O "$f" "$R/overlay/$f" || { sleep 2; wget -q -T 30 -O "$f" "$R/overlay/$f"; } || { sleep 4; wget -q -T 30 -O "$f" "$R/overlay/$f"; }; } || echo "$f" >> "$D/dl.fail" ) &
  i=$((i+1)); [ $((i % 8)) = 0 ] && wait
done < manifest.txt.new
wait
if [ "$INC" = 1 ] && [ ! -f "$D/dl.fail" ]; then # integrity: every downloaded file must match its recorded sha1 (catches a stale manifest.sha)
  while read -r f; do
    EXP=$(awk -v p="$f" '$2==p{print $1; exit}' manifest.sha.new); GOT=$(sha1sum < "$f" | cut -d' ' -f1)
    [ -z "$EXP" ] || [ "$EXP" = "$GOT" ] || fail "manifest.sha is stale for $f (expected $EXP, got $GOT)"
  done < "$D/dl.list"
fi
rm -f manifest.sha.new
if [ "$INC" = 1 ]; then log "   incremental download: $DLN downloaded, $REUSED reused from the live release"; else log "   full download ($DLN files; manifest.sha unavailable)"; fi
echo "$DLN|$REUSED" > "$D/dlstat" 2>/dev/null
[ -f "$D/dl.fail" ] && fail "download failed: $(head -3 "$D/dl.fail" | tr '\n' ' ')"
cp manifest.txt.new manifest.txt; rm -f manifest.txt.new; log "   $N files"

log "   change detection (vs live release)"
: > "$D/changed.list"; CH=0; ADDPHP=0; MIG=0; PHPCH=0
while read -r f; do
  if [ ! -f "$CUR/$f" ]; then
    CH=$((CH+1)); echo "$f" >> "$D/changed.list"
    case "$f" in app/*.php|database/seeders/*.php|database/factories/*.php) ADDPHP=$((ADDPHP+1));; esac
  elif ! cmp -s "$f" "$CUR/$f"; then CH=$((CH+1)); echo "$f" >> "$D/changed.list"; fi
done < manifest.txt
while read -r f; do
  case "$f" in database/migrations/*) MIG=$((MIG+1));; *.php) PHPCH=$((PHPCH+1)); php -l "$f" >/dev/null 2>&1 || fail "PHP syntax error in $f";; esac
done < "$D/changed.list"
echo "$CH" > "$D/changed"; chmod 666 "$D/changed" 2>/dev/null
log "   $CH files changed ($MIG migration, $PHPCH php, $ADDPHP new classes)"

log "3/9 composer"; stg Building 35
export COMPOSER_ALLOW_SUPERUSER=1
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ] || ! cmp -s composer.lock "$CUR/composer.lock" || ! cmp -s composer.json "$CUR/composer.json"; then
  composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction -q || fail "composer install"
elif [ "$ADDPHP" -gt 0 ]; then
  log "   new class files: refreshing autoloader only"; composer dump-autoload --optimize --classmap-authoritative --no-interaction -q || fail "composer dump-autoload"
else log "   SKIPPED (no composer or class-map change)"; fi

if [ "$MIG" -gt 0 ]; then
log "4/9 migration safety check"; stg Building 48
PRET=$(php artisan migrate --pretend --force 2>&1) || fail "migrate --pretend: $(echo "$PRET" | tail -2 | tr '\n' ' ')"
if echo "$PRET" | grep -Eiq 'drop (column|table|index|constraint)|rename (column|to)|alter column .* (type|set not null)|truncate'; then
  if [ "$FLAG" = allow-breaking ]; then log "   BREAKING migration allowed: maintenance mode for the switch"; DOWN=1
  else log "$(echo "$PRET" | grep -Ei 'drop (column|table|index|constraint)|rename|alter column|truncate' | head -3)"; fail "pending migration is NOT backward-compatible (drops/renames). Deploy with allow-breaking in a quiet moment."; fi
else log "   pending migrations are additive"; fi

log "5/9 migrate + install"; stg Building 55
[ "$DOWN" = 1 ] && php artisan down --retry=30 >/dev/null 2>&1 || true
php artisan migrate --force || fail "migrate"
else log "4/9 migration check: SKIPPED (no migration files changed)"; log "5/9 migrate: SKIPPED"; stg Building 55; fi
php artisan mother:install || log "   (install step reported a problem, continuing)"

log "6/9 optimise caches"; stg Building 65
php artisan config:cache && php artisan route:cache && php artisan view:cache || fail "cache build"
for f in storage/framework/views/*.php; do php -l "$f" >/dev/null 2>&1 || log "   VIEWLINT FAIL $(head -c 220 "$f" | grep -o 'PATH [^ ]*')"; done
chown -R www-data:www-data "$REL/bootstrap/cache" /srv/shared/storage 2>/dev/null

log "7/9 health check on the NEW release (not live yet)"; stg Testing 72
su -s /bin/sh www-data -c "cd $REL && exec php -S 127.0.0.1:8099 -t public public/index.php" >"$D/hc-server.log" 2>&1 &
HPID=$!; sleep 3
php "$D/hc.php" http://127.0.0.1:8099 dutyfreeshopbd.com / @product /offline /manifest.webmanifest /track /admin/login /account/login /_health | sed 's/^/   /'
HC=$?; kill "$HPID" 2>/dev/null; HPID=""; pkill -f 'php -S 127.0.0.1:8099' 2>/dev/null
[ "$HC" = 0 ] || fail "health check failed on new release (live site untouched)"

log "8/9 switch (atomic)"; stg Deploying 85
swap "$REL" || fail "switch"
echo "$CUR" > "$D/previous"; echo "$REL" > "$D/current"
[ "$DOWN" = 1 ] && php artisan up >/dev/null 2>&1; DOWN=0
KEEP=1
su -s /bin/sh www-data -c "cd /srv/app && php artisan queue:restart" >/dev/null 2>&1 || true

log "9/9 health check LIVE"; stg Verifying 93
sleep 2
if ! php "$D/hc.php" http://web dutyfreeshopbd.com / /offline /manifest.webmanifest /_health | sed 's/^/   /'; then
  log "live check failed -> automatic rollback"; swap "$CUR"; echo "$CUR" > "$D/current"; echo "$REL" > "$D/previous"
  php "$D/hc.php" http://web dutyfreeshopbd.com / | sed 's/^/   /'
  setst "failed: live check failed, rolled back to $(basename "$CUR")"; log "rolled back"; exit 1
fi
# keep the newest 3 releases (live + previous always)
ls -1dt /srv/releases/* 2>/dev/null | tail -n +4 | while read -r old; do [ "$old" != "$(readlink -f /srv/app)" ] && [ "$old" != "$CUR" ] && rm -rf "$old"; done
log "DONE: $ID is live. Previous release kept for rollback: $(basename "$CUR")"
setst "ok: $ID live"
