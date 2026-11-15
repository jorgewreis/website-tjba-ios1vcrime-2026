---
id: testing-instructions
title: "Instruções de Testes e QA"
version: "1.0"
date: "2026-02-21"
---

# Testes e QA (instruções rápidas)

Passos para testes manuais (smoke tests) no ambiente dev:

1. Verificar deploy recente em dev.ios1vcrime.com.br.  
2. Login com usuário de teste (admin) e validar fluxo de autenticação.  
3. Navegar para módulos principais: Guias, Usuários, Audiências, Cartório e Gabinete.  
4. Validar sessão autenticada via endpoint auxiliar legado ainda ativo: `GET /admin/api/checkToken.php`.  
5. Testar pelo menos um endpoint oficial em `public/api/` consumido pela interface real do módulo alterado. Exemplos: `/api/cartorio-guias/list.php`, `/api/sala-audiencias-pauta/occupied-slots.php`, `/api/processos/classe-read.php?numero=...`.  
6. Quando houver mudança administrativa, validar também endpoints oficiais de `public/api/admin/`, como `/api/admin/feriados/list.php`.  
7. Não usar a árvore `api/` da raiz (`/api/guia.php`, `/api/notifications.php`, `/api/users.php`, `/api/documents.php`) como referência de smoke test canônico, pois ela não representa a superfície oficial atual.  

Testes automatizados (sugestão):

- PHP Unit: escrever testes para serviços em `app/services/` (PHPUnit).  
- PHPStan: análise estática para `app/` (nível 7 recomendado).  
- lint CSS/JS: usar stylelint / ESLint conforme necessidade.

## Sandbox isolado de autenticação

Os testes de login e recuperação usam somente o sandbox local em `tests/auth/`.
Eles nunca leem o `.env` raiz, usam contas `@auth-test.invalid` e capturam e-mail
no Mailpit local. Para iniciar, executar, e remover o ambiente:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth/bootstrap.ps1 -Start
powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth/run-auth-tests.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tests/auth/cleanup.ps1
```

MySQL, SMTP e a interface Mailpit são publicados somente em loopback. A rede
Docker é bridge para permitir que o PHP local alcance os serviços; ela não prova
isolamento de egress e não deve receber relay SMTP. Mailpit aceita apenas
destinatários sintéticos `@auth-test.invalid`.

O fluxo HTTP local prova CSRF, sessão, reset e logout, mas não é evidência de
cookie `Secure` em HTTPS, comportamento em iframe ou paridade com produção.

Relatar falhas em issue com passos para reproduzir, logs e URL do ambiente dev.

