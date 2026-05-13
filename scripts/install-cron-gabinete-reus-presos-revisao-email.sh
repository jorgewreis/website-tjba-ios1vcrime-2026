#!/bin/bash
# Instala no crontab: quinta-feira 18:00. Nao use: php este_arquivo.sh (use o .php ou bash).
#
# Dois niveis de pasta public (docroot em .../public/public/): pode rodar a partir de
# .../public/scripts ou .../public/public/scripts.
#
# Canonico:
#   cd /path/apps && bash scripts/install-cron-gabinete-reus-presos-revisao-email.sh
# Servidor:
#   cd /path/apps/public/scripts && bash install-cron-gabinete-reus-presos-revisao-email.sh

set -eu

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT="$(cd "$HERE/.." && pwd)"
if [[ "$(basename "$HERE")" == "scripts" && "$(basename "$PARENT")" == "public" ]]; then
  GP="$(cd "$PARENT/.." && pwd)"
  if [[ "$(basename "$PARENT")" == "public" && "$(basename "$GP")" == "public" ]]; then
    ROOT="$GP"
  else
    ROOT="$PARENT"
  fi
else
  ROOT="$PARENT"
fi

SCRIPT="$HERE/send-gabinete-reus-presos-revisao-email.php"
if [[ ! -f "$SCRIPT" ]]; then
  SCRIPT="$ROOT/scripts/send-gabinete-reus-presos-revisao-email.php"
fi
if [[ ! -f "$SCRIPT" ]]; then
  SCRIPT="$ROOT/public/scripts/send-gabinete-reus-presos-revisao-email.php"
fi

if [[ "$(basename "$HERE")" == "scripts" && "$(basename "$ROOT")" == "public" ]]; then
  echo "Aviso: scripts sob pasta public (um ou dois niveis)." >&2
fi

if [[ ! -f "$ROOT/config/config.php" ]]; then
  echo "Erro: nao achei config/config.php em $ROOT." >&2
  exit 1
fi
if [[ ! -f "$SCRIPT" ]]; then
  echo "Erro: nao encontrei send-gabinete-reus-presos-revisao-email.php" >&2
  exit 1
fi

PHP_BIN="${PHP_BIN:-$(command -v php)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "Erro: php nao esta no PATH." >&2
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

echo "OK: crontab atualizado (quinta 18:00) -> $SCRIPT"
echo "Log: $ROOT/storage/logs/reus-presos-revisao-cron.log"
