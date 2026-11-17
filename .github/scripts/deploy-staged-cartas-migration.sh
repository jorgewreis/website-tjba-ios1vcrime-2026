#!/usr/bin/env bash
set -euo pipefail

for name in DEPLOY_USER DEPLOY_HOST DEPLOY_TARGET_PATH GITHUB_RUN_ID GITHUB_RUN_ATTEMPT; do
  if [[ -z "${!name:-}" ]]; then
    echo "::error::Variável obrigatória ausente: ${name}"
    exit 1
  fi
done

if [[ ! "$GITHUB_RUN_ID" =~ ^[0-9]+$ || ! "$GITHUB_RUN_ATTEMPT" =~ ^[0-9]+$ ]]; then
  echo "::error::Identificador inválido para staging remoto."
  exit 1
fi

remote="${DEPLOY_USER}@${DEPLOY_HOST}"
remote_stage="/tmp/fokuslaw-cartas-migration-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}"
ssh_args=(-i "$HOME/.ssh/id_ed25519" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes)

cleanup() {
  ssh "${ssh_args[@]}" "$remote" "rm -rf -- '$remote_stage'" >/dev/null 2>&1 || true
}
ssh "${ssh_args[@]}" "$remote" "umask 077 && mkdir -- '$remote_stage'"
trap cleanup EXIT
scp "${ssh_args[@]}" \
  scripts/migrate-cartas-expedidas-idempotency.php \
  "${remote}:${remote_stage}/migrate.php"
scp "${ssh_args[@]}" \
  database/migrations/20261116_add_cartas_expedidas_idempotency.sql \
  "${remote}:${remote_stage}/migration.sql"

printf -v project_root_q '%q' "$DEPLOY_TARGET_PATH"
printf -v migration_sql_q '%q' "$remote_stage/migration.sql"
printf -v migration_script_q '%q' "$remote_stage/migrate.php"
ssh "${ssh_args[@]}" "$remote" \
  "set -e; CARTAS_PROJECT_ROOT=${project_root_q} CARTAS_MIGRATION_SQL=${migration_sql_q} php ${migration_script_q}"
