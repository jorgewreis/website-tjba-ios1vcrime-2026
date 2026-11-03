#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
php_bin="$(command -v php)"
marker='# ios1vcrime-cartorio-tarefas-automacao'
log_dir="$root/storage/logs"
mkdir -p "$log_dir"
temp_file="$(mktemp)"
trap 'rm -f "$temp_file"' EXIT

(crontab -l 2>/dev/null || true) | grep -vF "$marker" > "$temp_file" || true
printf '* * * * * cd "%s" && "%s" "%s/scripts/processar-cartorio-tarefas-automacao.php" >> "%s/cartorio-tarefas-automacao.log" 2>&1 %s\n' "$root" "$php_bin" "$root" "$log_dir" "$marker" >> "$temp_file"
crontab "$temp_file"
crontab -l | grep -F "$marker" >/dev/null
printf 'Agendamento de tarefas instalado para execução a cada minuto.\n'
