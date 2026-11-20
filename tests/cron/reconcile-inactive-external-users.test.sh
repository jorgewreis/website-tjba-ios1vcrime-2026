#!/usr/bin/env bash
set -euo pipefail
# Os dois executáveis são simulados; nenhuma chamada alcança o crontab real.
base="$(mktemp -d)"
trap 'rm -rf "$base"' EXIT
root="$base/projeto com 'aspas'"
mkdir -p "$root/scripts" "$base/bin-a" "$base/bin-b"
cp "$(dirname "$0")/../../scripts/install-cron-inactive-external-users.sh" "$root/scripts/"
cat > "$base/bin-a/crontab" <<'CRON'
#!/usr/bin/env bash
set -eu
if [[ "$1" == -l ]]; then
  case "${CRON_MODE:-ok}" in
    missing) echo 'no crontab for synthetic' >&2; exit 1 ;;
    fail) echo 'permission denied' >&2; exit 1 ;;
    ambiguous) printf 'no crontab for synthetic\npermission denied\n' >&2; exit 1 ;;
  esac
  cat "$CRON_STATE"
else
  cp "$1" "$CRON_STATE"
  printf 'write\n' >> "$CRON_WRITES"
fi
CRON
chmod +x "$base/bin-a/crontab"
cp "$base/bin-a/crontab" "$base/bin-b/crontab"
printf '#!/usr/bin/env bash\nprintf "%%s\n" "$PWD" > "$CRON_INVOKED"\n' > "$base/bin-a/php"
cp "$base/bin-a/php" "$base/bin-b/php"
chmod +x "$base/bin-a/php" "$base/bin-b/php"
export CRON_STATE="$base/state" CRON_WRITES="$base/writes" CRON_INVOKED="$base/invoked"
: > "$CRON_STATE"
: > "$CRON_WRITES"
installer="$root/scripts/install-cron-inactive-external-users.sh"
run_a() { PATH="$base/bin-a:$PATH" bash "$installer"; }
run_b() { PATH="$base/bin-b:$PATH" bash "$installer"; }
CRON_MODE=missing run_a
[[ "$(wc -l < "$CRON_WRITES")" -eq 1 ]]
[[ "$(grep -Fc '# ios1vcrime-inactive-external-users' "$CRON_STATE")" -eq 1 ]]
run_a
[[ "$(wc -l < "$CRON_WRITES")" -eq 1 ]]
printf '%s\n' 'ENVIRONMENT=second' '# comentário # ios1vcrime-inactive-external-users' '30 8 * * * echo preservada # outra-tarefa' >> "$CRON_STATE"
run_b
[[ "$(wc -l < "$CRON_WRITES")" -eq 2 ]]
grep -Fq "$base/bin-b/php" "$CRON_STATE"
grep -Fxq '# comentário # ios1vcrime-inactive-external-users' "$CRON_STATE"
grep -Fxq '30 8 * * * echo preservada # outra-tarefa' "$CRON_STATE"
[[ "$(head -n 2 "$CRON_STATE" | tail -n 1)" == 'ENVIRONMENT=second' ]]
[[ "$(grep -c '^0 3 .*# ios1vcrime-inactive-external-users$' "$CRON_STATE")" -eq 1 ]]
# A linha gerada é um comando shell válido mesmo com apóstrofo e espaços.
line="$(grep '^0 3 .*# ios1vcrime-inactive-external-users$' "$CRON_STATE")"
bash -n -c "${line#0 3 \* \* \* }"
bash -c "${line#0 3 \* \* \* }"
[[ "$(cat "$CRON_INVOKED")" == "$root" ]]
cp "$CRON_STATE" "$base/before"
if CRON_MODE=fail run_a 2> "$base/error"; then echo 'Leitura com falha deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
[[ "$(wc -l < "$CRON_WRITES")" -eq 2 ]]
grep -Fq 'permission denied' "$base/error"
if CRON_MODE=ambiguous run_a 2> "$base/error"; then echo 'Erro ambíguo deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
# Testa a mudança física do diretório do projeto e preserva a entrada alheia.
newroot="$base/projeto novo"
mv "$root" "$newroot"
installer="$newroot/scripts/install-cron-inactive-external-users.sh"
run_b
grep -Fq "$newroot" "$CRON_STATE"
! grep -Fq "$root" "$CRON_STATE"
grep -Fxq '30 8 * * * echo preservada # outra-tarefa' "$CRON_STATE"
[[ "$(grep -c '^0 3 .*# ios1vcrime-inactive-external-users$' "$CRON_STATE")" -eq 1 ]]
# Percentual é especial para cron e não pode entrar no comando gerado.
percentroot="$base/projeto%invalido"
mkdir -p "$percentroot/scripts"
cp "$installer" "$percentroot/scripts/"
installer="$percentroot/scripts/install-cron-inactive-external-users.sh"
cp "$CRON_STATE" "$base/before"
if run_a 2> "$base/error"; then echo 'Caminho com percentual deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
installer="$newroot/scripts/install-cron-inactive-external-users.sh"
printf '%s\n' '0 4 * * * echo outra # ios1vcrime-inactive-external-users' >> "$CRON_STATE"
cp "$CRON_STATE" "$base/before"
if run_a 2> "$base/error"; then echo 'Marcador ambíguo deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
grep -Fq 'formato inesperado' "$base/error"
# Mesmo horário e marcador não qualificam comando alheio como gerenciado.
cp "$base/before" "$CRON_STATE"
sed '$d' "$CRON_STATE" > "$base/clean"
cp "$base/clean" "$CRON_STATE"
printf '%s\n' '0 3 * * * cd /outra && echo alheio # ios1vcrime-inactive-external-users' >> "$CRON_STATE"
cp "$CRON_STATE" "$base/before"
if run_a 2> "$base/error"; then echo 'Comando alheio deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
# Contraprova: não aceitar comandos extras mesmo que os caminhos esperados apareçam.
: > "$CRON_STATE"
printf '%s\n' "0 3 * * * cd /outro && /bin/echo tarefa-alheia && '/usr/bin/php' '/x/scripts/purge-inactive-external-users.php' >> '/x/storage/logs/inactive-external-users.log' 2>&1 # ios1vcrime-inactive-external-users" >> "$CRON_STATE"
cp "$CRON_STATE" "$base/before"
if run_a 2> "$base/error"; then echo 'Comando extra deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
# Formato histórico com aspas duplas também é reconhecido.
printf '%s\n' '0 3 * * * cd "/old" && "/usr/bin/php" "/old/scripts/purge-inactive-external-users.php" >> "/old/storage/logs/inactive-external-users.log" 2>&1 # ios1vcrime-inactive-external-users' > "$CRON_STATE"
run_a
grep -Fq "$newroot" "$CRON_STATE"
# Duplicatas reais exigem intervenção: não apagar entradas automaticamente.
cp "$base/clean" "$CRON_STATE"
grep '^0 3 .*# ios1vcrime-inactive-external-users$' "$base/clean" >> "$CRON_STATE"
cp "$CRON_STATE" "$base/before"
if run_a 2> "$base/error"; then echo 'Marcador duplicado deveria falhar' >&2; exit 1; fi
cmp "$base/before" "$CRON_STATE"
grep -Fq 'duplicada' "$base/error"
echo 'OK: instalação, idempotência, caminhos, preservação e falha de leitura'
