#!/bin/sh
# Regenerates manifest.sha (sha1 of every file listed in manifest.txt). deploy.sh uses it to download ONLY changed files.
cd "$(dirname "$0")/.." || exit 1
: > manifest.sha.tmp
while read -r f; do [ -z "$f" ] && continue; [ -f "overlay/$f" ] || { echo "missing overlay/$f" >&2; rm -f manifest.sha.tmp; exit 1; }; echo "$(sha1sum < "overlay/$f" | cut -d' ' -f1) $f" >> manifest.sha.tmp; done < manifest.txt
# every overlay file must be listed, or it never reaches the server (v1.235: TopSellers.php missing -> homepage 500)
(cd overlay && find . -type f | sed 's#^\./##' | sort) > manifest.all.tmp; sort manifest.txt > manifest.sorted.tmp
miss=$(comm -23 manifest.all.tmp manifest.sorted.tmp); rm -f manifest.all.tmp manifest.sorted.tmp
[ -z "$miss" ] || { echo "NOT IN manifest.txt:" >&2; echo "$miss" >&2; rm -f manifest.sha.tmp; exit 1; }
mv manifest.sha.tmp manifest.sha
