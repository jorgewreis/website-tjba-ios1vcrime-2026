#!/usr/bin/env bash
set -euo pipefail

readonly API_BASE="https://developers.hostinger.com/api/vps/v1"

if [[ -z "${HOSTINGER_API_TOKEN:-}" || ! "${FIREWALL_ID:-}" =~ ^[0-9]+$ ]]; then
  echo "Token ou ID do firewall temporário ausente/inválido; limpeza interrompida." >&2
  exit 1
fi

api_request() {
  local method="$1"
  local path="$2"
  curl --connect-timeout 10 --max-time 30 --fail-with-body --silent --show-error --request "$method" \
    --header "Authorization: Bearer ${HOSTINGER_API_TOKEN}" \
    --header "Accept: application/json" \
    "${API_BASE}${path}"
}

cleanup_failed=0
if [[ "${FIREWALL_ACTIVATED:-}" == "true" ]]; then
  if ! api_request POST "/firewall/${FIREWALL_ID}/deactivate/1053508" >/dev/null; then
    echo "Falha ao desativar o firewall temporário; tentando removê-lo (a API desativa grupos removidos)." >&2
    cleanup_failed=1
  fi
fi

if ! api_request DELETE "/firewall/${FIREWALL_ID}" >/dev/null; then
  echo "Falha ao excluir o firewall temporário ${FIREWALL_ID}." >&2
  cleanup_failed=1
fi

if (( cleanup_failed != 0 )); then
  exit 1
fi
echo "Firewall temporário ${FIREWALL_ID} removido."
