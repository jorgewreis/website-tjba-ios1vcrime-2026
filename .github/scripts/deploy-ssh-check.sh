#!/usr/bin/env bash
# Testa login SSH com a chave já preparada em ~/.ssh/id_ed25519; mensagem clara se falhar.
set -uo pipefail

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
  echo "::group::Últimas linhas de ssh -vvv (evidência: qual chave foi oferecida / resposta do servidor)"
  set +e
  ssh -vvv -i ~/.ssh/id_ed25519 \
    -o IdentitiesOnly=yes \
    -o PreferredAuthentications=publickey \
    -o PubkeyAuthentication=yes \
    -o PasswordAuthentication=no \
    -o BatchMode=yes \
    -o StrictHostKeyChecking=yes \
    "${DEPLOY_USER}@${DEPLOY_HOST}" \
    "true" 2>&1 | tail -n 120
  set -e
  echo "::endgroup::"
  echo "::notice::No servidor: a linha completa de \"Chave pública correspondente\" (step Setup SSH) deve estar em ~/.ssh/authorized_keys do MESMO usuário Linux usado aqui; chmod 700 ~/.ssh e 600 ~/.ssh/authorized_keys."
  echo "::notice::Teste local: ssh -i <privada> USUARIO@HOST — se pedir senha ou negar, o problema é authorized_keys/usuário no servidor, não o GitHub."
  exit "$code"
fi
