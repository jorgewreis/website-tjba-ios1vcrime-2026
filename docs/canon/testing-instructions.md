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

Relatar falhas em issue com passos para reproduzir, logs e URL do ambiente dev.

