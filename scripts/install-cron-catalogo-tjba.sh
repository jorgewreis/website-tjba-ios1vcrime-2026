#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
php_bin="$(command -v php)"
cd "$root"
umask 022
mkdir -p storage/logs
# Rodar como usuário do site. Instalar o cron somente após a primeira carga funcionar.
"$php_bin" scripts/atualizar-catalogo-tjba.php
test -s storage/catalogo-tjba.sqlite
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
marker='# ios1vcrime-catalogo-tjba'
if previous="$(LC_ALL=C crontab -l 2>&1)"; then
    printf '%s\n' "$previous" | awk -v marker="$marker" 'index($0, marker) == 0' > "$tmp"
elif [[ "$previous" != 'no crontab for '* ]]; then
    printf 'Não foi possível ler o crontab existente.\n' >&2
    exit 1
fi
printf '17 3 1 * * cd "%s" && "%s" scripts/atualizar-catalogo-tjba.php >> storage/logs/catalogo-tjba.log 2>&1 %s\n' "$root" "$php_bin" "$marker" >> "$tmp"
crontab "$tmp"
