# Módulo de produtividade interna — estado e roadmap

Documento vivo: descreve o que **já está no repositório**, o que o **schema** prevê sem uso na aplicação e o escopo planejado das **Fases 3 e 4** (roadmap em quatro fases alinhado ao planejamento do produto).

**Schema canônico:** [docs/schema/produtividade.sql](../../schema/produtividade.sql)

---

## Visão geral do roadmap (quatro fases)

| Fase | Conteúdo |
|------|------------|
| **1** | Banco: tabelas `produtividade_*`, triggers de IDs (`PRG-`, `PLC-`, etc.) |
| **2** | CRUD de regras, lançamento **manual**, listagem com filtros, APIs núcleo, integração rota/menu/RBAC |
| **3** | **Ranking** (por setor / visibilidade), **CRUD de metas**, **exportação** PDF e CSV |
| **4** | **Badges**, **widget no dashboard**, **`registrarAuto` / automações** nos serviços, desligamento gradual do legado |

As Fases 3 e 4 abaixo descrevem o que está **planejado para implementação**; no código atual a Fase 2 está substancialmente entregue; a Fase 3 e a 4 **ainda não** têm repositórios, telas ou APIs dedicados (exceto o que o schema já cria no banco).

---

## O que já foi implementado

### Fase 1 — Banco de dados

- Script único com tabelas:
  - `produtividade_regras` — catálogo de pontuação (`tipo_ato`, `tipo_documento`, `sistema_modulo`, multiplicador, `ativo`).
  - `produtividade_lancamentos` — lançamentos manuais e futuros automáticos; `tipo_lancamento` (`manual` / `automatico`), `status` (`ativo` / `anulado`), `contexto_hash` **único** (anti-duplicidade quando preenchido).
  - `produtividade_metas` — **definida no SQL, sem camada PHP ainda**.
  - `produtividade_log` — auditoria (ações como `criar_manual`, `anular_manual`).
  - `produtividade_badges` e `produtividade_badges_concedidos` — **definidas no SQL, sem uso na aplicação ainda**.
- Triggers `BEFORE INSERT` para gerar IDs com prefixos (`PRG-`, `PLC-`, `PMT-`, `PLG-`, `PBG-`, `PBC-`).

### Fase 2 — Aplicação (núcleo operacional)

**Serviço**

- [app/services/ProdutividadeService.php](../../../app/services/ProdutividadeService.php):
  - **Regras (gestão):** `listRules`, `findRule`, `listRuleCatalogValues`, `createRule`, `updateRule`, `setRuleAtivo`.
  - **Busca de regras ativas:** `searchRulesAtivas` (autocomplete para lançamento manual).
  - **Lançamento manual:** `createManualLancamento` — valida regra ativa, quantidade, CNJ do processo (quando informado), calcula pontos, gera `contexto_hash` quando há processo (duplicidade no mesmo minuto), grava log `criar_manual`.
  - **Anulação:** `anularLancamentoManual` — só `tipo_lancamento = manual`; dono pode anular em até **1 hora**; perfis de gestão (`ADMIN`, `CHEFIA`, `DIRETORA`, `MAGISTRADA`) podem anular além disso; log `anular_manual`.
  - **Listagem:** `listLancamentos` — usuário comum vê só os próprios lançamentos; gestão pode filtrar por `usuario_id`; retorna `rows`, `total`, `sum_pontos`, `is_gestao`.

**Repositórios**

- [app/repositories/ProdutividadeRegrasRepository.php](../../../app/repositories/ProdutividadeRegrasRepository.php)
- [app/repositories/ProdutividadeLancamentosRepository.php](../../../app/repositories/ProdutividadeLancamentosRepository.php)
- [app/repositories/ProdutividadeLogRepository.php](../../../app/repositories/ProdutividadeLogRepository.php)

**APIs HTTP** (`public/api/produtividade/`)

- [public/api/produtividade/_common.php](../../../public/api/produtividade/_common.php) — sessão, JSON, helper `produtividadeIsGestao`.
- `regras/list.php`, `regras/create.php`, `regras/update.php`, `regras/search.php`
- `lancamentos/list.php`, `lancamentos/create-manual.php`, `lancamentos/anular.php`

**Interface (views internas)**

- [app/views/internal/produtividade-list.php](../../../app/views/internal/produtividade-list.php) — listagem server-side com filtros (`status`, `tipo_lancamento`, intervalo de datas **máx. 60 dias**, `usuario_id` para gestão), total de pontos no período, paginação, modal de lançamento manual com busca de regra, integração DataJud para classe processual quando aplicável.
- [app/views/internal/produtividade-regras-list.php](../../../app/views/internal/produtividade-regras-list.php), `produtividade-regras-create.php`, `produtividade-regras-edit.php`.
- [app/views/internal/produtividade.php](../../../app/views/internal/produtividade.php) — apenas redireciona para `produtividade-list.php` (mantém compatibilidade de rota).

**JavaScript**

- [public/assets/js/modules/produtividade/produtividade-list.js](../../../public/assets/js/modules/produtividade/produtividade-list.js) — busca de regras, criação manual, anulação com confirmação em duplo passo + `showToast` (padrão iframe).

**Integração portal**

