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

O procedimento e o estado atual estão em `tests/auth/README.md` e
`docs/90-releases/rei-93-auth-sandbox-evidence.md`. O caminho positivo está
suspenso: não execute os testes, o runner, preparação de dependências ou
limpeza até revisão independente comprovar identidade/procedência/isolamento
Docker e a investigação histórica identificar destino e efeitos, ou registrar
que os logs necessários não existem mais. A presença do runner, configuração
sintética, imagens fixadas ou cópias temporárias não libera a execução. Não use
entrypoints legados nem o Compose histórico para contornar o bloqueio.

Depois da liberação documentada, atualize este procedimento com o comando e os
limites validados. Evidência de cookies `Secure` em HTTPS, comportamento em
iframe e paridade com produção exige testes próprios.

Relatar falhas em issue com passos para reproduzir, logs e URL do ambiente dev.

