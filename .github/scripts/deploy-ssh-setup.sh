#!/usr/bin/env bash
# Decodifica DEPLOY_KEY_B64, valida chave e registra host em known_hosts.
set -euo pipefail

mkdir -p ~/.ssh
TMP_ERR="$(mktemp)"
KS_OUT="$(mktemp)"
trap 'rm -f "$TMP_ERR" "$KS_OUT"' EXIT

ufw_cleanup_hint() {
  if [[ "${RUNNER_PUBLIC_IP:-}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ && "${GITHUB_RUN_ID:-}" =~ ^[0-9]+$ && "${GITHUB_RUN_ATTEMPT:-}" =~ ^[0-9]+$ ]]; then
    echo "Se a regra UFW temporária foi adicionada, remova-a no VPS com: sudo ufw delete allow from ${RUNNER_PUBLIC_IP} to any port 22 proto tcp comment 'gha-deploy-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}'"
  fi
}

if ! printf '%s' "${DEPLOY_KEY_B64}" | base64 -d > ~/.ssh/id_ed25519 2>"$TMP_ERR"; then
  echo "::error::Falha ao decodificar DEPLOY_KEY_B64 (não é Base64 válido ou pipe quebrou)."
  [ -s "$TMP_ERR" ] && cat "$TMP_ERR"
  ufw_cleanup_hint
  exit 1
fi

chmod 600 ~/.ssh/id_ed25519
KEY_BYTES="$(wc -c < ~/.ssh/id_ed25519 | tr -d ' ')"

if [ "${KEY_BYTES}" -lt 80 ]; then
  echo "::error::Arquivo da chave muito pequeno — DEPLOY_KEY_B64 pode estar truncado."
  ufw_cleanup_hint
  exit 1
fi

if ! ssh-keygen -lf ~/.ssh/id_ed25519 >/tmp/gha_ssh_kfl.txt 2>/tmp/gha_ssh_kfe.txt; then
  echo "::error::Conteúdo decodificado não é uma chave privada SSH válida."
  cat /tmp/gha_ssh_kfe.txt
  ufw_cleanup_hint
  exit 1
fi

echo "Fingerprint da chave de deploy:"
cat /tmp/gha_ssh_kfl.txt

run_keyscan() {
  ssh-keyscan -4 -H -T 5 "${DEPLOY_HOST}" >"$KS_OUT" 2>/tmp/gha_keyscan.err
}

keyscan_attempt=0
keyscan_max_attempts=24
while ! run_keyscan; do
  keyscan_attempt=$((keyscan_attempt + 1))
  if (( keyscan_attempt >= keyscan_max_attempts )); then
    echo "::error::SSH continuou inacessível após $((keyscan_max_attempts * 10)) segundos. Confirme a regra UFW temporária indicada no resumo do job."
    ufw_cleanup_hint
    [ -s /tmp/gha_keyscan.err ] && cat /tmp/gha_keyscan.err
    exit 1
  fi
  echo "::notice::Aguardando liberação UFW do runner (${keyscan_attempt}/${keyscan_max_attempts}); próxima tentativa em 5s."
  sleep 5
done

if [ ! -s "$KS_OUT" ]; then
  echo "::error::ssh-keyscan terminou sem chaves de host."
  [ -s /tmp/gha_keyscan.err ] && cat /tmp/gha_keyscan.err
  ufw_cleanup_hint
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
    ufw_cleanup_hint
    exit 1
  fi
  echo "Identidade SSH validada pela impressão digital configurada."
fi