- [public/frame.php](../../../public/frame.php) — `produtividade` → `produtividade-list.php`; `produtividaderegras` (+ create/edit).
- [app/access/PageAccessRegistry.php](../../../app/access/PageAccessRegistry.php) — `produtividade`: `all_internal`; telas de regras: `gestao`.
- [public/home.php](../../../public/home.php) — item “Produtividade” (área interna); “Regras de Produtividade” com perfis restritos.

### Legado (ainda em uso pontual)

- [app/services/Produtividade.php](../../../app/services/Produtividade.php) — grava na tabela MySQL **`Produtividade`** (PascalCase, colunas legadas).
- [scripts/migrate-produtividade-table.php](../../../scripts/migrate-produtividade-table.php) — cria a tabela legada `Produtividade` (uso histórico / ambientes antigos).
- [app/services/Guias.php](../../../app/services/Guias.php) — em fluxo de cadastro de guia chama `Produtividade::registrarCadastroGuia` (pontos fixos 1 ou 2 na tabela antiga). **Não** passa pelo `ProdutividadeService` nem por `produtividade_lancamentos`.

### O que o banco já tem mas a aplicação ainda não usa

- Tabela **`produtividade_metas`** — sem `ProdutividadeMetasRepository`, sem APIs `metas-*`, sem telas.
- Tabelas **`produtividade_badges`** / **`produtividade_badges_concedidos`** — sem telas admin, sem motor de concessão, sem UI de conquistas.
- Campo **`tipo_lancamento = automatico`** em `produtividade_lancamentos` — não há método público no serviço que insira lançamento automático a partir dos outros módulos (Fase 4).

---

## Fase 3 — em implementação planejada

Objetivo: **ranking**, **metas** e **exportação**, sem expandir ainda as automações em massa.

Entregas previstas:

1. **Metas**
   - `ProdutividadeMetasRepository` + métodos no `ProdutividadeService` (listar/criar/atualizar/desativar com `assertGestao`).
   - APIs JSON em `public/api/produtividade/metas/` (list, create, update, delete ou equivalente).
   - Telas ou fluxo de gestão (list/create/edit), rotas em `frame.php`, entradas em `PageAccessRegistry` e item de menu se necessário.

2. **Ranking**
   - Agregação de pontos por usuário (e por **setor**, após definir no código de onde vem o setor do usuário — cadastro, vínculo, ou política documentada).
   - API `ranking.php` com parâmetros alinhados aos filtros da listagem.
   - Bloco ou aba em `produtividade-list.php`: usuário comum vê ranking do próprio setor; gestores/admin veem visão ampliada.

3. **Exportação**
   - `export-csv.php` e `export-pdf.php` respeitando os filtros atuais da lista.
   - Botões na listagem (autenticação coerente com as demais APIs).

4. **Opcional nesta fase**
   - Cards de resumo (semana/mês/trimestre, comparativo, projeção) se ainda não cobertos pela listagem.

5. **QA**
   - Permissões, intervalo de 60 dias, UTF-8 no CSV, PDF com os mesmos filtros da tela.

---

## Fase 4 — em implementação planejada

Objetivo: **gamificação**, **visibilidade no dashboard** e **automação** centralizada, além de reduzir o legado.

Entregas previstas (alinhadas ao plano de produto em quatro fases):

1. **`registrarAuto` (ou equivalente)** no `ProdutividadeService`
   - Resolução de regra por `sistema_modulo` + `tipo_ato` + `tipo_documento` (regra inexistente/inativa: **não** quebrar o fluxo do módulo de origem; comportamento silencioso ou log leve, conforme decisão já registrada no planejamento).
   - `contexto_hash` determinístico + tratamento de duplicidade (constraint única / erro 1062 como idempotente).
   - Log em `produtividade_log` (ex.: ação de criação automática).

2. **Integrações nos serviços**
   - Substituir ou delegar `Produtividade::registrarCadastroGuia` em `Guias.php` para o fluxo novo.
   - PRs incrementais: cartório (cartas, editais, CEJUSC, medidas), gabinete, audiências — conforme prioridade.

3. **Badges**
   - Telas admin (list/create/edit), `verificarBadges()` após lançamentos, persistência em `produtividade_badges_concedidos`, notificação (toast + mini popup, se mantida a decisão de UX).

4. **Dashboard**
   - Widget em `dashboard.php` (ex.: pontos da semana, posição no ranking do setor).

5. **Legado**
   - Deprecar escrita na tabela `Produtividade` após validação; manter migração/script apenas para ambientes que ainda precisem da tabela antiga.

---

## Referências rápidas de arquivos

| Área | Caminhos principais |
|------|---------------------|
| Schema | `docs/schema/produtividade.sql` |
| Serviço | `app/services/ProdutividadeService.php` |
| Repositórios | `app/repositories/Produtividade{Regras,Lancamentos,Log}Repository.php` |
| APIs | `public/api/produtividade/**` |
| UI lista / regras | `app/views/internal/produtividade-list.php`, `produtividade-regras-*.php` |
| JS | `public/assets/js/modules/produtividade/produtividade-list.js` |
| Legado | `app/services/Produtividade.php`, `Guias.php`, `scripts/migrate-produtividade-table.php` |

---

*Última revisão do conteúdo frente ao repositório: documento gerado para consolidar implementado vs. planejado (Fases 3 e 4).*
