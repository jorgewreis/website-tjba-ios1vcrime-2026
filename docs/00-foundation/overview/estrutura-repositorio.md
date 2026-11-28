# Estrutura de Arquivos (Exemplos) — website-tjba-ios1vcrime-2026

Este documento define **nomes de arquivos padrão** e **exemplos de conteúdo** para cada pasta do repositório.
Objetivo: padronizar a organização, facilitar manutenção, revisão e automação (CI/CD, versões, release notes).

> Regra-mãe:
> - `app/` = lógica e camadas do sistema
> - `public/` = tudo que é entregue ao navegador
> - `storage/` = runtime (logs, uploads, backups) — não versionar conteúdo
> - `docs/` = documentação operacional e técnica do projeto
> - `scripts/` = automações (versionamento, release notes, checks)
> - `.github/` e `.githooks/` = governança de Git e pipeline


---

## Raiz do repositório (arquivos de governança)

- `README.md`  
  **Por quê:** porta de entrada do projeto (instalação, execução local, visão geral rápida, rotas principais).

- `LICENSE.md`  
  **Por quê:** estabelece termos de uso do repositório (mesmo em ambiente interno, é governança).

- `VERSION`  
  **Por quê:** fonte única para versão do sistema (usada pelos scripts/workflows).

- `CHANGELOG.md`  
  **Por quê:** histórico humano-legível do que mudou por versão (Major/Minor).

- `COMMITS.md`  
  **Por quê:** padronização de mensagens/escopo de commit e convenções (base para automação).

