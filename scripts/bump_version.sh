#!/usr/bin/env bash
set -euo pipefail

VERSION_FILE="VERSION"
CHANGELOG_FILE="CHANGELOG.md"

die() { echo "ERROR: $*" >&2; exit 1; }

git fetch --tags --prune >/dev/null 2>&1 || true

[[ -f "$VERSION_FILE" ]] || die "Arquivo VERSION não encontrado na raiz do projeto."
current="$(tr -d ' \r\n\t' < "$VERSION_FILE")"
[[ "$current" =~ ^[0-9]{2}\.[0-9]{2}\.[0-9]{4}$ ]] || die "VERSION inválido. Use YY.MM.PPPP (ex.: 26.00.0000). Encontrado: $current"

YY="${current:0:2}"
MM="${current:3:2}"
PPPP="${current:6:4}"

yearYY="$(date +%y)"

if [[ "$YY" != "$yearYY" ]]; then
  YY="$yearYY"
  MM="00"
  PPPP="0000"
fi

msg="$(git log -1 --pretty=%B | tr -d '\r')"

if echo "$msg" | grep -qi "\[BIG\]"; then
  mm_int=$((10#$MM + 1))
  [[ $mm_int -le 99 ]] || die "MM estourou (>= 100). Ajuste a regra/intervalo."
  MM="$(printf "%02d" "$mm_int")"
  PPPP="0000"
else
  p_int=$((10#$PPPP + 1))
  [[ $p_int -le 9999 ]] || die "PPPP estourou (>= 10000). Ajuste a regra/intervalo."
  PPPP="$(printf "%04d" "$p_int")"
fi

new="${YY}.${MM}.${PPPP}"

if git rev-parse -q --verify "refs/tags/v${new}" >/dev/null; then
  die "A tag v${new} já existe."
fi

printf "%s\n" "$new" > "$VERSION_FILE"

today="$(date +%Y-%m-%d)"
subject="$(git log -1 --pretty=%s | tr -d '\r')"

if [[ ! -f "$CHANGELOG_FILE" ]]; then
  printf "# Changelog\n\n" > "$CHANGELOG_FILE"
fi

printf "## %s — %s\n%s\n\n" "$new" "$today" "$subject" >> "$CHANGELOG_FILE"

git add "$VERSION_FILE" "$CHANGELOG_FILE"

if git diff --cached --quiet; then
  echo "Nada para commitar; VERSION/CHANGELOG não mudaram."
  exit 0
fi

git commit -m "chore(release): ${new}" >/dev/null
git tag "v${new}"

echo "$new"
