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
  echo "::error::Autenticação SSH falhou (Permission denied / exit ${code}). O servidor não aceitou a chave privada do secret DEPLOY_KEY_B64 para o usuário indicado em DEPLOY_USER."
  echo "::notice::No servidor: coloque a linha \"Chave pública correspondente\" do step anterior em ~/.ssh/authorized_keys desse usuário; chmod 700 ~/.ssh e chmod 600 ~/.ssh/authorized_keys; dono dos arquivos deve ser o próprio DEPLOY_USER."
  echo "::notice::Confirme também se DEPLOY_USER no GitHub é exatamente o usuário Linux do SSH (ex.: usuário do site no painel), não root, salvo que seja esse o destino."
  exit "$code"
fi
