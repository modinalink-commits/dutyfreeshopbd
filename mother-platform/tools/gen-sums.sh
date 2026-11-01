#!/bin/sh
# Regenerates manifest.sha (sha1 of every file listed in manifest.txt). deploy.sh uses it to download ONLY changed files.
cd "$(dirname "$0")/.." || exit 1
: > manifest.sha.tmp
while read -r f; do [ -z "$f" ] && continue; [ -f "overlay/$f" ] || { echo "missing overlay/$f" >&2; rm -f manifest.sha.tmp; exit 1; }; echo "$(sha1sum < "overlay/$f" | cut -d' ' -f1) $f" >> manifest.sha.tmp; done < manifest.txt
mv manifest.sha.tmp manifest.sha
