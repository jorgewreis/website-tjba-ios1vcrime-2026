#!/usr/bin/env bash
set -euo pipefail

# -----------------------------------------------------------------------------
# DEPRECATED
# -----------------------------------------------------------------------------
# Este script (bump_version.sh) foi substituído pelo fluxo oficial do projeto:
# - Hook: .git/hooks/post-commit (ajusta VERSION e gera/atualiza notas)
# - Script: scripts/bump-version.ps1 (motor de versionamento)
# - Script: scripts/update-draft-minor.ps1 (atualiza _draft_minor.md em patches)
#
# O projeto NÃO usa mais o formato YY.MM.PPPP.
# O padrão atual é:
# - V. 1.0          (release nova / patch=0 e minor=0)
# - V. 1.04         (minor com patch zerado)
# - V. 1.04.0123    (patch > 0)
#
# Objetivo desta deprecação: evitar divergência e commits/tags duplicados.
# -----------------------------------------------------------------------------

echo "DEPRECATED: bump_version.sh não deve mais ser usado."
echo "Use o fluxo oficial:"
echo "  - Commit normal (hook post-commit fará o bump automaticamente)"
echo "  - Ou rode: powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/bump-version.ps1 -CommitMessage \"...\" -Bump minor|major|patch"
echo
echo "Nenhuma alteração foi aplicada."
exit 0
