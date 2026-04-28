# Varredura Global — Padronizacao e Plano de Execucao (Onda 6)

**Data de abertura:** 2026-04-28  
**Escopo:** varredura global por ondas (PHP + JS), alinhado ao plano aprovado; continuidade com [`onda-3-varredura-global-relatorio.md`](onda-3-varredura-global-relatorio.md), [`varredura-global-correcao-continuada-relatorio.md`](varredura-global-correcao-continuada-relatorio.md) e [`governanca-toast-app-page.md`](governanca-toast-app-page.md).

---

## 1. Inventario (rastreamento)

### 1.1 Escopo de pastas

| Pasta | Papel |
|-------|--------|
| `app/views/internal` | Views PHP internas, includes, scripts inline |
| `public/api` | Endpoints JSON por arquivo |
| `app/helpers` | Helpers PHP reutilizaveis |
| `public/assets/js/shared` | JS compartilhado (`App.*`) |
| `public/assets/js/modules` | JS por modulo (Audiencias, Produtividade, Datajud, Cartorio search) |

**Nota:** nao existe `app/controllers/` no repositorio; fluxo e view + api + service.

### 1.2 Metricas por assinatura (grep, abril/2026)

| Padrao | Regiao | Observacao |
|--------|--------|------------|
| `notifyByKey` / wrappers | `public/assets/js` | Dezenas de arquivos shared + modules; prioridade delegar a `App.Page.notifyByKey` |
| `queuePostReloadToast` / `flushPostReloadToast` | `public/assets/js` | Concentrado em shared + audiencias; excecao documentada: `oficios-list.js` + highlight |
| `createSecondClickGuard` / `requireSecondClick` | `public/assets/js` | Parcialmente em `App.Page` / `App.RecordEditCommon`; locais em `midias-edit`, `produtividade-list` |
| `bootstrapRequireTrustedPostRequest` | `public/api/**/*.php` | Dezenas de endpoints; funcao canonica em `BootstrapHelper.php` |
| `DeleteAuthorization` | `public/api/**/delete.php` | Ja adotado em deletes listados na correcao continuada; expandir com cuidado a outros verbos |

### 1.3 Familias de duplicacao JS (resumo)

1. **Toast / App.Page:** `notify`, `notifyByKey`, fila pos-reload repetidos em listas Cartorio, GME, Oficios, Ceapa, Cejusc, Editais, Medidas, modules Audiencias/Produtividade.
2. **Filtros + URLSearchParams + location:** padrao copiado entre `*-list.js` e modules `pauta-list`, `midias-list`, `atas-list`.
3. **fetch POST JSON:** `App.RecordEditCommon.postJson` disponivel; muitos `fetch` inline em modules.
4. **Chaves sessionStorage pos-reload:** `salapauta.postReloadToast`, `estudapauta.postReloadToast`, `cartorio.postReloadToast`; **Sala de Midias** usava `cartorio.postReloadToast` por engano — corrigido nesta onda para `salamidias.postReloadToast`.
5. **Navegacao:** `location.href` vs `data-nav-href` + `navigate-buttons.js` — backlog UI.

### 1.4 Familias de duplicacao PHP (resumo)

1. **Handler por arquivo:** header JSON, `$basePath`, `require_once`, POST confiavel, payload JSON, try/catch 400/500.
2. **Alocar / desalocar / list / concluir-subtarefa:** estruturas paralelas entre modulos Cartorio (guias, editais, cartas, cejusc, etc.).
3. **`_common.php` por dominio:** `produtividade/_common.php`, `sala-midias/_common.php`, `internal-list-export/_common.php` — padroes similares, contratos diferentes.
4. **Perfis hardcoded:** arrays `ADMIN`, `CHEFIA`, etc. em cancel/desalocar — candidato a helper alinhado a `UsuarioPerfisAutorizacao` / `DeleteAuthorization` em onda futura.

### 1.5 Classificacao de candidatos

| Classe | Exemplos |
|--------|-----------|
| Canonico | `page-helpers.js` (`App.Page`), `record-edit-common.js`, `toast-catalog.js`, `DeleteAuthorization.php`, `BootstrapHelper.php` |
| Adapter temporario | Wrappers `notifyByKey` em varios `*-list.js` ate migracao completa |
| Duplicado migravel | `queueByKey`/`notifyByKey` internos em `cartorio-guias-list.js` vs `cartorio-cejusc-list.js` |
| Excecao legitima | `oficios-list.js` flush + highlight; storage keys por contexto Audiencias |
| Possivelmente sem uso | Sempre exigir grep em repo + teste manual antes de remover |

---

## 2. Contratos (fontes da verdade)

### 2.1 JavaScript

