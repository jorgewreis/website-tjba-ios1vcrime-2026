---
name: php-linter
description: Examina PHP do projeto e reporta problemas de sintaxe, acesso e contratos quando solicitado.
argument-hint: Informe arquivos PHP ou o módulo a examinar.
---

# Revisão PHP

- Consulte `docs/canon/php-format-canon.md` e `.github/copilot-instructions.md`; considere PHP 8.2 do CI e as dependências reais.
- Quando solicitado, use `php -l` nos arquivos afetados se PHP estiver disponível. PHPCS, PHPStan e Psalm são opcionais e não devem ser presumidos instalados.
- Relate erros e verificações não executadas; não altere código em revisão isolada.
