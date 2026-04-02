#!/usr/bin/env bash
# Decodifica DEPLOY_KEY_B64, valida chave e registra host em known_hosts.
set -euo pipefail

mkdir -p ~/.ssh
TMP_ERR="$(mktemp)"
KS_OUT="$(mktemp)"
trap 'rm -f "$TMP_ERR" "$KS_OUT"' EXIT

if ! printf '%s' "${DEPLOY_KEY_B64}" | base64 -d > ~/.ssh/id_ed25519 2>"$TMP_ERR"; then
  echo "::error::Falha ao decodificar DEPLOY_KEY_B64 (não é Base64 válido ou pipe quebrou)."
  [ -s "$TMP_ERR" ] && cat "$TMP_ERR"
  exit 1
fi

chmod 600 ~/.ssh/id_ed25519
KEY_BYTES="$(wc -c < ~/.ssh/id_ed25519 | tr -d ' ')"

if [ "${KEY_BYTES}" -lt 80 ]; then
  echo "::error::Arquivo da chave muito pequeno — DEPLOY_KEY_B64 pode estar truncado."
  exit 1
fi

if ! ssh-keygen -lf ~/.ssh/id_ed25519 >/tmp/gha_ssh_kfl.txt 2>/tmp/gha_ssh_kfe.txt; then
  echo "::error::Conteúdo decodificado não é uma chave privada SSH válida."
  cat /tmp/gha_ssh_kfe.txt
  exit 1
fi

echo "Fingerprint da chave de deploy:"
cat /tmp/gha_ssh_kfl.txt

run_keyscan() {
  local extra=("$@")
  ssh-keyscan "${extra[@]}" -H -T 20 "${DEPLOY_HOST}" >"$KS_OUT" 2>/tmp/gha_keyscan.err
}

if run_keyscan; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "ssh-keyscan: host registrado em known_hosts."
elif run_keyscan -4; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "ssh-keyscan: host registrado em known_hosts (IPv4)."
else
  echo "::error::ssh-keyscan falhou — porta 22 inacessível, timeout de rede ou firewall a bloquear o runner do GitHub."
  [ -s /tmp/gha_keyscan.err ] && cat /tmp/gha_keyscan.err
  exit 1
fi
