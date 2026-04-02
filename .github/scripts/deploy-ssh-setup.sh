#!/usr/bin/env bash
# Decodifica DEPLOY_KEY_B64, valida chave e registra host em known_hosts.
set -euo pipefail

mkdir -p ~/.ssh
TMP_ERR="$(mktemp)"
trap 'rm -f "$TMP_ERR"' EXIT

if ! printf '%s' "${DEPLOY_KEY_B64}" | base64 -d > ~/.ssh/id_ed25519 2>"$TMP_ERR"; then
  echo "::error::Falha ao decodificar DEPLOY_KEY_B64 (não é Base64 válido ou pipe quebrou)."
  [ -s "$TMP_ERR" ] && cat "$TMP_ERR"
  exit 1
fi

chmod 600 ~/.ssh/id_ed25519
KEY_BYTES="$(wc -c < ~/.ssh/id_ed25519 | tr -d ' ')"
echo "Chave privada decodificada: ${KEY_BYTES} bytes"

if [ "${KEY_BYTES}" -lt 80 ]; then
  echo "::warning::Arquivo da chave muito pequeno — confira se DEPLOY_KEY_B64 é a chave completa (sem quebras extras no secret)."
fi

if ! ssh-keygen -lf ~/.ssh/id_ed25519 >/tmp/gha_ssh_kfl.txt 2>/tmp/gha_ssh_kfe.txt; then
  echo "::error::O conteúdo decodificado não é uma chave privada SSH reconhecida (ssh-keygen -lf falhou)."
  cat /tmp/gha_ssh_kfe.txt
  exit 1
fi

echo "Fingerprint da chave usada pelo Actions:"
cat /tmp/gha_ssh_kfl.txt

echo "Chave pública correspondente (uma linha; deve estar em ~/.ssh/authorized_keys do DEPLOY_USER no servidor):"
ssh-keygen -y -f ~/.ssh/id_ed25519

if ! ssh-keyscan -H "${DEPLOY_HOST}" >> ~/.ssh/known_hosts 2>/tmp/gha_keyscan.err; then
  echo "::error::ssh-keyscan falhou para DEPLOY_HOST (host inalcançável, DNS ou firewall)."
  cat /tmp/gha_keyscan.err
  exit 1
fi

echo "ssh-keyscan: host registrado em known_hosts."
