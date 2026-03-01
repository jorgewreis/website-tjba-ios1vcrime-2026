---
title: "Manifesto de Movimentação de Canônicos"
version: "1.0"
date: "2026-02-21"
---

# Manifesto de Movimentação de Canônicos

Arquivos canônicos identificados na raiz do repositório que recomendamos mover para `docs/canon/` preservando histórico git:

- global-canon.md
- markdown-format-canon.md
- php-format-canon.md
- html-format-canon.md
- js-format-canon.md
- css-format-canon.md
- api-canon.md
- architecture-canon.md
- db-canon.md
- doc-generation-procedure-canon.md
- governance-canon.md
- security-canon.md
- stack-canon.md
- requirements-canon.md
- onboarding-canon.md
- processes-canon.md
- ops-canon.md
- production-dev-documentation-canon.md
- technical-documentation-canon.md

Recomendação de comando (na raiz do repositório) para cada arquivo:

```bash
git mv global-canon.md docs/canon/global-canon.md
```

Observações:

- Antes de mover, atualizar referências relativas que apontam para os arquivos na raiz.
- Utilizar `git mv` para preservar histórico.
- Após mover, atualizar `docs/canon/INDEX.md` com os novos links relativos.