- `.gitignore`  
  **Por quê:** evita versionar runtime e segredos (storage/*, .env, logs, uploads etc).

- `.gitattributes`  
  **Por quê:** normaliza EOL, diffs, linguagens, e evita ruído em PR.

- `.markdownlint.json` / `.markdownlintignore`  
  **Por quê:** consistência de documentação em markdown (docs/ mais previsíveis).


---

## .config/

- `.config/app.paths.json` (exemplo recomendado)  
  **Por quê:** centralizar caminhos/padrões usados por scripts e CI (evita hardcode).  
  **Tipo:** JSON simples com roots e aliases do projeto.


---

## .github/

### .github/copilot-instructions.md
- **Por quê:** orienta padrões de código e estilo, reduz divergência no time/ferramenta.

### .github/workflows/

- `.github/workflows/version.yml`  
  **Por quê:** pipeline principal de versionamento/tags/releases.

- `.github/workflows/version-bump.yml`  
  **Por quê:** automatiza bump (Major/Minor/Patch) com validações e geração de notas.

- `.github/workflows/ci.yml` (exemplo recomendado)  
  **Por quê:** separar CI (lint/test/build) do processo de versionamento.  
  **Tipo:** workflow para rodar checks em PR/push.


---

## .githooks/

- `.githooks/commit-msg`  
  **Por quê:** validar padrão de commit (ex.: Conventional Commits) para suportar automação.

- `.githooks/prepare-commit-msg`  
  **Por quê:** pré-preencher mensagens com escopo/ID/branch (reduz erro humano).

- `.githooks/post-commit`  
  **Por quê:** gatilhos locais opcionais (ex.: atualizar draft minor automaticamente).

- `scripts/setup-githooks.ps1` (recomendado fora daqui)  
  **Por quê:** instalar hooks via `git config core.hooksPath .githooks` (sem isso, ninguém usa).


---

## .vscode/

- `.vscode/settings.json`  
  **Por quê:** padroniza editor (format, lint, arquivos, encoding, indent).  
  **Obs.:** deve ser enxuto e não impor preferências pessoais irrelevantes.


---

## app/

A pasta `app/` é a camada do sistema (MVC + serviços + segurança).

### app/controllers/

Arquivos de exemplo (orientados às páginas internas do projeto):
- `app/controllers/AuthController.php`  
  **Por quê:** login/logout, criação de sessão, validações.
- `app/controllers/DashboardController.php`  
  **Por quê:** painel interno (visão consolidada).
- `app/controllers/CartasExpedidasController.php`  
  **Por quê:** listagem/cadastro/consulta/fluxos de cartas expedidas.
- `app/controllers/CartasRecebidasController.php`  
  **Por quê:** fluxo equivalente para recebidas.
- `app/controllers/CejuscController.php`  
  **Por quê:** rotinas/consultas do CEJUSC (página dedicada).
- `app/controllers/EditaisController.php`  
  **Por quê:** gestão/consulta/publicação interna de editais.
- `app/controllers/GuiasController.php`  
  **Por quê:** guias/rotinas vinculadas (página interna).
- `app/controllers/OficiosCartorioController.php`  
  **Por quê:** operações de ofícios do cartório.
- `app/controllers/OficiosGabineteController.php`  
  **Por quê:** operações do gabinete.
- `app/controllers/ProdutividadeController.php`  
  **Por quê:** indicadores, relatórios e painéis de produtividade.

> Regra: Controller controla fluxo HTTP e delega regras para Services/Models.  
> Evite SQL direto em controller.

### app/models/

Arquivos de exemplo:
- `app/models/User.php`  
  **Por quê:** representação do usuário (perfil, status, hash, auditoria).
- `app/models/Perfil.php`  
  **Por quê:** perfis/roles (RBAC) e permissões.
- `app/models/Carta.php`  
  **Por quê:** entidade-base para cartas (campos comuns).
- `app/models/CartaExpedida.php` / `app/models/CartaRecebida.php`  
  **Por quê:** especializações com regras/campos específicos.
- `app/models/Oficio.php`  
  **Por quê:** entidade para ofícios (cartório/gabinete).
- `app/models/Edital.php`  
  **Por quê:** entidade de edital.
- `app/models/MedidaProtetiva.php`  
  **Por quê:** entidade e validações de dados.
- `app/models/LogEvento.php`  
  **Por quê:** auditoria (quem fez o quê e quando).

> Regra: Model concentra regras de domínio e validações estruturais.  
> Persistência pode ficar em Repository/DAO (se você adotar).

### app/middlewares/

Arquivos de exemplo:
- `app/middlewares/AuthMiddleware.php`  
  **Por quê:** bloqueia área interna sem sessão válida.
- `app/middlewares/CsrfMiddleware.php`  
  **Por quê:** proteção contra CSRF em formulários.
- `app/middlewares/RbacMiddleware.php`  
  **Por quê:** autorização por perfil/permissão (ex.: acesso a módulos).
- `app/middlewares/RateLimitMiddleware.php`  
  **Por quê:** reduzir brute force e abusos em rotas sensíveis (login, reset).

### app/helpers/

Arquivos de exemplo:
- `app/helpers/Response.php`  
  **Por quê:** padroniza respostas JSON/redirect/erro.
- `app/helpers/Validator.php`  
  **Por quê:** validação reutilizável (strings, datas, números, CPF se houver).
- `app/helpers/Sanitizer.php`  
  **Por quê:** sanitização para evitar XSS/injeções.
- `app/helpers/DateHelper.php`  
  **Por quê:** manipulação consistente de datas e timezone.

### app/views/

Padrão: separar `internal/` e `public/`.

#### app/views/public/
Arquivos de exemplo:
- `app/views/public/home.php`  
  **Por quê:** página pública inicial (se existir).
- `app/views/public/login.php`  
  **Por quê:** tela de login.
- `app/views/public/recupera_senha.php`  
  **Por quê:** fluxo de recuperação.
- `app/views/public/partials/head.php`  
  **Por quê:** head padrão e imports globais.
- `app/views/public/partials/footer.php`  
  **Por quê:** rodapé padrão.

#### app/views/internal/
Arquivos de exemplo (espelhando seus módulos):
- `app/views/internal/dashboard.php`
- `app/views/internal/cartas_expedidas.php`
- `app/views/internal/cartas_recebidas.php`
- `app/views/internal/cejusc.php`
- `app/views/internal/editais.php`
- `app/views/internal/guias.php`
- `app/views/internal/oficios_cartorio.php`
- `app/views/internal/oficios_gabinete.php`
- `app/views/internal/produtividade.php`

Parciais recomendadas:
- `app/views/internal/partials/menu.php`  
  **Por quê:** menu unificado com RBAC (mostra/esconde módulos).
- `app/views/internal/partials/header.php`  
  **Por quê:** topo e breadcrumbs.
- `app/views/internal/partials/flash.php`  
  **Por quê:** mensagens de sucesso/erro padrão.
- `app/views/internal/partials/pagination.php`  
  **Por quê:** paginação padrão e consistente.

> Regra: Views não devem conter SQL nem lógica de permissão complexa.
> Permissões são resolvidas antes (middleware/controller).


---

## bootstrap/

- `bootstrap/app.php`  
  **Por quê:** ponto central de inicialização (config, timezone, autoload, container).
- `bootstrap/routes.php`  
  **Por quê:** mapa de rotas públicas e internas em um só lugar.
- `bootstrap/session.php`  
  **Por quê:** inicialização segura de sessão (cookie flags, lifetime, etc).


---

## config/

- `config/app.php`  
  **Por quê:** configurações gerais (timezone, locale, modo debug).
- `config/auth.php`  
  **Por quê:** parâmetros de autenticação (política de senha, expiração, lockout).
- `config/rbac.php`  
  **Por quê:** mapa de perfis e permissões por módulo.
- `config/logging.php`  
  **Por quê:** níveis e destinos de log (app/security/jobs).
- `config/database.php`  
  **Por quê:** configuração de conexão (sem credenciais hardcoded; usar env).

> Regra: `config/` é versionável; segredos vêm do `.env`.


---

## database/

- `database/migrations/2026_02_01_000001_create_users_table.php`  
  **Por quê:** versionar estrutura do banco com histórico rastreável.
- `database/migrations/2026_02_01_000002_create_perfis_table.php`
- `database/migrations/2026_02_01_000003_create_cartas_table.php`
- `database/migrations/2026_02_01_000004_create_oficios_table.php`
- `database/migrations/2026_02_01_000005_create_audit_logs_table.php`

- `database/seeds/SeedPerfis.php`  
  **Por quê:** perfis padrão e permissões iniciais.
- `database/seeds/SeedUsersAdmin.php`  
  **Por quê:** usuário admin inicial (com cuidado; sem senha fixa em texto).

- `database/schema.sql` (opcional)  
  **Por quê:** export mínimo de estrutura para referência rápida (sem dados).


---

## public/

### public/index.php
- **Por quê:** front controller (entra tudo por aqui), aplica bootstrap e roteamento.

### public/assets/css/
- `public/assets/css/reset.css`
- `public/assets/css/color.css`
- `public/assets/css/pages.css`
- `public/assets/css/form.css`
- `public/assets/css/components.css` (recomendado)
- `public/assets/css/layout.css` (recomendado)
- `public/assets/css/login.css` (separar estilos de login)

**Por quê:** manter CSS modular por responsabilidade (base, layout, componentes, páginas).

### public/assets/js/
- `public/assets/js/common.js`  
  **Por quê:** utilitários globais (menu, mensagens, helpers).
- `public/assets/js/auth.js`  
  **Por quê:** login/logout, validações front.
- `public/assets/js/menu.js`  
  **Por quê:** interação do menu interno.
- `public/assets/js/forms.js` (recomendado)  
  **Por quê:** máscaras/validações genéricas.

### public/assets/img/ e icons/
- `public/assets/icons/*.png`  
  **Por quê:** ícones padronizados e reutilizáveis.
- `public/assets/img/logo.png`  
  **Por quê:** identidade visual.

> Regra: não colocar PDF interno, dumps, backups ou arquivos sensíveis em `public/`.


---

## docs/

Você já tem uma boa estrutura. Exemplos de arquivos por área:

### docs/00-foundation/overview/
- `docs/00-foundation/overview/objetivos-e-publico-alvo.md`
- `docs/00-foundation/overview/funcionalidades-areas-publica-e-restrita.md`
- `docs/00-foundation/overview/estrutura-do-repositorio.md` (recomendado)

### docs/00-foundation/ (outros padrões)
- `docs/00-foundation/exportacao-listas-internas.md` — select CSV/PDF nas listagens internas (`_list_export_buttons.php` + `navigate-buttons.js`).

### docs/10-architecture/charter/
- `docs/10-architecture/charter/plano-de-gerenciamento-do-cronograma.md`
- `docs/10-architecture/charter/SAD.md`  
  **Por quê:** visão arquitetural e decisões macro.

### docs/10-architecture/adr/
- `docs/10-architecture/adr/0001-autenticacao-sessao.md`
- `docs/10-architecture/adr/0002-rbac-por-modulo.md`  
  **Por quê:** decisões rastreáveis ao longo do tempo.

### docs/20-modules/specs/
- `docs/20-modules/specs/modulo-cartas.md`
- `docs/20-modules/specs/modulo-oficios.md`
- `docs/20-modules/specs/modulo-produtividade.md`  
  **Por quê:** detalhar regras de cada módulo (campos, filtros, estados, permissões).

### docs/30-security/
- `docs/30-security/auth/politica-de-senhas.md`
- `docs/30-security/rbac/matriz-de-permissoes.md`
- `docs/30-security/audit-logging/eventos-auditoria.md`
- `docs/30-security/lgpd/classificacao-dados.md`  
  **Por quê:** segurança “documentada”, não só “implementada”.

### docs/40-operations/
- `docs/40-operations/deploy/guia-de-deploy.md`
- `docs/40-operations/backups/politica-de-backup.md`
- `docs/40-operations/monitoring/metricas-e-alertas.md`
- `docs/40-operations/troubleshooting/erros-comuns.md`

### docs/90-releases/
- `docs/90-releases/README.md`  
  **Por quê:** explica versionamento, tags, fluxo de release.
- `docs/90-releases/_draft_minor.md`  
  **Por quê:** acumulador de patches até fechar Minor.
- `docs/90-releases/changelog/v1.00.md`  
  **Por quê:** notas formais da versão “congelada”.
- `docs/90-releases/KNOWN_ISSUES.md`  
  **Por quê:** transparência e controle do que está pendente.


---

## scripts/

- `scripts/bump_version.sh`  
  **Por quê:** bump de versão em ambiente Linux/CI.
- `scripts/bump-version.ps1`  
  **Por quê:** bump para Windows (seu ambiente local).
- `scripts/update-draft-minor.ps1`  
  **Por quê:** automatiza escrita no `_draft_minor.md`.

Recomendados:
- `scripts/verify-repo.ps1`  
  **Por quê:** checar estrutura, permissões, arquivos obrigatórios, presença de `.env.example`.
- `scripts/lint-docs.ps1`  
  **Por quê:** garantir docs padronizados antes do merge.


---

## storage/  (runtime — não versionar conteúdo)

- `storage/logs/app/.gitkeep`  
- `storage/logs/security/.gitkeep`
- `storage/logs/jobs/.gitkeep`
- `storage/uploads/.gitkeep`
- `storage/backups/.gitkeep`

**Por quê:** manter pastas existindo no repo sem versionar arquivos gerados.

> Regra: tudo que for criado em produção (logs, uploads, backups) fica aqui e é ignorado pelo Git.


---

## old/  (legado)

Se você mantiver:
- `old/README.md`  
  **Por quê:** declarar explicitamente: "não usar no código novo", finalidade e data de congelamento.

Ideal:
- mover `old/` para branch `legacy/` e retirar do trunk (`main/develop`),
  evitando confusão e duplicidade de lógica/arquivos.


---

## Observações de padronização

1) `resourses/` deve ser renomeada para `resources/` para evitar dívida técnica.  
2) Rotas internas devem mapear 1:1 com controllers e views:
   - Ex.: `GET /internal/cartas-expedidas` → `CartasExpedidasController` → `app/views/internal/cartas_expedidas.php`  
3) Módulos sensíveis (produtividade, auditoria) devem gerar eventos em `LogEvento.php` e `storage/logs/security/`.

Fim.
