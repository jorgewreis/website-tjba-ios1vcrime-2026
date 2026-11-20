#!/usr/bin/env bash
set -euo pipefail

if [[ ! "${RUNNER_PUBLIC_IP:-}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
  echo "IP público do runner ausente ou inválido; limpeza UFW interrompida." >&2
  exit 1
fi
if [[ ! "${GITHUB_RUN_ID:-}" =~ ^[0-9]+$ ]]; then
  echo "GITHUB_RUN_ID ausente ou inválido; limpeza UFW interrompida." >&2
  exit 1
fi
if [[ ! "${GITHUB_RUN_ATTEMPT:-}" =~ ^[0-9]+$ ]]; then
  echo "GITHUB_RUN_ATTEMPT ausente ou inválido; limpeza UFW interrompida." >&2
  exit 1
fi
if [[ -z "${DEPLOY_USER:-}" || -z "${DEPLOY_HOST:-}" ]]; then
  echo "Alvo SSH ausente; limpeza UFW interrompida." >&2
  exit 1
fi

if ! ssh -i ~/.ssh/id_ed25519 \
  -o IdentitiesOnly=yes \
  -o StrictHostKeyChecking=yes \
  "${DEPLOY_USER}@${DEPLOY_HOST}" \
  bash -s -- "$RUNNER_PUBLIC_IP" "gha-deploy-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}" <<'REMOTE'
set -euo pipefail
runner_ip="$1"
rule_comment="$2"
rule=(allow from "$runner_ip" to any port 22 proto tcp comment "$rule_comment")

if sudo -n ufw status | grep -Fq -- "$rule_comment"; then
  sudo -n ufw --dry-run --force delete "${rule[@]}" >/dev/null
  sudo -n ufw --force delete "${rule[@]}"
  echo "Regra UFW temporária removida: ${rule_comment} (${runner_ip}/32)."
else
  echo "Regra UFW temporária ${rule_comment} não estava presente."
fi

if sudo -n ufw status | grep -Fq -- "$rule_comment"; then
  echo "Regra UFW ${rule_comment} ainda aparece após a tentativa de remoção." >&2
  exit 1
fi
REMOTE
then
  echo "Não foi possível confirmar a remoção automática. Se a regra ainda existir, remova-a no Terminal do hPanel com: sudo ufw delete allow from ${RUNNER_PUBLIC_IP} to any port 22 proto tcp comment 'gha-deploy-${GITHUB_RUN_ID}-${GITHUB_RUN_ATTEMPT}'" >&2
  exit 1
fi
