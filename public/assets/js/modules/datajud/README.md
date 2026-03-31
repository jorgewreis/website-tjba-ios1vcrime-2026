# Módulo DataJud (front-end)

Scripts **transversais** usados em gabinete, cartório, audiências e outras áreas: formatação/validação CNJ, ciclo de UI da consulta ao DataJud (lock de botões, toasts, abort), **pré-leitura da classe no banco**, preenchimento da classe processual após `cnjValidated` e **sincronização automática** com o cadastro local.

## Fluxo (ordem real)

1. `cnj.js` — evento `cnjValidated` (20 dígitos).
2. `datajud-api.js` — `GET /api/processos/classe-read.php?numero=...` (sessão). Se houver classe no BD, preenche o input com `data-preserve-banco="1"` para não apagar o valor durante “Consultando…”.
3. `DataJudCycle.startLoading` — consulta ao DataJud em andamento.
4. `POST /api/datajud/search-processo.php` — classe vinda do índice DataJud.
5. Em sucesso com classe — `POST /api/processos/classe-sync.php` (sessão) para garantir `classes` + `processos.classe_id` (noop se código igual ao já vinculado). `postSyncClasse` aplica **até 3 tentativas** em falha de **rede** ou **5xx**; **toasts só em** `updated`/`created` (**info**); erros de sync **sem** toast. Se o **BD** já preencheu a classe, `DataJudCycle.startLoading` usa `bancoPrefilled`: input em **`status-finished`**, botões **sem** lock e sem toast “consultando…” até o DataJud responder.

**Documentação canônica (única fonte):** [`docs/00-foundation/datajud-classe-processo-bd-sync.md`](../../../../docs/00-foundation/datajud-classe-processo-bd-sync.md) (índice, decisões, QA, arquivos).

## Arquivos

| Arquivo | Função |
|---------|--------|
| `cnj.js` | Máscara, validação e evento `cnjValidated` após número válido (20 dígitos). |
| `datajud-cycle-helper.js` | `window.DataJudCycle` — snapshot de botões, `aria-busy`, timer 500 ms, `AbortController` compartilhado; restauração de classe do BD se o DataJud falhar ou não trouxer classe. |
| `datajud-api.js` | Leitura BD, escuta `cnjValidated`, `POST` DataJud, `POST` sync; `window.__datajudBancoPrefill` para fallback de UI. |

## Overrides opcionais (janela)

| Variável | Uso |
|----------|-----|
| `window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT` | Substitui a URL do `POST` DataJud. |
| `window.PROCESSO_CLASSE_READ_ENDPOINT` | Substitui `GET` de leitura BD (padrão `/api/processos/classe-read.php`). |
| `window.PROCESSO_CLASSE_SYNC_ENDPOINT` | Substitui `POST` de sync (padrão `/api/processos/classe-sync.php`). |
| `window.DATAJUD_SKIP_CLASSE_SYNC` | Se `true`, não chama o sync após o DataJud (ex.: CEAPA com classe travada). |

## Atributos no input de classe

| Atributo | Uso |
|----------|-----|
| `data-datajud-related="true"` | Marca o campo como ligado ao fluxo DataJud (recomendado). |
| `data-preserve-banco="1"` | Definido pelo script após leitura BD; evita apagar o valor no estado “Consultando…”. |
| `data-datajud-initial="1"` | Definido no **PHP** quando a classe já veio do servidor no primeiro render (ex.: CEAPA com classe travada); o helper preserva o texto ao entrar em loading até o CNJ mudar ou o fluxo substituir por BD/DataJud. |

Comparação de códigos no backend: `ClassesRepository::normalizeCodigo` / `codigosEquivalentes` — ver `docs/00-foundation/datajud-classe-processo-bd-sync.md`.

## Ordem de inclusão nas views

1. `cnj.js`
2. `datajud-cycle-helper.js`
3. `datajud-api.js`

Scripts específicos da página vêm **depois**.

## Contrato de IDs

Resolvido em cadeia em `datajud-api.js` (`getDom`): classe `audiencia-classe` → `gab-mon-classe` → `filter-classe`; processo `audiencia-processo` → `gab-mon-processo` → `filter-processo`; opcionais `hidden-classe-codigo`, `hidden-classe-descricao`, status `audiencia-classe-status` ou `processo-ap-info`.

Marcação HTML recomendada: `data-datajud-related="true"` no input de classe; `data-allow-during-loading="true"` em botões de escape (Voltar/Cancelar) quando aplicável.

## Observação

O nome legado `window.CARTAS_EXPEDIDAS_DATAJUD_ENDPOINT` permanece suportado para sobrescrever a URL da API DataJud sem alterar este módulo.
