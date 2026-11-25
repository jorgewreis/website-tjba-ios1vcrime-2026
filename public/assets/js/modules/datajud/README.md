# Módulo DataJud (front-end)

Scripts **transversais** usados em gabinete, cartório, audiências e outras áreas: formatação/validação CNJ, ciclo de UI da consulta ao DataJud (lock de botões, toasts, abort), **pré-leitura da classe no banco**, preenchimento da classe processual após `cnjValidated` e **sincronização automática** com o cadastro local.

## Fluxo (ordem real)

1. `cnj.js` — evento `cnjValidated` (20 dígitos).
2. `datajud-api.js` — `GET /api/processos/classe-read.php?numero=...` (sessão). Se houver classe no BD **e** o texto não for equivalente ao placeholder **`Classe não informada`** (`DataJudCycle.FALLBACK_CLASSE`, comparação **sem** diferenciar maiúsculas/minúsculas), preenche o input com `data-preserve-banco="1"`. Caso contrário, segue o loading padrão (`status-warning`, placeholder curto de consulta ao DataJud).
3. `DataJudCycle.startLoading` — consulta ao DataJud em andamento (placeholder base `PLACEHOLDER_LOADING`).
4. `POST /api/datajud/search-processo.php` — classe vinda do índice DataJud; até **3 tentativas** após timeout, com espera **10s / 15s / 20s** por tentativa (`getFetchTimeoutMs`). O placeholder do input fica **`Tent. N — DataJud…`** a partir da 2ª tentativa.
5. Após resposta utilizável, a sincronização automática usa `POST /api/processos/datajud-sync.php`, que reconsulta e valida o resultado no servidor e grava classe + assuntos em uma única transação. Se `DATAJUD_SKIP_CLASSE_SYNC` estiver ativo, a tela usa `POST /api/processos/assunto-sync.php` para sincronizar somente assuntos e preservar a classe travada. Ambas as chamadas enviam apenas o CNJ; valores do navegador não são fonte de gravação. Há até **3 tentativas** em falhas de **rede** ou **5xx**; erros de sync não geram toast.

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
| `window.PROCESSO_ASSUNTO_SYNC_ENDPOINT` | Substitui o sync apenas de assuntos quando a classe está travada. |
| `window.DATAJUD_SKIP_CLASSE_SYNC` | Se `true`, sincroniza apenas assuntos e preserva a classe travada (ex.: CEAPA). |

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
