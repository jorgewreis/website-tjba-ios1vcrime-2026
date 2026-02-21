---
id: ci-setup
title: "CI - Setup recomendado"
version: "1.0"
date: "2026-02-21"
---

# Configuração de CI recomendada

Jobs sugeridos:

- validate-markdown:
  - Executa markdownlint no repositório.
  - Valida frontmatter e títulos (um # por arquivo).

- php-lint-and-static:
  - Executa composer install.
  - Executa phpcs --standard=PSR12.
  - Executa phpstan analyse --level=7 (ou psalm).

- security-scan:
  - Executa composer audit (ou tool equivalente).

Observações:
- Armazenar segredos (como DB credentials) em GitHub Secrets e não no repositório.
- Preferir imagens oficiais nas Actions (php, node) e cache de dependências para velocidade.

