# Notas de versão

Este diretório concentra as notas de versão por MINOR e os resumos executivos por MAJOR.

## Estrutura

- `_draft_minor.md` (cumulativo, durante o desenvolvimento da próxima MINOR)
- `vX.YY.md` (arquivo final de MINOR)
- `vX.0.md` (arquivo final de MAJOR, resumo das MINORs do MAJOR)
- `KNOWN_ISSUES.md` (bugs conhecidos a corrigir em versões futuras)

## Modelo de entrada no _draft_minor.md

Cada patch deve adicionar uma linha no formato:

```text
[Fixed][Dashboard] Corrige carregamento do CSS no ambiente X. (PR #123 / commit abc123) Impacto: Baixo
```

## Seções e ordem

As entradas devem ficar agrupadas nesta ordem:

1. Added
1. Changed
1. Fixed
1. Security
1. Deprecated
1. Removed
1. Bugs conhecidos (para versão futura)
1. Próximas implementações (sugestões)

Dentro de cada seção, manter ordenação por escopo (alfabético) ou por data (mais recente primeiro).

## Gatilho de release (MINOR)

Quando o bump for MINOR (manual ou pela regra de mais de 100 arquivos), o processo deve:

1. Gerar `docs/90-releases/vX.YY.md` com base no conteúdo do `_draft_minor.md`.
1. Reiniciar o `_draft_minor.md` para o proximo ciclo.
1. Adicionar o link da nova versão no `CHANGELOG.md`.

## Agrupamento de patches (para reduzir repetição)

Quando houver textos iguais ou muito semelhantes dentro da mesma seção (mesmo tipo + escopo + descrição),
o processo de automação consolida em uma única entrada, adicionando vários commits no mesmo item
(ex.: `commits abc123, def456`).
