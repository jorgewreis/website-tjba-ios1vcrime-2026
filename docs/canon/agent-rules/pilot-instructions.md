---
id: pilot-instructions
title: "Instruções para Testes Piloto de Agentes"
version: "1.0"
date: "2026-02-21"
---

# Instruções para Testes Piloto

Objetivo: executar CodeReviewer + php-linter em um conjunto de arquivos representativos e documentar resultados.

Passos:

1. Selecionar 2-3 arquivos PHP do `src/` ou `app/` como amostra.
2. Rodar `phpcs --standard=PSR12` e `phpstan analyse` localmente.
3. Executar CodeReviewer.agent.md (manual): aplicar validações listadas.
4. Registrar diffs e observações em `pilot-results.md`.

Critérios de sucesso:

- Linters detectam problemas e CodeReviewer fornece sugestões acionáveis.
- Formatação automática não altera lógica.

