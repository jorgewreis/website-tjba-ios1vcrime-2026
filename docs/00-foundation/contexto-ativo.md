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
- **Cartas Recebidas:** implementação do submódulo espelhando Cartas Expedidas (tabelas, repositórios, serviço, APIs, views list/create/edit/rules). Especificação em `docs/20-modules/specs/cartorio-cartas-recebidas.md`.
- Consolidar os ativos públicos dentro de `public/assets/{css,js,img,icons}` para obedecer à hierarquia descrita em `docs/00-foundation/estrutura-repositorio.md` (reset/variables/base/components/forms, módulos públicos, imagens/ícones com prefixos semânticos).
- Criar helpers JavaScript reutilizáveis sob `public/assets/js/shared/` (autenticação, validação de CPF, notificações e menu) e garantir que `public/index.php`, `public/home.php` e `public/recupera_senha.php` carreguem esses utilitários.
- Migrar a lógica PHP legada do diretório `old/` para as pastas canônicas (`app/models/`, `app/helpers/`, `app/services/`, `public/admin/api/`) e manter os entrypoints (`public/index.php`, `public/login.php`, `public/logout.php`, `public/frame.php`, `public/envia_mail.php`) apontando para os novos arquivos.
- Atualizar o Documento Canônico e o contexto ativo para registrar as decisões tomadas (stack, arquitetura, CSS/JS compartilhados e o uso de `assets/` como raíz de recursos públicos).

## O problema atual
- As páginas internas ainda dependem de scripts legados (menu, controle de seções) que precisam ser verificadas contra a nova implementação em `public/assets/js/shared/menu.js`.
- A camada de documentação e o README não estão totalmente em sincronia com a nova organização física das pastas e arquivos (assets, configs, scripts).
- O fluxo de autenticação da área interna usa sessão PHP, token persistido em hash no banco, timeout de inatividade e reset por link único; qualquer mudança precisa preservar a decisão registrada no canônico do projeto.

## O que já foi testado
- Realizadas inspeções manuais dos entrypoints `public/index.php`, `public/home.php` e `public/recupera_senha.php` para garantir que carreguem os estilos e scripts redistribuídos.
- Os arquivos JS compartilhados foram validados visualmente (sem testes automáticos) em um carregamento básico do browser para confirmar que `App.CPF`, `App.Auth` e `App.Menu` estão definidos antes dos handlers de DOM.
- O endurecimento de autenticação foi validado manualmente após deploy, incluindo login, sessão, troca de senha e fluxo de redefinição.

## O que NÃO pode ser alterado
- A stack oficial baseada em PHP, MySQL, HTML, CSS e JavaScript, além do modelo de autenticação por sessão PHP com token persistido em hash no banco, conforme documentado nos arquivos canônicos atuais.
- A governança de versionamento (arquivo `VERSION`, `CHANGELOG.md`, pastas `docs/90-releases/`) e o uso de `public/` exclusivamente para recursos expostos.
- As decisões de arquitetura já consolidadas (painel admin em iframe, separação de áreas públicas/restritas, IDs com prefixos semânticos).

## Arquivos impactados
- `docs/00-foundation/contexto-ativo.md`
- `docs/00-foundation/project-canonical-document.md`
- `docs/00-foundation/estrutura-repositorio.md`
- `README.md`
- `.gitignore`
- `public/index.php`
- `public/home.php`
- `public/frame.php`
- `public/login.php`
- `public/logout.php`
- `public/recupera_senha.php`
- `public/redefinir_senha.php`
- `public/envia_mail.php`
- `public/admin/api/checkToken.php`
- `public/admin/api/getPerfis.php`
- `app/models/Usuario.php`
- `app/helpers/Conection.php`
- `app/services/PasswordResetService.php`
- `app/services/LoginRateLimiter.php`
- `config/security.php`
- `config/config.php`
- `database/migrations/20260401_login_rate_limits.sql`
- `public/assets/css/shared/layout.css`
- `public/assets/css/shared/components.css`
- `public/assets/css/shared/forms.css`
- `public/assets/css/shared/color.css`
- `public/assets/css/modules/public/login.css`
- `public/assets/js/shared/auth.js`
- `public/assets/js/shared/cpf.js`
- `public/assets/js/shared/common.js`
- `public/assets/js/shared/menu.js`
- `public/assets/img/backgrounds/`
- `public/assets/icons/`
- `scripts/bump_version.sh`
- `database/estrutura.sql`

Se precisar completar este contexto com mais detalhes (por exemplo: prioridades de demanda ou bloqueios específicos), me avise.
