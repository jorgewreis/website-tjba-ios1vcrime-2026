#!/usr/bin/env bash
# Só executa no VPS de origem, com o dump privado. Nenhuma conexão com a instância ativa.
set -euo pipefail
umask 077
fail() { printf 'RESTORE NÃO CONFIRMADO. Código: %s\n' "$1" >&2; exit 1; }
[[ $# == 4 && $EUID != 0 ]] || fail ARGUMENTOS
id=$1; sha=$2; schema=$3; expected=$4
[[ "$id" =~ ^vara-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$ && "$sha" =~ ^[a-f0-9]{64}$     && "$schema" =~ ^[a-zA-Z0-9_][a-zA-Z0-9_-]*$ && ${#schema} -le 64 ]] || fail ARGUMENTOS
[[ "$expected" =~ ^[0-9]+(:[0-9]+){4}$ ]] || fail INVENTARIO_ESPERADO
[[ "${schema,,}" != mysql && "${schema,,}" != information_schema && "${schema,,}" != performance_schema     && "${schema,,}" != sys && "${schema,,}" != test ]] || fail SCHEMA_RESERVADO
backup_dir="${HOME:?}/.vara-db-backups"
backup="$backup_dir/$id.sql.gz"
[[ -d "$backup_dir" && ! -L "$backup_dir" && -O "$backup_dir" && $(stat -c %a -- "$backup_dir") == 700     && -f "$backup" && ! -L "$backup" && -O "$backup" && $(stat -c %a -- "$backup") == 600 ]] || fail BACKUP_PRIVADO
actual=$(sha256sum -- "$backup")
[[ "${actual%% *}" == "$sha" ]] || fail HASH
for dep in mariadb mariadbd mariadb-install-db gzip timeout; do
    command -v "$dep" >/dev/null || fail DEPENDENCIA
done
# Use uma área de trabalho com pelo menos 2 GiB livres além de três vezes o dump
# descomprimido. Não guarde uma cópia SQL em disco para medir o tamanho.
available=$(df -Pk /tmp | awk 'END{print $4}')
[[ "$available" =~ ^[0-9]+$ && "$available" -ge 2097152 ]] || fail ESPACO
sqlbytes=$(timeout 120s bash -o pipefail -c 'gzip -dc -- "$1" 2>/dev/null | wc -c' _ "$backup") || fail GZIP
[[ "$sqlbytes" =~ ^[0-9]+$ && "$available" -ge $((2097152 + 3*(sqlbytes/1024+1))) ]] || fail ESPACO
area=$(mktemp -d /tmp/vara-restore.XXXXXXXX)
pid=''
cleanup() {
    if [[ -n "$pid" ]]; then
        kill -TERM "$pid" 2>/dev/null || true
        wait "$pid" 2>/dev/null || true
    fi
    rm -rf -- "$area"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM

# Socket exclusivo dentro de diretório 0700; jamais usa porta/TCP, arquivos de configuração
# do servidor ou usuário de produção. O agendador não executa eventos do dump.
socket="$area/m.sock"
unset MYSQL_PWD MYSQL_HOST MYSQL_TCP_PORT MYSQL_UNIX_PORT
export MYSQL_TEST_LOGIN_FILE="$area/sem-login"
timeout 120s mariadb-install-db --no-defaults --datadir="$area/db" --skip-test-db     --auth-root-authentication-method=normal >"$area/install.log" 2>&1 || fail INIT
mariadbd --no-defaults --datadir="$area/db" --socket="$socket" --pid-file="$area/m.pid"     --skip-networking --skip-log-bin --event-scheduler=DISABLED --tmpdir="$area"     --innodb-buffer-pool-size=64M --max-connections=6 >"$area/server.log" 2>&1 &
pid=$!
client=(mariadb --no-defaults --protocol=SOCKET --socket="$socket" --user=root --connect-timeout=2 --batch --skip-column-names)
ready=false
for ((i=0; i<40; i++)); do
    kill -0 "$pid" 2>/dev/null || fail INICIO
    if [[ -S "$socket" ]] && "${client[@]}" -e 'SELECT 1' >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
[[ "$ready" == true && $(cat "$area/m.pid" 2>/dev/null) == "$pid" ]] || fail SOCKET
isolated=$("${client[@]}" -e 'SELECT @@skip_networking, @@event_scheduler, @@datadir' 2>"$area/check.log") || fail ISOLAMENTO
[[ "$isolated" == $'1\tDISABLED\t'"$area/db/" ]] || fail ISOLAMENTO
"${client[@]}" -e "CREATE DATABASE \`$schema\`" >"$area/create.log" 2>&1 || fail SCHEMA
# O cliente não usa --force: qualquer erro SQL impede o selo de restauração.
if ! gzip -dc -- "$backup" 2>"$area/gzip.log" | timeout 900s "${client[@]}" --binary-mode --database="$schema" >"$area/import.log" 2>&1; then
    fail IMPORTACAO
fi
inventory=$("${client[@]}" --database="$schema" -e "
    SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE';
    SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW';
    SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE();
    SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE();
    SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE();
    SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'
       AND TABLE_NAME IN ('processos','cartas_expedidas','cartas_expedidas_subtarefas');" 2>"$area/inventory.log") || fail INVENTARIO
mapfile -t n <<< "$inventory"
[[ ${#n[@]} == 6 && ${n[0]} =~ ^[0-9]+$ && ${n[1]} =~ ^[0-9]+$ && ${n[2]} =~ ^[0-9]+$     && ${n[3]} =~ ^[0-9]+$ && ${n[4]} =~ ^[0-9]+$ && ${n[5]} == 3 ]] || fail INVENTARIO
[[ "${n[0]}:${n[1]}:${n[2]}:${n[3]}:${n[4]}" == "$expected" ]] || fail INVENTARIO_DIVERGENTE
# Cada view precisa aceitar preparação/execução sem ler registros. Se faltarem
# os definidores na instância de teste, registrar a falha e não declarar sucesso.
"${client[@]}" --raw --database="$schema" -e "
    SELECT CONCAT('SELECT 1 FROM ', CHAR(96), REPLACE(TABLE_NAME, CHAR(96), REPEAT(CHAR(96), 2)), CHAR(96), ' LIMIT 0;')
    FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME
" >"$area/check-views.sql" 2>"$area/metadata.log" || fail METADADOS_VIEWS
"${client[@]}" --binary-mode --database="$schema" <"$area/check-views.sql" >/dev/null 2>"$area/views.log" || fail LEITURA_VIEWS
"${client[@]}" --database="$schema" -e '
    SELECT COUNT(*) FROM processos; SELECT COUNT(*) FROM cartas_expedidas;
    SELECT COUNT(*) FROM cartas_expedidas_subtarefas;
' >/dev/null 2>"$area/read.log" || fail LEITURA
printf 'RESTORE ISOLADO OK %s sha256=%s tabelas=%s views=%s gatilhos=%s rotinas=%s eventos=%s\n'     "$id" "$sha" "${n[0]}" "${n[1]}" "${n[2]}" "${n[3]}" "${n[4]}"
