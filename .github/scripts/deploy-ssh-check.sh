#!/usr/bin/env bash
# Valida login SSH com a chave preparada em ~/.ssh/id_ed25519.
set -euo pipefail

ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  -o PreferredAuthentications=publickey \
  -o PubkeyAuthentication=yes \
  -o PasswordAuthentication=no \
  -o BatchMode=yes \
  -o StrictHostKeyChecking=yes \
  "${DEPLOY_USER}@${DEPLOY_HOST}" \
  "echo SSH_OK && whoami && pwd"
