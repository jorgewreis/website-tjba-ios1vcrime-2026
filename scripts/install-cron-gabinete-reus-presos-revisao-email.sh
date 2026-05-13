#!/usr/bin/env bash
# Instala no crontab do usuário atual uma linha: toda quinta-feira às 18:00.
# Uso (uma vez no servidor Linux):
#   bash scripts/install-cron-gabinete-reus-presos-revisao-email.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$ROOT/scripts/send-gabinete-reus-presos-revisao-email.php"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

if [[ ! -f "$SCRIPT" ]]; then
  echo "Erro: não encontrado: $SCRIPT" >&2
  exit 1
fi
if [[ -z "$PHP_BIN" ]]; then
  echo "Erro: php não está no PATH. Defina PHP_BIN=/caminho/do/php e execute de novo." >&2
  exit 1
fi

MARKER='#tjba-reus-presos-revisao-email'
LINE="0 18 * * 4 cd \"$ROOT\" && \"$PHP_BIN\" \"$SCRIPT\" >> \"$ROOT/storage/logs/reus-presos-revisao-cron.log\" 2>&1 $MARKER"

mkdir -p "$ROOT/storage/logs"

TMP="$(mktemp)"
( crontab -l 2>/dev/null | grep -vF "$MARKER" || true ) > "$TMP"
echo "$LINE" >> "$TMP"
crontab "$TMP"
rm -f "$TMP"

echo "OK: crontab atualizado — quinta-feira 18:00 → $SCRIPT"
echo "Log: $ROOT/storage/logs/reus-presos-revisao-cron.log"
