#!/usr/bin/env bash
set -euo pipefail

readonly API_BASE="https://developers.hostinger.com/api/vps/v1"
readonly FIREWALL_NAME="gha-deploy-${GITHUB_RUN_ID:-manual}-${GITHUB_RUN_ATTEMPT:-1}"
readonly VPS_ID="1053508"

if [[ -z "${HOSTINGER_API_TOKEN:-}" ]]; then
  echo "Secret HOSTINGER_API_TOKEN ausente." >&2
  exit 1
fi
if [[ -z "${GITHUB_OUTPUT:-}" ]]; then
  echo "GITHUB_OUTPUT ausente; execução fora do GitHub Actions não suportada." >&2
  exit 1
fi

runner_ip="$(curl --connect-timeout 10 --max-time 20 --fail --silent --show-error --ipv4 https://api.ipify.org)"
python3 - "$runner_ip" <<'PY'
import ipaddress
import sys

try:
    ipaddress.IPv4Address(sys.argv[1])
except ipaddress.AddressValueError:
    raise SystemExit("O IP público obtido do runner não é IPv4 válido.")
PY
runner_cidr="${runner_ip}/32"

api_request() {
  local method="$1"
  local path="$2"
  local body="${3:-}"
  local args=(--connect-timeout 10 --max-time 30 --fail-with-body --silent --show-error --request "$method" \
    --header "Authorization: Bearer ${HOSTINGER_API_TOKEN}" \
    --header "Accept: application/json" \
    --header "Content-Type: application/json")

  if [[ -n "$body" ]]; then
    args+=(--data "$body")
  fi
  curl "${args[@]}" "${API_BASE}${path}"
}

created="$(api_request POST /firewall "$(jq -cn --arg name "$FIREWALL_NAME" '{name:$name}')")"
firewall_id="$(jq -er '.data.id // .data.firewall_id // .id // .firewall_id' <<<"$created")"
if [[ ! "$firewall_id" =~ ^[0-9]+$ ]]; then
  echo "A API Hostinger não retornou um ID válido para o firewall temporário." >&2
  exit 1
fi

# Publique o ID imediatamente para que a etapa always() consiga remover o grupo
# mesmo se a criação das regras ou a ativação falhar.
printf 'firewall_id=%s\n' "$firewall_id" >> "$GITHUB_OUTPUT"

api_request POST "/firewall/${firewall_id}/rules" \
  "$(jq -cn --arg source "$runner_cidr" \
    '{protocol:"TCP",port:"22",source:"custom",source_detail:$source}')" >/dev/null
api_request POST "/firewall/${firewall_id}/rules" \
  '{"protocol":"TCP","port":"80","source":"any","source_detail":"any"}' >/dev/null
api_request POST "/firewall/${firewall_id}/rules" \
  '{"protocol":"TCP","port":"443","source":"any","source_detail":"any"}' >/dev/null
api_request POST "/firewall/${firewall_id}/activate/${VPS_ID}" >/dev/null

printf 'activated=true\n' >> "$GITHUB_OUTPUT"
echo "Firewall temporário ${firewall_id} ativado; SSH liberado somente para ${runner_cidr}, com HTTP/HTTPS preservados."
