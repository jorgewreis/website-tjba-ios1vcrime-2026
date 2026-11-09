---
id: pilot-instructions
title: "Instruções para Testes Piloto de Agentes"
version: "1.0"
date: "2026-02-21"
---

# Instruções para Testes Piloto

Objetivo: executar CodeReviewer + php-linter em um conjunto de arquivos representativos e documentar resultados.

Passos:

1. Selecionar 2-3 arquivos PHP relevantes de `app/`, `config/` ou `public/` como amostra.
2. Quando solicitado, usar `php -l` se PHP estiver disponível; PHPCS e PHPStan são opcionais e só devem ser usados se configurados e disponíveis.
3. Executar CodeReviewer.agent.md (manual): aplicar validações listadas.
4. Registrar diffs e observações em `pilot-results.md`.

Critérios de sucesso:

- Linters detectam problemas e CodeReviewer fornece sugestões acionáveis.
- Formatação automática não altera lógica.

