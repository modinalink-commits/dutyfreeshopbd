#!/bin/sh
# Regenerates manifest.sha (sha1 of every file listed in manifest.txt). deploy.sh uses it to download ONLY changed files.
cd "$(dirname "$0")/.." || exit 1
# a glob line (e.g. public/adimg/pp-*.jpg) in manifest.txt is expanded to the real files, so the deploy never looks for a file literally named with a star
if grep -q '[*?[]' manifest.txt; then
  : > manifest.exp.tmp
  while read -r f; do case "$f" in *[\*\?\[]*) (cd overlay && ls -1 $f 2>/dev/null) >> manifest.exp.tmp ;; *) [ -n "$f" ] && echo "$f" >> manifest.exp.tmp ;; esac; done < manifest.txt
  awk '!s[$0]++' manifest.exp.tmp > manifest.txt; rm -f manifest.exp.tmp
fi
: > manifest.sha.tmp
while read -r f; do [ -z "$f" ] && continue; [ -f "overlay/$f" ] || { echo "missing overlay/$f" >&2; rm -f manifest.sha.tmp; exit 1; }; echo "$(sha1sum < "overlay/$f" | cut -d' ' -f1) $f" >> manifest.sha.tmp; done < manifest.txt
# every overlay file must be listed, or it never reaches the server (v1.235: TopSellers.php missing -> homepage 500)
(cd overlay && find . -type f | sed 's#^\./##' | sort) > manifest.all.tmp; sort manifest.txt > manifest.sorted.tmp
miss=$(comm -23 manifest.all.tmp manifest.sorted.tmp); rm -f manifest.all.tmp manifest.sorted.tmp
[ -z "$miss" ] || { echo "NOT IN manifest.txt:" >&2; echo "$miss" >&2; rm -f manifest.sha.tmp; exit 1; }
mv manifest.sha.tmp manifest.sha
