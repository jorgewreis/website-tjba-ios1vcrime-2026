#!/usr/bin/env bash
set -euo pipefail

VERSION_FILE="VERSION"
CHANGELOG_FILE="CHANGELOG.md"

git fetch --tags --prune >/dev/null 2>&1 || true

if [[ ! -f "$VERSION_FILE" ]]; then
  echo "ERROR: VERSION file not found"
  exit 1
fi

current="$(tr -d ' \n\r\t' < "$VERSION_FILE")"
if [[ ! "$current" =~ ^[0-9]{2}\.[0-9]{2}\.[0-9]{4}$ ]]; then
  echo "ERROR: VERSION must be in format YY.MM.PPPP (e.g., 26.00.0000). Found: $current"
  exit 1
fi

YY="${current:0:2}"
MM="${current:3:2}"
PPPP="${current:6:4}"

yearYY="$(date +%y)"

if [[ "$YY" != "$yearYY" ]]; then
  YY="$yearYY"
  MM="00"
  PPPP="0000"
fi

msg="$(git log -1 --pretty=%B | tr -d '\r')"

if echo "$msg" | grep -qi "\[BIG\]"; then
  mm_int=$((10#$MM + 1))
  MM="$(printf "%02d" "$mm_int")"
  PPPP="0000"
else
  p_int=$((10#$PPPP + 1))
  PPPP="$(printf "%04d" "$p_int")"
fi

new="${YY}.${MM}.${PPPP}"

if git rev-parse "v${new}" >/dev/null 2>&1 || git rev-parse "${new}" >/dev/null 2>&1; then
  echo "ERROR: tag for version already exists: v${new} or ${new}"
  exit 1
fi

echo "$new" > "$VERSION_FILE"

today="$(date +%Y-%m-%d)"
subject="$(git log -1 --pretty=%s | tr -d '\r')"

if [[ ! -f "$CHANGELOG_FILE" ]]; then
  printf "# Changelog\n\n" > "$CHANGELOG_FILE"
fi

printf "## %s — %s\n%s\n\n" "$new" "$today" "$subject" >> "$CHANGELOG_FILE"

git add "$VERSION_FILE" "$CHANGELOG_FILE"
git commit -m "chore(release): ${new}" >/dev/null

git tag "v${new}"
