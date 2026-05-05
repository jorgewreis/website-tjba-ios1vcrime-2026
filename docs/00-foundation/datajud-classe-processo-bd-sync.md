# DataJud + classe processual: BD primeiro e sincronização automática

Documento **canônico** do fluxo: decisões de produto, implementação, APIs, UI e QA. Demais referências (`README` do módulo, regra Cursor, `create-processo-classe-reu-standard.md`) devem remeter a este arquivo quando houver divergência.

---

## Índice

1. [Objetivo](#objetivo)
2. [Resumo executivo](#resumo-executivo)
3. [Sequência técnica](#sequência-técnica)
4. [Arquivos da implementação](#arquivos-da-implementação)
5. [APIs](#apis)
6. [Comparação de código de classe](#comparação-de-código-de-classe-única-fonte)
7. [Atributos HTML / UI](#atributo-data-datajud-initial-ui)
8. [Feedback (toasts)](#feedback-toasts)
9. [Decisões de produto (questionário 1–10)](#decisões-de-produto-questionário-110)
10. [Decisões de ajuste fino (3)](#decisões-de-ajuste-fino-alinhamento-final)
11. [Exceções](#exceções-de-produto)
12. [Checklist de QA](#checklist-de-qa-manual-cenários-globais)
13. [Inventário de telas](#inventário-de-views-trio-completo)

---

## Objetivo

Em telas internas com o trio `cnj.js` → `datajud-cycle-helper.js` → `datajud-api.js`, após **20 dígitos CNJ válidos**, o sistema: lê a **classe já vinculada** em `processos`/`classes`; consulta o **DataJud**; e **sincroniza** o cadastro local quando a API externa devolve classe utilizável — **sem** depender do envio do formulário do módulo para essa gravação.

---

## Resumo executivo

| Tópico | Definição |
|--------|-----------|
| **Ordem** | CNJ válido → `classe-read` (BD) → DataJud → `classe-sync` (só se DataJud trouxe classe). |
| **Igualdade** | Comparação por **código** normalizado (`ClassesRepository::normalizeCodigo`); resposta `noop` sem toast de sucesso. |
| **Processo novo** | `classe-sync` pode **criar** linha em `processos` com número + classe. |
| **DataJud falha** | Não chama sync; UI pode manter classe só do BD (*Classe do cadastro local*). Com classe já vinda do BD: **sem** toast de erro; só mensagem de contexto no rótulo/auxiliar. |
| **BD com classe** | Enquanto o DataJud carrega: input em **`status-finished`**, botões **desbloqueados** (`startLoading` com `bancoPrefilled`). |
| **BD com placeholder** | Se `classe-read` trouxer texto equivalente a **`Classe não informada`** (`DataJudCycle.FALLBACK_CLASSE`), **sem diferenciar maiúsculas/minúsculas** (ex.: “Classe Não Informada”), trata-se como **sem classe**: não preenche o input, usa placeholder de consulta ao DataJud e **`status-warning`**. |
| **CEAPA edit travado** | `DATAJUD_SKIP_CLASSE_SYNC` — não grava via sync; DataJud ainda preenche visualmente. |
| **Retries** | `postSyncClasse` em `datajud-api.js`: até **3** tentativas com atraso em **rede** ou **HTTP 5xx**. O corpo da resposta é lido como **texto** e parseado com `JSON.parse` seguro; corpo inválido ou vazio **não** dispara retry (evita confundir HTML/erro de proxy com falha de rede). |
| **Reconsulta** | Sem botão dedicado; novo ciclo ao **revalidar o CNJ** (`cnjValidated`). |
| **Botão de cadastro** | Contrato único: inicia `Aguarde...` (disabled), vira `Erro` em CNJ inválido, consulta DataJud quando válido e volta para `Cadastrar` habilitado quando o DataJud finaliza (sucesso ou falha). |

---

## Sequência técnica

1. **Validação CNJ** — `cnj.js` dispara `cnjValidated` com 20 dígitos (não a cada tecla; validação estável / blur).
2. **Leitura local** — `GET` `/api/processos/classe-read.php?numero=` (20 dígitos). Se existir classe, preenche o input (`data-preserve-banco="1"`).
3. **Consulta DataJud** — `POST` `/api/datajud/search-processo.php`. Até **3 tentativas** em caso de timeout por tentativa, com limite **10s / 15s / 20s** (`DataJudCycle.getFetchTimeoutMs`).
4. **Sincronização** — se a resposta DataJud tiver classe utilizável, `POST` `/api/processos/classe-sync.php`. Respostas: `noop` | `updated` | `created`.

---

## Arquivos da implementação

| Caminho | Papel |
|---------|--------|
| [`app/services/ProcessoClasseSyncService.php`](../../app/services/ProcessoClasseSyncService.php) | Leitura por CNJ + `syncFromDataJud`. |
| [`app/repositories/ClassesRepository.php`](../../app/repositories/ClassesRepository.php) | `normalizeCodigo`, `codigosEquivalentes`, `ensureClass`. |
| [`app/repositories/ProcessosRepository.php`](../../app/repositories/ProcessosRepository.php) | `updateClasseForProcesso`, `ensureProcess`, `findByNumero`. |
| [`public/api/processos/classe-read.php`](../../public/api/processos/classe-read.php) | API GET/POST leitura. |
| [`public/api/processos/classe-sync.php`](../../public/api/processos/classe-sync.php) | API POST sync. |
| [`public/assets/js/modules/datajud/cnj.js`](../../public/assets/js/modules/datajud/cnj.js) | Validação CNJ e `cnjValidated`. |
| [`public/assets/js/modules/datajud/datajud-cycle-helper.js`](../../public/assets/js/modules/datajud/datajud-cycle-helper.js) | Lock de UI, `finishSuccess` / `finishFailure`, preservação BD/`data-datajud-initial`. |
| [`public/assets/js/modules/datajud/datajud-api.js`](../../public/assets/js/modules/datajud/datajud-api.js) | Orquestra BD → DataJud → `postSyncClasse` (retries). |
| [`app/views/internal/cartorio-ceapa-edit.php`](../../app/views/internal/cartorio-ceapa-edit.php) | `DATAJUD_SKIP_CLASSE_SYNC`, `data-datajud-initial` quando aplicável. |

Documentação de apoio: [`public/assets/js/modules/datajud/README.md`](../../public/assets/js/modules/datajud/README.md), [`docs/create-processo-classe-reu-standard.md`](../create-processo-classe-reu-standard.md).

---

## APIs

| Endpoint | Método | Autenticação |
|----------|--------|--------------|
| `/api/processos/classe-read.php` | GET (`?numero=`) ou POST JSON | Sessão (`id_usuario`) |
| `/api/processos/classe-sync.php` | POST JSON | Sessão |

**Segurança (10A):** cookie de sessão + `fetch` same-origin; sem token CSRF extra neste fluxo. Reavaliar se houver falha em iframe ou política nova.

---

## Comparação de código de classe (única fonte)

- [`ClassesRepository::normalizeCodigo()`](../../app/repositories/ClassesRepository.php)
- [`ClassesRepository::codigosEquivalentes()`](../../app/repositories/ClassesRepository.php) — ex.: regra CEAPA ao comparar payload com registro existente.

---

## Atributo `data-datajud-initial` (UI)

Usado quando a **classe vem do PHP** no primeiro render (ex.: CEAPA com classe travada). O helper **não zera** o valor ao entrar em “Consultando…”. Removido quando o CNJ validado **muda**, quando o BD preenche `data-preserve-banco`, após sucesso DataJud, ou quando o usuário edita o campo manualmente (`input`).

---

## Feedback (toasts)

- **Sync** (`updated` / `created`): toast **`info`** (`variant: 'simple'`) — atualização de classe ou cadastro de processo/classe.
- **Erro** de sync (HTTP, rede após retries, corpo inválido): **sem toast** (valores do DataJud permanecem na tela; o usuário pode salvar o formulário).
- **`noop`:** sem toast (decisão ajuste fino **1**).
- **DataJud** (timeout, HTTP, rede, processo sem classe): toast **`error`** só quando **não** há classe no BD (`classe-read` não preencheu o campo); se já havia classe local, só ajuste de UI/label, sem toast.

---

## Decisões de produto (questionário 1–10)

| # | Decisão |
|---|---------|
| 1 | **Escopo A:** todas as telas com trio completo (inventário canônico). |
| 2 | **Processo inexistente A:** sync pode criar linha mínima em `processos` quando o DataJud trouxer classe. |
| 3 | **Diferença A:** comparar pelo **código** normalizado; igual → `noop`. |
| 4 | **Bloqueio C:** trava sobretudo em **edição** (ex.: CEAPA); **create** com sync normal. |
| 5 | **Sem classe no DataJud A:** manter cadastro local quando o índice não trouxer classe. |
| 6 | **Auditoria B:** sem log dedicado por sync. |
| 7 | **Falha sync C:** retries automáticos limitados — **implementado** em `postSyncClasse` (`SYNC_MAX_ATTEMPTS`, rede e 5xx). |
| 8 | **Performance C:** disparo alinhado a `cnjValidated` (não a cada tecla). |
| 9 | **Ambiente D:** DataJud falhou → **não** chamar sync. |
| 10 | **Segurança A:** sessão + same-origin; sem CSRF extra até haver motivo. |

---

## Decisões de ajuste fino (alinhamento final)

| # | Decisão |
|---|---------|
| **1** | Toast de sync só em `updated` / `created`; não em `noop`. |
| **2** | Campo classe **readonly** após sucesso DataJud (edição manual não é o padrão). |
| **3** | Sem botão “Atualizar pelo DataJud”; só **revalidação do CNJ**. |

---

## Exceções de produto

- **CEAPA edição com classe travada** — [`cartorio-ceapa-edit.php`](../../app/views/internal/cartorio-ceapa-edit.php): `window.DATAJUD_SKIP_CLASSE_SYNC = true`; consulta DataJud e UI seguem; **POST** `classe-sync` não é enviado.

---

## Checklist de QA manual (cenários globais)

- [ ] Processo **novo** no BD: DataJud retorna classe → toast **info** em `updated`/`created`; `processos` e `classes` coerentes.
- [ ] **Mesma** classe BD/DataJud → `noop`, sem toast.
- [ ] Classe **diferente** no DataJud → `updated` + toast **info**.
- [ ] DataJud **sem** classe; BD tinha classe → *Classe do cadastro local* / fallback; sem toast de erro.
- [ ] Falha de rede no sync após DataJud OK → retries; **sem** toast de erro; valores DataJud mantidos.
- [ ] BD já trouxe classe → `status-finished` e botões liberados enquanto DataJud carrega.
- [ ] CEAPA `lockClasse` → sem `POST` em `classe-sync`.
- [ ] **Troca de CNJ** → novo ciclo sem valor fantasma.

### Por área (amostragem mínima por release)

| Área | Fluxos |
|------|--------|
| **Cartório** | Guias, cartas, CEAPA create/edit, ofícios list, medidas, editais, CEJUSC create. |
| **Gabinete** | Ofícios list, monitoramento create, réus presos edit. |
| **Audiências** | Pauta create. |

---

## Inventário de views (trio completo)

Tabela atualizada em [`docs/create-processo-classe-reu-standard.md`](../create-processo-classe-reu-standard.md).
