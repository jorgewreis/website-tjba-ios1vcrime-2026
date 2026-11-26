#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="$(command -v php)"
mkdir -p "$ROOT/storage/logs"
CRON_FILE="$(mktemp)"
trap 'rm -f "$CRON_FILE"' EXIT
crontab -l 2>/dev/null | grep -vF '# ios1vcrime-processos-datajud-sync' > "$CRON_FILE" || true
printf '*/2 * * * * cd "%s" && "%s" scripts/processar-processos-datajud.php 20 >> storage/logs/processos-datajud-sync.log 2>&1 # ios1vcrime-processos-datajud-sync\n' "$ROOT" "$PHP_BIN" >> "$CRON_FILE"
crontab "$CRON_FILE"
