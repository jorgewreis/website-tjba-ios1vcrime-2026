# Estrutura de Arquivos (Exemplos) — website-tjba-ios1vcrime-2026

Este documento define **nomes de arquivos padrão** e **exemplos de conteúdo** para cada pasta do repositório.
Objetivo: padronizar a organização, facilitar manutenção, revisão e automação (CI/CD, versões, release notes).

## 1. Objetivo e fundamentos

Além de servir como referência para pastas e arquivos, este guia deve ser consultado em conjunto com o `Documento Canônico do Projeto` (`docs/00-foundation/project-canonical-document.md`), que concentra decisões já tomadas e diretrizes de stack, arquitetura e versionamento. Qualquer alteração na estrutura aqui descrita precisa ser refletida nesse documento canônico e, quando aplicável, nos canônicos do domínio (governança e arquitetura).

## 2. Referências canônicas obrigatórias

- `docs/00-foundation/project-canonical-document.md`: decisão central do projeto (stack, arquitetura, restrições e versionamento).
- `docs/00-foundation/guia-estrutura-docs.md`: recomendações de organização da pasta `docs/`, nomenclaturas e formatos.
- `docs/00-foundation/technical-assumptions-constraints.md`: premissas e limites técnicos que condicionam esta estrutura.
- `docs/00-foundation/bloco-processo-listagem.md`: modelo visual e funcional do bloco de processo em listagens internas (partial, CSS, dados, cópia CNJ, tooltips).
- `docs/90-releases/README.md`: operação de releases (Patch/Minor/Major) e seus artefatos (`VERSION`, `CHANGELOG`, `_draft_minor.md`).

## 3. Regras gerais transversais

A nomenclatura principal segue `kebab-case` para arquivos Markdown e pastas (`docs/architecture/adr/session-policy.md`), `PascalCase` para classes PHP (controllers, models, helpers) e `snake_case` ou `kebab-case` para views e assets, conforme o contexto. Identificadores persistidos usam prefixo semântico (ex.: `USR-`, `PER-`) para facilitar auditoria e logs.

O controle de versão usa o trio `VERSION` (fonte única para scripts), `CHANGELOG.md` (registro cumulativo) e `docs/90-releases/` (draft e notas). Branches principais (`main`, `release`) são protegidas; quando houver branches temáticas, documente escopo, ticket e área impactada antes de qualquer merge (ver `docs/00-foundation/escopo-projeto.md`).

---

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

### `tools/`
- `tools/ai/` — scripts Python (`config.py`, `query.py`, `rag.py`, `ingest.py`) e `requirements.txt` relacionados à análise/elaboração de prompts ou automações.
- `tools/prompts/` — modelos de prompt (`modelo_prompt_*`) usados para interação com assistentes; mantê-los versionados aqui, não na raiz.

**Regra:** tudo que não é parte do deploy (front/back) deve ser organizado em `tools/`, mantendo a raiz enxuta.


---

## .config/

- `.config/builderio/data.json`  
  **Por quê:** configurações específicas de integração/geração de conteúdo (quando aplicável).  
  **Tipo:** JSON de configuração; deve ser versionável se não contiver segredos.

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
- `app/helpers/Helper.php`  
  **Por quê:** utilitários de domínio (IDs únicos, iniciais, transformações).
- `app/helpers/AuditLogger.php`  
  **Por quê:** registrar eventos operacionais e de auditoria com fallback para `error_log`.
- `app/helpers/ToastHelper.php`  
  **Por quê:** ponte entre o backend PHP e o sistema de toasts em `public/assets/js`.
- `app/helpers/autoload.php`  
  **Por quê:** autoload simples que carrega helpers e serviços via convenção de nome.
- `app/helpers/bootstrap.php`  
  **Por quê:** arranca sessão, autoload e conexão usada por entrypoints públicos (ex.: `public/index.php`).

### app/services/

- `app/services/ClasseManager.php`  
  **Por quê:** encapsula operações com `classes`, prepara IDs via `Helper`.
- `app/services/ProcessoManager.php`  
  **Por quê:** centraliza busca, cadastro e prioridades dos processos jurídicos.
- `app/services/ReuManager.php`  
  **Por quê:** gerencia réus, CPF e vínculos com processos (`processoReus`).
- `app/services/ProdutividadeService.php`  
  **Por quê:** regras, lançamentos e badges do módulo Produtividade interno (substitui o antigo `Produtividade.php`, removido por falta de uso).
- `app/services/Guias.php`  
  **Por quê:** orquestra cadastro de guias, serviços dependentes e auditoria (AuditLogger).
- `app/services/ProcessoClasseSyncService.php`  
  **Por quê:** leitura de processo/classe por CNJ e sincronização automática da classe após DataJud (`/api/processos/classe-read.php`, `/api/processos/classe-sync.php`). Documentação: `docs/00-foundation/datajud-classe-processo-bd-sync.md`.

Esses serviços devem ser consumidos por controllers e middlewares, mantendo a conexão com o banco por `app/helpers/Conexao.php`.

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
- `app/views/internal/cartorio-oficios-list.php`
- `app/views/internal/cartorio-oficios-edit.php`
- `app/views/internal/gabinete-oficios-list.php`
- `app/views/internal/gabinete-oficios-edit.php`
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

