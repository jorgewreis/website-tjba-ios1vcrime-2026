#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="$(command -v php)"
MARKER='# ios1vcrime-inactive-external-users'
command -v crontab >/dev/null
mkdir -p "$ROOT/storage/logs"
tmp_file="$(mktemp)"
error_file="$(mktemp)"
trap 'rm -f "$tmp_file" "$error_file"' EXIT

# O crontab é lido e atualizado sem alterar outras tarefas existentes.
if LC_ALL=C crontab -l > "$tmp_file" 2> "$error_file"; then
  :
elif grep -qi 'no crontab for' "$error_file"; then
  : > "$tmp_file"
else
  cat "$error_file" >&2
  exit 1
fi
if grep -Fq "$MARKER" "$tmp_file"; then
  exit 0
fi
printf '0 3 * * * cd "%s" && "%s" "%s/scripts/purge-inactive-external-users.php" >> "%s/storage/logs/inactive-external-users.log" 2>&1 %s\n' "$ROOT" "$PHP_BIN" "$ROOT" "$ROOT" "$MARKER" >> "$tmp_file"
crontab "$tmp_file"