| Responsabilidade | Fonte | Regra |
|------------------|--------|--------|
| Toast + fila pos-reload + 2o clique (padrao) | `App.Page` em `public/assets/js/shared/page-helpers.js` | Ver `.cursor/rules/js-toast-fonte-unica-app-page.mdc` e `governanca-toast-app-page.md` |
| Telas edit compartilhadas | `App.RecordEditCommon` | Delega notify/fila/postJson para `App.Page` quando existir |
| Mensagens por chave | `App.ToastCatalog` + `config/toast_messages.php` | Endpoint `public/api/config/toast-messages.php` |
| Filtro processo em listas Cartorio finas | `App.ListSearch` em `list-search.js` | Ja usado em `cartorio/.../search-list.js` |

### 2.2 PHP (nova camada minima)

| Responsabilidade | Fonte | Uso |
|------------------|--------|-----|
| JSON resposta + header + status | `ApiJsonResponse` em `app/helpers/ApiJsonResponse.php` | `ApiJsonResponse::send($payload, $code)`; `ApiJsonResponse::readJsonPayload()` |
| POST confiavel (CSRF leve) | `bootstrapRequireTrustedPostRequest()` | Mantido; nao encapsulado em `ApiJsonResponse` para nao esconder dependencia |
| Exclusao por perfil | `DeleteAuthorization` | Continua canonico para familia delete ja migrada |

**Contrato:** endpoints que chamam `ApiJsonResponse::send` nao devem enviar `header('Content-Type: application/json')` antes, salvo casos especiais; o helper define Content-Type quando `headers_sent()` e falso.

---

## 3. Execucao desta onda (micro-lotes aplicados)

### Onda 6A — JS

1. **`cartorio-task-list.js`:** em `bindDesalocarButtons`, `notifyByKey` passa a priorizar `pageTools.notifyByKey` (App.Page), depois catalogo, depois fallback — alinhado a governanca.
2. **`midias-list.js` / `midias-edit.js`:** `POST_RELOAD_TOAST_KEY` alterado de `cartorio.postReloadToast` para `salamidias.postReloadToast` para isolamento de fila Sala de Midias vs Cartorio.
3. **`midias-edit.js`:** `requireSecondClick` passa a usar `App.Page.createSecondClickGuard` quando disponivel, com fallback local identico ao TTL 5s.

### Onda 6B — PHP

1. **Novo helper** `app/helpers/ApiJsonResponse.php` com `readJsonPayload()` e `send()`.
2. **Pilotos:** `public/api/cartorio-guias/delete.php` e `public/api/cartorio-editais/delete.php` passam a usar o helper para header + payload + erros, sem alterar textos nem codigos HTTP.

### Onda 6C — Documentacao

1. [`governanca-toast-app-page.md`](governanca-toast-app-page.md): entrada de excecao estrutural para storage key `salamidias.postReloadToast`.
2. Este arquivo como registro de inventario + contratos + execucao.

---

## 4. Pendencias (proximas ondas)

1. Extrair `App.ListFilters` / builder de query para listas Cartorio + Audiencias (medio risco).
2. Centralizar `postJson` em consumidores que ainda usam `fetch` inline (modules Audiencias, Produtividade).
3. Helper PHP `readJsonPayload` massivo: migrar endpoints por familia (alocar, list, cancel) com testes de contrato.
4. Harmonizar `confirm()` em listas Cartorio (cancelar subtarefa) com fluxo toast + clique unico, conforme regra destrutiva.
5. Resolver duplicidade documental (`guia-estrutura-docs.md` em dois caminhos) — ver subagent governanca.

---

## 5. Validacao (esta onda)

- `php -l` em `app/helpers/ApiJsonResponse.php`, `public/api/cartorio-guias/delete.php`, `public/api/cartorio-editais/delete.php` — **sem erros de sintaxe** (execucao local).
- Lint/diagnostico IDE em `cartorio-task-list.js`, `midias-list.js`, `midias-edit.js` — **sem alertas**.
- Smoke manual sugerido (nao automatizado nesta sessao): exclusao guia/edital (perfil autorizado); lista/edit Sala de Midias com redirect pos-exclusao e fila de toast na chave `salamidias.postReloadToast`.

---

## 6. Checklist final de aceite (rubrica)

- [x] Nenhuma mensagem de negocio ou status HTTP alterado nos pilotos PHP (textos e codigos espelhados do estado anterior).
- [x] Toasts e filas pos-reload preservam tipo/titulo/mensagem nos fluxos tocados (apenas chave de storage isolada em Midias).
- [x] Storage Sala de Midias nao colide mais com chave Cartorio (`salamidias.postReloadToast`).
- [x] Novos helpers documentados; excecoes em governanca atualizadas.
- [ ] Proxima varredura: repetir grep das assinaturas da secao 1.2 e comparar contagens (backlog continuo).

## 7. Conferencia pos-implementacao (grep)

- `salamidias.postReloadToast`: presente apenas em `midias-list.js` e `midias-edit.js` (isolamento confirmado).
- `cartorio-task-list.js`: ramo `notifyByKey` em desalocar prioriza `pageTools.notifyByKey` antes do catalogo direto.
