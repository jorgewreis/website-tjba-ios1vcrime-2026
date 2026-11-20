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
else
  status=$?
  if [[ "$status" -eq 1 ]] && [[ ! -s "$tmp_file" ]] && [[ "$(wc -l < "$error_file")" -eq 1 ]] && grep -qxE 'no crontab for .+' "$error_file"; then
    : > "$tmp_file"
  else
    cat "$error_file" >&2
    exit 1
  fi
fi
# Cron interpreta % mesmo dentro de aspas e quebras de linha criariam novas
# entradas. Recusamos esses caminhos antes de escrever o crontab.
case "$ROOT$PHP_BIN" in
  *'%'*|*$'\n'*|*$'\r'*) echo 'Caminho incompatível com crontab.' >&2; exit 1 ;;
esac
quote_shell() {
  local escaped="${1//\'/\'\\\'\'}"
  printf "'%s'" "$escaped"
}
root_quoted="$(quote_shell "$ROOT")"
php_quoted="$(quote_shell "$PHP_BIN")"
script_quoted="$(quote_shell "$ROOT/scripts/purge-inactive-external-users.php")"
log_quoted="$(quote_shell "$ROOT/storage/logs/inactive-external-users.log")"
new_file="$(mktemp)"
line_file="$(mktemp)"
trap 'rm -f "$tmp_file" "$error_file" "$new_file" "$line_file"' EXIT
printf '0 3 * * * cd %s && %s %s >> %s 2>&1 %s\n' "$root_quoted" "$php_quoted" "$script_quoted" "$log_quoted" "$MARKER" > "$line_file"
# Substitua no mesmo local para conservar o escopo das variáveis do crontab.
if ! awk '
  BEGIN {
    sq = sprintf("%c", 39); dq = sprintf("%c", 34); bs = sprintf("%c", 92)
    single = sq "[^" sq "]*" sq "(" bs bs sq sq "[^" sq "]*" sq ")*"
    double = dq "[^" dq "$`" bs bs "]*" dq
    token = "(" single "|" double ")"
    expected = "^0 3 [*] [*] [*] cd " token " && " token " " token " >> " token " 2>&1 # ios1vcrime-inactive-external-users[[:space:]]*$"
  }
  FILENAME == ARGV[1] { line = $0; next }
  !/^[[:space:]]*#/ && /# ios1vcrime-inactive-external-users[[:space:]]*$/ {
    if (found || $0 !~ expected ||
        $0 !~ /\/scripts\/purge-inactive-external-users[.]php["\047] >> / ||
        $0 !~ /\/storage\/logs\/inactive-external-users[.]log["\047] 2>&1 # ios1vcrime-inactive-external-users[[:space:]]*$/) {
      invalid = 1; exit 1
    }
    print line; found = 1
    next
  }
  { print }
  END { if (!found && !invalid) print line }
' "$line_file" "$tmp_file" > "$new_file"; then
  echo 'Entrada duplicada ou com marcador em formato inesperado; crontab preservado.' >&2
  exit 1
fi
if ! cmp -s "$tmp_file" "$new_file"; then
  crontab "$new_file"
fi
