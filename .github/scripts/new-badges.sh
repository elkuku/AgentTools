#!/bin/bash
#
# Compares the badges of ingress.plus with .github/badges-known.tsv, and the pages of
# .github/badges-watch.tsv with what they answered so far. Writes what is new to issue.md, or
# no file when nothing is. The badges workflow turns it into one issue.
#
#   .github/scripts/new-badges.sh            check
#   .github/scripts/new-badges.sh --update   add the badges of today to the list
set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
KNOWN=$ROOT/.github/badges-known.tsv
WATCH=$ROOT/.github/badges-watch.tsv
API='https://ingress.plus/api/collections/badges/records?expand=category&perPage=500'
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

curl --silent --show-error --fail --location --max-time 60 --retry 2 "$API" -o "$TMP/badges.json"

# A page cut short must not look like badges that went away, nor a broken answer like none at all
total=$(jq '.totalItems' "$TMP/badges.json")
items=$(jq '.items | length' "$TMP/badges.json")
if [ "$total" != "$items" ] || [ "$items" -lt 300 ]; then
    echo "::error::ingress.plus gave $items of $total badges"
    exit 1
fi

# id<TAB>images<TAB>title: a badge with a new tier has more images
jq -r '.items[] | [.id, (.image | length), (.title | gsub("\\s+"; " ") | ltrimstr(" ") | rtrimstr(" "))] | @tsv' \
    "$TMP/badges.json" | LC_ALL=C sort > "$TMP/now.tsv"

if [ "${1:-}" = '--update' ]; then
    cp "$TMP/now.tsv" "$KNOWN"
    echo "$(wc -l < "$KNOWN") badges in .github/badges-known.tsv"
    exit 0
fi

LC_ALL=C comm -13 "$KNOWN" "$TMP/now.tsv" | cut -f1 > "$TMP/new.txt"

: > "$TMP/pages.md"
while IFS=$'\t' read -r url note; do
    [ -n "$url" ] || continue
    status=$(curl --silent --location --output /dev/null --max-time 30 --write-out '%{http_code}' "$url" || true)
    if [ "$status" = 200 ]; then
        echo "- [$url]($url): $note" >> "$TMP/pages.md"
    fi
done < "$WATCH"

[ -s "$TMP/new.txt" ] || [ -s "$TMP/pages.md" ] || exit 0

{
    if [ -s "$TMP/new.txt" ]; then
        echo 'ingress.plus has badges that are new or have new tiers:'
        echo
        echo '| Badge | Category | Tiers | Description |'
        echo '|---|---|---|---|'
        jq -r --rawfile new "$TMP/new.txt" '
            ($new | split("\n")) as $ids
            | .items[] | select(.id as $id | $ids | index($id))
            | "| \(.title | gsub("\\s+"; " ")) | \(.expand.category.title // "–") | \(.image | length) | \(.description // "–" | gsub("[|\\n]"; " ")) |"
            ' "$TMP/badges.json"
        echo
        echo 'Add the medals you want (the steps are in the developer guide), then run `.github/scripts/new-badges.sh --update` and commit the list. This issue closes on the next run.'
        echo
    fi
    if [ -s "$TMP/pages.md" ]; then
        echo 'These pages now exist:'
        echo
        cat "$TMP/pages.md"
        echo
        echo 'Do what the note says, then remove the line from `.github/badges-watch.tsv`.'
    fi
} > "$ROOT/issue.md"