- `database/schema.sql`  
  **Por quê:** estrutura SQL **auxiliar e parcial** do banco, com tabelas de apoio, índices e views. Não substitui o banco completo nem as migrações incrementais.
- `database/triggers.sql`  
  **Por quê:** triggers de apoio versionados separadamente do schema auxiliar.
- `database/README-migracoes.md`  
  **Por quê:** inventário atual das migrações SQL e ordem sugerida por cenário.

Arquivos presentes hoje em `database/migrations/`:

- `database/migrations/usuarios_created_at_updated_at.sql`  
  **Por quê:** normaliza `created_at` e `updated_at` em `usuarios`.
- `database/migrations/20260322_seed_setores_inicial.sql`  
  **Por quê:** seed inicial de `setores`.
- `database/migrations/20260322_setores_codigo_area.sql`  
  **Por quê:** adiciona e preenche `setores.codigo_area`.
- `database/migrations/20260322_usuario_perfil_composite_limpeza.sql`  
  **Por quê:** apoio à transição para perfis compostos.
- `database/migrations/20260322_oficios_criado_por.sql`  
  **Por quê:** rastreabilidade de criação/edição em ofícios.
- `database/migrations/20260322_gabinete_reus_presos.sql`  
  **Por quê:** estrutura do módulo de réus presos.
- `database/migrations/guias.sql`  
  **Por quê:** estrutura do módulo de guias.
- `database/migrations/salario_minimo_e_valor_multa_guias.sql`  
  **Por quê:** apoio a cálculo de multa nas guias.
- `database/migrations/seed_salario_minimo_1994_2026.sql`  
  **Por quê:** carga histórica de salário mínimo.
- `database/migrations/20260330_processoPrioridades.sql`  
  **Por quê:** artefato legado/deprecado do modelo antigo de prioridades.
- `database/migrations/20260331_processo_prioridades_unificacao.sql`  
  **Por quê:** unifica prioridades processuais no modelo atual.
- `database/migrations/run_processo_prioridades_unificacao_data.php`  
  **Por quê:** migração assistida de dados do modelo legado de prioridades.
- `database/migrations/20260401_login_rate_limits.sql`  
  **Por quê:** cria a estrutura oficial de rate limit de login.


---

## public/

### public/index.php
- **Por quê:** front controller (entra tudo por aqui), aplica bootstrap e roteamento.

- ### public/assets/css/
- #### Arquivos compartilhados
- - `public/assets/css/shared/reset.css` — redefinição e reset global.
- - `public/assets/css/shared/color.css` — design system: cores, temas (claro/escuro/auto), variáveis de formulários e tipografia (Poppins).
- - `public/assets/css/shared/layout.css` — layout da home (shell: menu lateral, navbar, content wrapper, iframe).
- - `public/assets/css/shared/themes.css` — variações de tema (claro, escuro, feedback).
- - `public/assets/css/shared/components.css` — componentes reutilizáveis.
- - `public/assets/css/shared/forms.css` — formulários, inputs, mascaramento e helpers visuais.
- - `public/assets/css/shared/responsive.css` — regras adaptativas por breakpoint.
- - `public/assets/css/shared/toast.css` — estilos dos toasts flutuantes.
- - `public/assets/js/shared/toast.js` — fila, modelos simple/full, duracao e politica automatica (`App.Toast`).
- - `public/assets/js/shared/toast-helper.js` — `showToast` para iframes (postMessage ao parent).
- - `public/assets/css/shared/utilities.css` — helpers utilitários (mostrar/ocultar, espaçamentos).
- #### Estilos por módulo
- Cada módulo que precise de estilos específicos (por exemplo, `public/assets/css/modules/guias/create.css`) deve viver em `public/assets/css/modules/<nome-do-modulo>/` para evitar poluição global. Essa organização ajuda a manter apenas o necessário carregado nas páginas específicas de cada área.

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
- `public/assets/img/avatars/default.png`  
  **Por quê:** fallback consistente.

> Regra: não colocar PDF interno, dumps, backups ou arquivos sensíveis em `public/`.


---

## docs/

Você já tem uma boa estrutura. Exemplos de arquivos por área:

### docs/00-foundation/overview/
- `docs/00-foundation/overview/objetivos-e-publico-alvo.md`
- `docs/00-foundation/overview/funcionalidades-areas-publica-e-restrita.md`
- `docs/00-foundation/overview/estrutura-do-repositorio.md` (recomendado)

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
- `docs/40-operations/auth-security-runbook.md`

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

## Legado e decisões de migração

Não existe mais a pasta `old/` no trunk: todo código ativo está nas pastas canônicas (`app/`, `public/`, `docs/`, `storage/`). Qualquer artefato legado deve ser migrado para branches específicos (`legacy/`) ou arquivado fora do repositório antes de ser removido, para evitar duplicação e DNS incorreto (-`old/`).


---

## Observações de padronização

1) Renomeie `resourses/` para `resources/` e use esta última para depósitos de fontes externas quando houver PDF original, mantendo a convenção única de nomes.  
2) Rotas internas devem mapear 1:1 com controllers e views:
   - Ex.: `GET /internal/cartas-expedidas` → `CartasExpedidasController` → `app/views/internal/cartas_expedidas.php`  
3) Módulos sensíveis (produtividade, auditoria) devem gerar eventos em `LogEvento.php` e `storage/logs/security/`.

Fim.
