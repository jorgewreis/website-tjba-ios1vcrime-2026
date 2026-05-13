#!/bin/bash
# Opcional: no servidor Linux, clone na raiz do app (pastas public/, scripts/, config/ irmaas).
# Com dois niveis de pasta public (docroot em public/public/), os symlinks ficam no public
# externo: public/scripts -> ../scripts e public/config -> ../config.
# Cria symlinks em public/ para os diretorios versionados no repo, evitando copia duplicada
# que git pull nao atualiza.
#
# Uso (na raiz do repositorio, ex.: /home/.../apps):
#   bash scripts/link-public-overlays-to-repo.sh
#
# Se public/scripts ou public/config ja existirem como PASTA com conteudo, o script para
# (backup, remova e rode de novo) para nao apagar dados sem querer.

set -eu

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PUBLIC="$ROOT/public"

if [[ ! -d "$PUBLIC" ]]; then
  echo "Erro: nao existe $PUBLIC." >&2
  exit 1
fi

link_dir () {
  local name="$1"
  local source="$ROOT/$name"
  local target="$PUBLIC/$name"

  if [[ ! -d "$source" ]]; then
    echo "Aviso: nao existe $source — pulando $name." >&2
    return 0
  fi

  if [[ -L "$target" ]]; then
    echo "OK: $target ja e symlink -> $(readlink "$target")"
    return 0
  fi

  if [[ -d "$target" ]]; then
    if [[ -n "$(find "$target" -maxdepth 1 -mindepth 1 2>/dev/null | head -1)" ]]; then
      echo "Erro: $target existe e nao e symlink. Backup, remova a pasta e rode de novo." >&2
      printf '  Ex.: mv "%s" "%s.bak.manual"\n' "$target" "$target" >&2
      return 1
    fi
    rmdir "$target" 2>/dev/null || true
  fi

  mkdir -p "$PUBLIC"
  ln -sfn "../$name" "$target"
  echo "OK: $target -> ../$name"
}

link_dir scripts || exit 1
link_dir config || exit 1

echo "Pronto. git pull na raiz do repo mantem scripts/ e config/; public/ segue os symlinks."
