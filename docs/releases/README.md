# Notas de versao

Este diretorio concentra as notas de versao por MINOR e os resumos executivos por MAJOR.

## Estrutura

- `_draft_minor.md` (cumulativo, durante o desenvolvimento da proxima MINOR)
- `vX.YY.md` (arquivo final de MINOR)
- `vX.0.md` (arquivo final de MAJOR, resumo das MINORs do MAJOR)
- `KNOWN_ISSUES.md` (bugs conhecidos a corrigir em versoes futuras)

## Modelo de entrada no _draft_minor.md

Cada patch deve adicionar uma linha no formato:

```text
[Fixed][Dashboard] Corrige carregamento do CSS no ambiente X. (PR #123 / commit abc123) Impacto: Baixo
```

## Secoes e ordem

As entradas devem ficar agrupadas nesta ordem:

1. Added
1. Changed
1. Fixed
1. Security
1. Deprecated
1. Removed
1. Bugs conhecidos (para versao futura)
1. Proximas implementacoes (sugestoes)

Dentro de cada secao, manter ordenacao por escopo (alfabetico) ou por data (mais recente primeiro).

## Gatilho de release (MINOR)

Quando o bump for MINOR (manual ou pela regra de mais de 100 arquivos), o processo deve:

1. Gerar `docs/releases/vX.YY.md` com base no conteudo do `_draft_minor.md`.
1. Reiniciar o `_draft_minor.md` para o proximo ciclo.
1. Adicionar o link da nova versao no `CHANGELOG.md`.

## Agrupamento de patches (para reduzir repeticao)

Quando houver textos iguais ou muito semelhantes dentro da mesma secao (mesmo tipo + escopo + descricao),
o processo de automacao consolida em uma unica entrada, adicionando varios commits no mesmo item
(ex.: `commits abc123, def456`).
