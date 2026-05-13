#!/bin/bash
# Instala no crontab do usuario atual: quinta-feira 18:00.
#
# Execute na RAIZ do repositorio (onde existem as pastas config/ e scripts/), NAO dentro de public/:
#   cd /home/ios1vcrime/htdocs/apps
#   bash scripts/install-cron-gabinete-reus-presos-revisao-email.sh
#
# Se o arquivo .sh tiver sido editado no Windows, converta para LF antes de subir ao servidor:
#   dos2unix scripts/install-cron-gabinete-reus-presos-revisao-email.sh

set -eu

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"

if [[ ! -f "$ROOT/config/config.php" ]]; then
  echo "Erro: raiz do projeto invalida (nao achei config/config.php em $ROOT)." >&2
  echo "Use a pasta do aplicativo acima de public/, por exemplo:" >&2
  echo "  cd /home/ios1vcrime/htdocs/apps && bash scripts/install-cron-gabinete-reus-presos-revisao-email.sh" >&2
  exit 1
fi

SCRIPT="$ROOT/scripts/send-gabinete-reus-presos-revisao-email.php"
PHP_BIN="${PHP_BIN:-$(command -v php)}"

if [[ ! -f "$SCRIPT" ]]; then
  echo "Erro: nao encontrado: $SCRIPT" >&2
  exit 1
fi
if [[ -z "$PHP_BIN" ]]; then
  echo "Erro: php nao esta no PATH. Defina PHP_BIN e execute de novo." >&2
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
