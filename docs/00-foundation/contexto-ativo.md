---
title: "Contexto Ativo — Estado Atual"
status: draft
version: "0.1.0"
date: 2026-02-18
review_cycle: 3m
authors:
  - "Jorge Reis"
---

# Contexto Ativo — Estado Atual

## O que está sendo desenvolvido agora
- Realinhamento da documentação canônica do projeto: foi criado o `Documento Canônico do Projeto` (`docs/00-foundation/project-canonical-document.md`) para reunir decisões de stack, arquitetura e versionamento, servindo como referência prioritária para qualquer mudança subsequente.
- Reorganização da estrutura de scripts e helpers em `public/assets/js/` e `app/` para refletir as convenções definidas em `estrutura-repositorio.md`: utilitários compartilhados (mascaramento, validação, toasts) vivem em `public/assets/js/shared/form-utils.js`, enquanto cada módulo (por enquanto Guias) consome scripts específicos em `public/assets/js/modules/guias/`.
- Redistribuição das classes PHP (`Guias`, `ClasseManager`, `ProcessoManager`, `ReuManager`, `Produtividade`, helpers de auditoria e toast) da raiz `public/` para `app/services/` e `app/helpers/`, respeitando a separação de camadas e garantindo que a lógica fique dentro das pastas oficiais do `app/`.
- Implementação de `app/helpers/autoload.php` e `app/helpers/bootstrap.php` para padronizar o carregamento de helpers/services e a conexão ao banco de dados a partir dos entrypoints públicos (`old/public/*.php`).
- Atualização dos arquivos de infraestrutura (middleware, helpers) para obedecer à nova estrutura (mover `bd/*.php` para `app/middlewares/` e `app/helpers/`) e garantir compatibilidade com o bootstrap atual.

## O problema atual
- Há pouca documentação centralizada sobre o estado corrente do projeto, o que dificulta que qualquer novo artefato ou mudança respeite a stack híbrida (Next.js + PHP/MySQL) e as regras de versionamento já acordadas.
- Os scripts front-end estavam dispersos em arquivos com foco exclusivo em guias; o desafio é garantir que as mesmas funções sejam reutilizáveis em outras páginas sem replicação indevida.
- A infraestrutura PHP precisava estar alinhada à estrutura definida no guia para evitar ambiguidades ao integrar novas features (pasta `bd/` contradizia o padrão `app/`).

## O que já foi testado
- Nenhum teste automatizado foi executado (documentação/manual). As ações seguem o workflow de reorganização e verificação visual do conteúdo (verificação manual de arquivos e validações de lint existentes conforme `page-common.js` e `toast.js`).

## O que NÃO pode ser alterado
- A stack oficial (PHP 8.2, MySQL 8.0 com `utf8mb4`, Next.js + JS ES2022) e a forma como tokens JWT são armazenados (`sessionStorage`), conforme registrado no documento canônico.
- Estrutura de versionamento mantida (`VERSION`, `CHANGELOG.md`, `docs/90-releases/`), além das pastas sensíveis (`storage/` só com `.gitkeep`, `public/` para ativos públicos).
- Decisões de arquitetura documentadas (como o painel admin via iframe e a separação público/restrito) continuam sendo prioridade e constam do canônico de arquitetura e do novo documento de projeto.

## Arquivos impactados
- `docs/00-foundation/project-canonical-document.md`
- `docs/00-foundation/estrutura-repositorio.md`
- `docs/00-foundation/contexto-ativo.md`
- `docs/00-foundation/guia-estrutura-docs.md` (referência mencionada)
- `public/assets/js/shared/form-utils.js`
- `public/assets/js/modules/guias/allocations.js`
- `public/assets/js/modules/guias/create.js`
- `public/assets/js/modules/guias/edit.js`
- `public/assets/js/modules/guias/list.js`
- `public/assets/js/modules/guias/permissions.js`
- `public/assets/js/toast.js`
- `app/middlewares/AuthMiddleware.php`
- `app/helpers/Conexao.php`

Se precisar completar este contexto com mais detalhes (por exemplo: prioridades de demanda ou bloqueios específicos), me avise.
