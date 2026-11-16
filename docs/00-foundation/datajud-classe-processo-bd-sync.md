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

Em telas internas com o trio `cnj.js` → `datajud-cycle-helper.js` → `datajud-api.js`, após **20 dígitos CNJ válidos**, o sistema lê a classe local, consulta o DataJud e sincroniza dados somente quando o processo já existe localmente e a resposta comprova CNJ exato, unicidade, metadados completos e ausência explícita de sigilo.

---

## Resumo executivo

| Tópico | Definição |
|--------|-----------|
| **Ordem** | CNJ válido → `classe-read` local → DataJud → sincronização agregada e atômica de classe + assuntos. |
| **Igualdade** | Comparação por **código** normalizado (`ClassesRepository::normalizeCodigo`); resposta `noop` sem toast de sucesso. |
| **Processo inexistente** | A sincronização é recusada; o DataJud não cria processo. |
| **DataJud falha** | Não chama sync; UI pode manter classe só do BD (*Classe do cadastro local*). Com classe já vinda do BD: **sem** toast de erro; só mensagem de contexto no rótulo/auxiliar. |
| **BD com classe** | Enquanto o DataJud carrega: input em **`status-finished`**, botões **desbloqueados** (`startLoading` com `bancoPrefilled`). |
| **BD com placeholder** | Se `classe-read` trouxer texto equivalente a **`Classe não informada`** (`DataJudCycle.FALLBACK_CLASSE`), **sem diferenciar maiúsculas/minúsculas** (ex.: “Classe Não Informada”), trata-se como **sem classe**: não preenche o input, usa placeholder de consulta ao DataJud e **`status-warning`**. |
| **Sigilo** | Gravação exige marcador explícito público (`nivelSigilo=0` ou “sem sigilo”); campo ausente ou ambíguo bloqueia a sincronização. |
| **Retries** | Até **3** tentativas com atraso em **rede** ou **HTTP 5xx**. As escritas combinadas compartilham uma única transação no servidor. |
| **Reconsulta** | Sem botão dedicado; novo ciclo ao **revalidar o CNJ** (`cnjValidated`). |
| **Botão de cadastro** | Contrato único: inicia `Aguarde...` (disabled), vira `Erro` em CNJ inválido, consulta DataJud quando válido e volta para `Cadastrar` habilitado quando o DataJud finaliza (sucesso ou falha). |

---

## Sequência técnica

1. **Validação CNJ** — `cnj.js` dispara `cnjValidated` com 20 dígitos (não a cada tecla; validação estável / blur).
2. **Leitura local** — `GET` `/api/processos/classe-read.php?numero=` (20 dígitos). Se existir classe, preenche o input (`data-preserve-banco="1"`).
3. **Consulta DataJud** — `POST` `/api/datajud/search-processo.php`. Até **3 tentativas** em caso de timeout por tentativa, com limite **10s / 15s / 20s** (`DataJudCycle.getFetchTimeoutMs`).
4. **Sincronização** — se houver classe ou assuntos utilizáveis, `POST` `/api/processos/datajud-sync.php`. O servidor reconsulta o DataJud, revalida as evidências e grava ambos na mesma transação. O endpoint `assunto-sync.php` faz sincronização somente de assuntos nas telas com classe travada.

---

## Arquivos da implementação

| Caminho | Papel |
|---------|--------|
| [`app/services/ProcessoClasseSyncService.php`](../../app/services/ProcessoClasseSyncService.php) | Leitura por CNJ + `syncFromDataJud`. |
| [`app/repositories/ClassesRepository.php`](../../app/repositories/ClassesRepository.php) | `normalizeCodigo`, `codigosEquivalentes`, `ensureClass`. |
| [`app/repositories/ProcessosRepository.php`](../../app/repositories/ProcessosRepository.php) | `updateClasseForProcesso`, `ensureProcess`, `findByNumero`. |
| [`public/api/processos/classe-read.php`](../../public/api/processos/classe-read.php) | API GET/POST leitura. |
| [`public/api/processos/classe-sync.php`](../../public/api/processos/classe-sync.php) | API POST sync. |
| [`public/api/processos/datajud-sync.php`](../../public/api/processos/datajud-sync.php) | Revalidação e sync atômico de classe + assuntos. |
| [`public/api/processos/assunto-sync.php`](../../public/api/processos/assunto-sync.php) | Revalidação e sync de assuntos quando classe está travada. |
| [`public/assets/js/modules/datajud/cnj.js`](../../public/assets/js/modules/datajud/cnj.js) | Validação CNJ e `cnjValidated`. |
| [`public/assets/js/modules/datajud/datajud-cycle-helper.js`](../../public/assets/js/modules/datajud/datajud-cycle-helper.js) | Lock de UI, `finishSuccess` / `finishFailure`, preservação BD/`data-datajud-initial`. |
| [`public/assets/js/modules/datajud/datajud-api.js`](../../public/assets/js/modules/datajud/datajud-api.js) | Orquestra BD → DataJud → endpoint agregado (retries). |
Documentação de apoio: [`public/assets/js/modules/datajud/README.md`](../../public/assets/js/modules/datajud/README.md), [`docs/create-processo-classe-reu-standard.md`](../create-processo-classe-reu-standard.md).

