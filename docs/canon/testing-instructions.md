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
3. Navegar para: Guias, Notificações, Usuários, Configurações, Documentos.  
4. Testar criação de guia via API: POST /api/guia.php?action=create.  
5. Testar upload de documento em /documentos.  
6. Testar marcação de notificações via /api/notifications.php?action=mark_read.  

Testes automatizados (sugestão):

- PHP Unit: escrever testes para serviços em `app/services/` (PHPUnit).  
- PHPStan: análise estática para `app/` (nível 7 recomendado).  
- lint CSS/JS: usar stylelint / ESLint conforme necessidade.

Relatar falhas em issue com passos para reproduzir, logs e URL do ambiente dev.

