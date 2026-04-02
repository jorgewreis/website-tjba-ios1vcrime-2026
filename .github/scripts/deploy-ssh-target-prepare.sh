#!/usr/bin/env bash
# Normaliza DEPLOY_HOST e DEPLOY_USER (CRLF/espaços do copiar/colar) e valida DNS do host.
set -euo pipefail

N=$(printf '%s' "${DEPLOY_HOST:-}" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
if [ -z "$N" ]; then
  echo "::error::DEPLOY_HOST ficou vazio após remover espaços e quebras de linha. Ajuste o secret no GitHub."
  exit 1
fi

if ! getent hosts "$N" >/dev/null 2>&1; then
  echo "::error::O host \"$N\" não resolve via DNS a partir do GitHub Actions (equivalente ao erro getaddrinfo: Name or service not known)."
  echo "Corrija o secret DEPLOY_HOST: use FQDN público, hostname com DNS na Internet, ou IP público do servidor."
  exit 1
fi

echo "DEPLOY_HOST_EFFECTIVE=$N" >> "$GITHUB_ENV"
echo "DEPLOY_HOST: DNS OK no runner para este alvo."

U=$(printf '%s' "${DEPLOY_USER:-}" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
if [ -z "$U" ]; then
  echo "::error::DEPLOY_USER ficou vazio após normalização. Remova espaços/quebras extras no secret no GitHub."
  exit 1
fi

if printf '%s' "$U" | grep -qE '[[:space:]@]'; then
  echo "::error::DEPLOY_USER contém espaço ou \"@\" após normalização — use só o nome do usuário Linux (ex.: clp_abc123), não \"user@host\"."
  exit 1
fi

echo "DEPLOY_USER_EFFECTIVE=$U" >> "$GITHUB_ENV"
echo "DEPLOY_USER: normalizado (sem CRLF/espaços nas pontas)."
