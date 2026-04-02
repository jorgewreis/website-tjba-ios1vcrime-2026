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

run_keyscan() {
  local extra=("$@")
  ssh-keyscan "${extra[@]}" -H -T 20 "${DEPLOY_HOST}" >"$KS_OUT" 2>/tmp/gha_keyscan.err
}

if run_keyscan; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "ssh-keyscan: host registrado em known_hosts."
elif run_keyscan -4; then
  cat "$KS_OUT" >> ~/.ssh/known_hosts
  echo "::notice::ssh-keyscan só funcionou com IPv4 (-4). Confira IPv6/rota ou use em DEPLOY_HOST o mesmo FQDN/IP que resolve para IPv4."
  echo "ssh-keyscan: host registrado em known_hosts (IPv4)."
else
  echo "::error::ssh-keyscan falhou para DEPLOY_HOST (porta 22 inacessível a partir do GitHub, timeout, ou DNS diferente do getent)."
  echo "Saída stderr:"
  cat /tmp/gha_keyscan.err || true
  if grep -qi 'refused\|Connection refused' /tmp/gha_keyscan.err 2>/dev/null; then
    echo "::notice::Possível causa: nada a escutar na porta 22 nesse destino, ou firewall a bloquear IPs dos runners do GitHub."
  fi
  if grep -qi 'timed out\|timeout\|No route' /tmp/gha_keyscan.err 2>/dev/null; then
    echo "::notice::Possível causa: firewall/security group a bloquear SSH de fora; permitir TCP 22 de origens públicas (ou faixas documentadas do GitHub Actions)."
  fi
  if grep -qi 'getaddrinfo\|Name or service not known' /tmp/gha_keyscan.err 2>/dev/null; then
    echo "::notice::Possível causa: nome em DEPLOY_HOST não resolve neste runner — confirme o secret e teste DNS público."
  fi
  exit 1
fi
