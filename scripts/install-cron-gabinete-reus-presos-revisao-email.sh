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
ERR="$(mktemp)"
FILTERED="$(mktemp)"
trap 'rm -f "$TMP" "$ERR" "$FILTERED"' EXIT
if LC_ALL=C crontab -l > "$TMP" 2> "$ERR"; then
  :
else
  status=$?
  # Somente a mensagem convencional de ausencia, sem outros erros, permite criar.
  if [[ "$status" -eq 1 ]] && [[ ! -s "$TMP" ]] && [[ "$(wc -l < "$ERR")" -eq 1 ]] && grep -qxE 'no crontab for .+' "$ERR"; then
    : > "$TMP"
  else
    cat "$ERR" >&2
    echo "Erro: nao foi possivel ler o crontab; nenhuma alteracao foi instalada." >&2
    exit 1
  fi
fi
# Remova apenas linhas com horário, executável e marcador do job gerenciado.
# Uma referência incidental ao marcador em outra tarefa deve ser preservada.
awk -v marker="$MARKER" '
  {
    line = $0
    sub(/[[:space:]]+$/, "", line)
    managed = line ~ /^0[[:space:]]+18[[:space:]]+\*[[:space:]]+\*[[:space:]]+4[[:space:]]+cd[[:space:]]/ \
      && line ~ /&&.*send-gabinete-reus-presos-revisao-email[.]php.*>>.*2>&1[[:space:]]/ \
      && index(line, "send-gabinete-reus-presos-revisao-email.php") < index(line, "#") \
      && length(line) > length(marker) \
      && substr(line, length(line)-length(marker), 1) ~ /[[:space:]]/ \
      && substr(line, length(line)-length(marker)+1) == marker
    if (!managed) print $0
  }
' "$TMP" > "$FILTERED"
cat "$FILTERED" > "$TMP"
echo "$LINE" >> "$TMP"
crontab "$TMP"

echo "OK: crontab atualizado (quinta 18:00) -> $SCRIPT"
echo "Log: $ROOT/storage/logs/reus-presos-revisao-cron.log"
