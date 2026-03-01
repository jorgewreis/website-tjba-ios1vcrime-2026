---
title: "Agentes e Subagentes — Guia"
version: "1.0"
date: "2026-02-21"
---

# Agentes e Subagentes — Guia de Uso

Este documento descreve a finalidade, convenções e templates para agentes e subagentes que automatizam validações, revisão e ações seguindo os canônicos do projeto.

Principais objetivos:

- Fornecer templates padronizados para novas definições de agentes.
- Explicar regras comportamentais obrigatórias (seguir `global-canon.md`).
- Mapear subagentes por linguagem (PHP, HTML, JS, CSS) e suas responsabilidades.

Estrutura recomendada:

- `.github/agents/` — agentes principais (CodeReviewer, Formatter, SecurityAuditor, Debug).
- `.github/agents/subagents/` — subagentes por linguagem (php-linter, html-render-check, js-static-analysis, css-style).
- `docs/canon/agent-rules/` — regras operacionais e checklists.

Leia também: [Índice de Canônicos](./INDEX.md) e [`markdown-format-canon.md`](../../markdown-format-canon.md).

