#!/usr/bin/env bash
# Normaliza DEPLOY_HOST e confirma resolução DNS no runner (mesma rede do ssh-keyscan).
set -euo pipefail

N=$(printf '%s' "${DEPLOY_HOST}" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
if [ -z "$N" ]; then
  echo "::error::DEPLOY_HOST ficou vazio após remover espaços e quebras de linha. Ajuste o secret no GitHub."
  exit 1
fi

if ! getent hosts "$N" >/dev/null 2>&1; then
  echo "::error::O host \"$N\" não resolve via DNS a partir do GitHub Actions (equivalente ao erro getaddrinfo: Name or service not known)."
  echo "Corrija o secret DEPLOY_HOST: use FQDN público (ex.: ios1vcrime.com.br), hostname com DNS na Internet, ou IP público do servidor. Evite nomes só de rede interna ou VPN."
  exit 1
fi

echo "DEPLOY_HOST_EFFECTIVE=$N" >> "$GITHUB_ENV"
echo "DEPLOY_HOST: DNS OK no runner para este alvo."
