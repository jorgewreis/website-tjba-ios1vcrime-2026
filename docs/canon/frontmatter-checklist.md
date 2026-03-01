---
id: frontmatter-checklist
title: "Padronização de Frontmatter"
version: "1.0"
date: "2026-02-21"
---

# Padronização de Frontmatter

Campos mínimos obrigatórios para todos os canônicos:

- `canonical: true`
- `id`
- `title`
- `scope`
- `version`
- `date`
- `inherits_from` (quando aplicável)

Passos sugeridos:

1. Inspecionar cada arquivo listado em `move-manifest.md`.
2. Adicionar/normalizar frontmatter conforme campos mínimos.
3. Validar com script (ver `ci-validation.md`).

Arquivos verificados rapidamente (exemplos):

- `global-canon.md` — contém frontmatter completo.
- `markdown-format-canon.md` — contém frontmatter completo.
- `php-format-canon.md` — contém frontmatter completo.

Para automatizar:

- Escrever um script (node/python) que parseie YAML frontmatter e verifique campos.

