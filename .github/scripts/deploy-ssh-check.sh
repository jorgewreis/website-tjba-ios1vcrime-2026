#!/usr/bin/env bash
# Testa login SSH com a chave já preparada em ~/.ssh/id_ed25519; mensagem clara se falhar.
set -uo pipefail

echo "Fingerprint da chave usada neste teste (compare com ssh-keygen -lf no servidor):"
ssh-keygen -lf ~/.ssh/id_ed25519 || true

set +e
ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  -o PreferredAuthentications=publickey \
  -o PubkeyAuthentication=yes \
  -o PasswordAuthentication=no \
  -o BatchMode=yes \
  -o StrictHostKeyChecking=yes \
  "${DEPLOY_USER}@${DEPLOY_HOST}" \
  "echo SSH_OK && whoami && pwd"
code=$?
set -e

if [ "$code" -ne 0 ]; then
  echo "::error::Autenticação SSH falhou (Permission denied / exit ${code}). O servidor não aceitou esta chave para este usuário@host."
  echo "::notice::Se o resumo abaixo mostra \"Offering public key\" e em seguida de novo \"Authentications that can continue: publickey\", o sshd recusou essa chave — ela não está (ou não bate) em ~/.ssh/authorized_keys do DEPLOY_USER no servidor."

  echo "----- Cole esta linha inteira em ~/.ssh/authorized_keys do usuário de deploy no servidor -----"
  ssh-keygen -y -f ~/.ssh/id_ed25519
  echo "-----------------------------------------------------------------------------------------------"

  VLOG="$(mktemp)"
  trap 'rm -f "$VLOG"' EXIT

  set +e
  ssh -vvv -i ~/.ssh/id_ed25519 \
    -o IdentitiesOnly=yes \
    -o PreferredAuthentications=publickey \
    -o PubkeyAuthentication=yes \
    -o PasswordAuthentication=no \
    -o BatchMode=yes \
    -o StrictHostKeyChecking=yes \
    "${DEPLOY_USER}@${DEPLOY_HOST}" \
    "true" >"$VLOG" 2>&1
  set -e

  echo "----- Resumo diagnóstico (ssh -vvv; também no grupo abaixo) -----"
  grep -E 'Connecting to |Authentications that can continue|Offering public key|Trying private key|Server accepts key|sign_and_send_pubkey|Permission denied|No more authentication|Failed publickey|publickey' "$VLOG" | tail -n 40 || true
  echo "------------------------------------------------------------------"

  echo "::group::Últimas 120 linhas completas de ssh -vvv"
  tail -n 120 "$VLOG"
  echo "::endgroup::"

  echo "::notice::No servidor: a linha completa \"Chave pública correspondente\" (step Setup SSH) em ~/.ssh/authorized_keys do MESMO usuário; chmod 700 ~/.ssh e 600 ~/.ssh/authorized_keys; dono = esse usuário."
  echo "::notice::Secret DEPLOY_USER = só login Linux, sem @. Teste local: ssh -i privada USUARIO@HOST"
  exit "$code"
fi
