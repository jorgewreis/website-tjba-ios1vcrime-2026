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

if ! run_keyscan && ! run_keyscan -4; then
  echo "::error::ssh-keyscan falhou — porta 22 inacessível, timeout de rede ou firewall a bloquear o runner do GitHub."
  [ -s /tmp/gha_keyscan.err ] && cat /tmp/gha_keyscan.err
  exit 1
fi

expected="$(printf '%s' "${DEPLOY_HOST_KEY_SHA256:-}" | tr -d '\r\n' | xargs)"
if [ -z "$expected" ]; then
  echo "::warning::DEPLOY_HOST_KEY_SHA256 não configurado; a identidade SSH ainda não está fixada."
  cat "$KS_OUT" >> ~/.ssh/known_hosts
else
  matched=0
  while IFS= read -r host_key; do
    [ -n "$host_key" ] || continue
    fingerprint="$(printf '%s\n' "$host_key" | ssh-keygen -lf - -E sha256 2>/dev/null | awk '{print $2}')" || continue
    if [ "$fingerprint" = "$expected" ]; then
      printf '%s\n' "$host_key" >> ~/.ssh/known_hosts
      matched=1
    fi
  done < "$KS_OUT"
  if [ "$matched" -ne 1 ]; then
    echo "::error::A chave SSH do servidor não corresponde à impressão digital configurada."
    exit 1
  fi
  echo "Identidade SSH validada pela impressão digital configurada."
fi
