#!/usr/bin/env bash
# Verifica secrets obrigatórios antes de SSH/rsync (sem imprimir valores).
set -euo pipefail

missing=""
[ -z "${DEPLOY_KEY_B64:-}" ] && missing="${missing} DEPLOY_KEY_B64"
[ -z "${DEPLOY_HOST:-}" ] && missing="${missing} DEPLOY_HOST"
[ -z "${DEPLOY_USER:-}" ] && missing="${missing} DEPLOY_USER"
[ -z "${DEPLOY_TARGET_PATH:-}" ] && missing="${missing} DEPLOY_TARGET_PATH"

if [ -n "$missing" ]; then
  echo "::error::Secrets vazios ou não configurados no GitHub:${missing}"
  echo "Configure em Settings → Secrets and variables → Actions (ambiente production, se aplicável)."
  exit 1
fi

echo "Preflight: todos os secrets obrigatórios estão definidos (não vazios)."
echo "Metadados seguros: comprimento Base64 da chave=${#DEPLOY_KEY_B64} caracteres; comprimento do caminho alvo=${#DEPLOY_TARGET_PATH}"
