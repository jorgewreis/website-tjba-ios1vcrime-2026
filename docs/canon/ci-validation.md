---
id: ci-validation
title: "Validação CI para Canônicos"
version: "1.0"
date: "2026-02-21"
---

# Validação CI para Canônicos

Jobs sugeridos:

- `docs/validate-markdown`:
  - Verifica conformidade com `markdown-format-canon.md` (hierarquia de títulos, listas, blocos de código).
  - Checa frontmatter obrigatório (canonical, id, title, version).
  - Valida links internos e externos.

- `docs/validate-frontmatter`:
  - Garante presença dos campos mínimos em todos os canônicos.

Ferramentas recomendadas:

- markdownlint / markdownlint-cli
- a custom script to validate frontmatter (node/python)
- linkchecker or html-proofer for link validation

Resultado:

- Fail CI quando uma regra canônica de formatação não for cumprida.