---

## APIs

| Endpoint | Método | Autenticação |
|----------|--------|--------------|
| `/api/processos/classe-read.php` | GET (`?numero=`) | Perfil `consultaprocessos`, sessão e processo local |
| `/api/processos/classe-sync.php` | POST JSON | Perfil `consultaprocessos`, origem confiável, sessão e processo local |
| `/api/processos/datajud-sync.php` | POST JSON | Perfil `consultaprocessos`, origem confiável, sessão e processo local |
| `/api/processos/assunto-sync.php` | POST JSON | Perfil `consultaprocessos`, origem confiável, sessão e processo local |

Gravações exigem `ApiAccessGuard`, POST de origem confiável e perfil interno autorizado. O servidor ignora os valores de classe/assuntos enviados pelo navegador e valida a resposta fonte antes de persistir.

---

## Comparação de código de classe (única fonte)

- [`ClassesRepository::normalizeCodigo()`](../../app/repositories/ClassesRepository.php)
- [`ClassesRepository::codigosEquivalentes()`](../../app/repositories/ClassesRepository.php) — equivalência de códigos ao comparar payload com registro existente.

---

## Atributo `data-datajud-initial` (UI)

Usado quando a **classe vem do PHP** no primeiro render. O helper **não zera** o valor ao entrar em “Consultando…”. Removido quando o CNJ validado **muda**, quando o BD preenche `data-preserve-banco`, após sucesso DataJud, ou quando o usuário edita o campo manualmente (`input`).

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
| 2 | **Regra de segurança atual (REI-76):** exige processo local existente; sincronização não cria processos. |
| 3 | **Diferença A:** comparar pelo **código** normalizado; igual → `noop`. |
| 4 | **Bloqueio C:** trava sobretudo em **edição** quando o produto exigir (`DATAJUD_SKIP_CLASSE_SYNC`); **create** com sync normal. |
| 5 | **Sem classe no DataJud A:** manter cadastro local quando o índice não trouxer classe. |
| 6 | **Auditoria B:** sem log dedicado por sync. |
| 7 | **Falha sync C:** retries automáticos limitados a rede e 5xx; gravação classe + assuntos usa transação agregada. |
| 8 | **Performance C:** disparo alinhado a `cnjValidated` (não a cada tecla). |
| 9 | **Ambiente D:** DataJud falhou → **não** chamar sync. |
| 10 | **Segurança atualizada (REI-76):** perfil `consultaprocessos` para sincronização DataJud; `consultaprocessosedit` para alteração manual; processo local obrigatório e ausência de sigilo explícita. |

---

## Decisões de ajuste fino (alinhamento final)

| # | Decisão |
|---|---------|
| **1** | Toast de sync só em `updated` / `created`; não em `noop`. |
| **2** | Campo classe **readonly** após sucesso DataJud (edição manual não é o padrão). |
| **3** | Sem botão “Atualizar pelo DataJud”; só **revalidação do CNJ**. |

---

## Exceções de produto

- Telas com `window.DATAJUD_SKIP_CLASSE_SYNC = true`: consulta DataJud e UI seguem; classe é preservada e assuntos são sincronizados pelo endpoint verificado de assuntos.

---

## Checklist de QA manual (cenários globais)

- [ ] Processo **novo** no BD: DataJud retorna classe → toast **info** em `updated`/`created`; `processos` e `classes` coerentes.
- [ ] **Mesma** classe BD/DataJud → `noop`, sem toast.
- [ ] Classe **diferente** no DataJud → `updated` + toast **info**.
- [ ] DataJud **sem** classe; BD tinha classe → *Classe do cadastro local* / fallback; sem toast de erro.
- [ ] Falha de rede no sync após DataJud OK → retries; **sem** toast de erro; valores DataJud mantidos.
- [ ] BD já trouxe classe → `status-finished` e botões liberados enquanto DataJud carrega.
- [ ] **Troca de CNJ** → novo ciclo sem valor fantasma.

### Por área (amostragem mínima por release)

| Área | Fluxos |
|------|--------|
| **Cartório** | Guias, cartas, ofícios list, editais. |
| **Gabinete** | Ofícios list, monitoramento create, réus presos edit. |
| **Audiências** | Pauta create. |

---

## Inventário de views (trio completo)

Tabela atualizada em [`docs/create-processo-classe-reu-standard.md`](../create-processo-classe-reu-standard.md).
