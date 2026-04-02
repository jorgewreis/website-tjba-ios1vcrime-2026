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

echo "----- Teste TCP (porta 22) antes do ssh-keyscan -----"
TCP_STATUS="unknown"
if command -v nc >/dev/null 2>&1; then
  if nc -zw5 "${DEPLOY_HOST}" 22 >/dev/null 2>&1; then
    TCP_STATUS="open"
  else
    TCP_STATUS="closed_or_filtered (nc)"
  fi
elif timeout 5 bash -c "echo >/dev/tcp/${DEPLOY_HOST}/22" 2>/dev/null; then
  TCP_STATUS="open"
else
  TCP_STATUS="closed_or_filtered (bash /dev/tcp)"
fi
echo "TCP 22 para DEPLOY_HOST: ${TCP_STATUS}"

if [[ "$TCP_STATUS" == closed_or_filtered* ]]; then
  echo "::error::Porta 22 do DEPLOY_HOST está bloqueada (firewall DROP ou sem serviço SSH) — o runner do GitHub não consegue abrir TCP para o servidor. Abrir TCP 22 para conexões externas ou para as faixas de IP dos runners do GitHub."
  exit 1
fi
echo "------------------------------------------------------"

run_keyscan() {
  local extra=("$@")
  ssh-keyscan "${extra[@]}" -H -T 20 "${DEPLOY_HOST}" >"$KS_OUT" 2>/tmp/gha_keyscan.err
}

if run_keyscan; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "ssh-keyscan: host registrado em known_hosts."
elif run_keyscan -4; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "::notice::ssh-keyscan só funcionou com IPv4 (-4). Confira IPv6 ou use IP direto em DEPLOY_HOST."
  echo "ssh-keyscan: host registrado em known_hosts (IPv4)."
else
  KS_BYTES="$(wc -c < "$KS_OUT" | tr -d ' ')"
  KS_ERR_BYTES="$(wc -c < /tmp/gha_keyscan.err | tr -d ' ')"
  echo "::error::ssh-keyscan falhou (stdout ${KS_BYTES} bytes; stderr ${KS_ERR_BYTES} bytes)."
  if [ "${KS_ERR_BYTES}" -gt 0 ]; then
    echo "Stderr do keyscan:"
    cat /tmp/gha_keyscan.err
  else
    echo "::notice::Stderr vazio = timeout silencioso — firewall DROP na porta 22 a partir desta rede (GitHub Actions). Permitir TCP 22 de IPs externos (ou das faixas docs.github.com/en/actions/using-github-hosted-runners/about-github-hosted-runners#ip-addresses)."
  fi
  exit 1
fi
