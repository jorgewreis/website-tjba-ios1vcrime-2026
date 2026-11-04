#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
php_bin="${1:-php}"
read -r dia hora minuto < <(TZ=America/Bahia date '+%u %H %M')
hora=$((10#$hora))
minuto=$((10#$minuto))

if (( dia <= 5 && hora >= 10 && hora < 15 )); then
    atrasos=(0 10 20 30 40 50)
elif (( dia <= 5 && hora >= 7 && hora < 18 )); then
    atrasos=(0 15 30 45)
elif (( minuto % 5 == 0 )); then
    atrasos=(0)
else
    exit 0
fi

cd "$root"
for atraso in "${atrasos[@]}"; do
    (
        if (( atraso > 0 )); then sleep "$atraso"; fi
        "$php_bin" "$root/scripts/processar-cartorio-tarefas-automacao.php"
    ) &
done
wait
