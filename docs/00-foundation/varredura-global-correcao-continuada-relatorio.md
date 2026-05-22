# Varredura Global - Relatorio de Correcao Continuada (JS + PHP)

## Escopo executado
- Trilha A (JS toast/notificacao): consolidacao de `queuePostReloadToastByKey` em ponto unico com fallback.
- Trilha B (JS utilitarios): reducao de duplicacao de `postJson` em modulo com wrapper local.
- Trilha C (PHP reuso/coesao): consolidacao de autorizacao de exclusao em helper unico para endpoints equivalentes.
- Trilha D (candidatos sem uso): validacao com evidencia e deprecacao controlada (sem remocao definitiva).

## Trilha A - Toast/Notificacao
### Acoes aplicadas
- Adicao de `queuePostReloadToastByKey(...)` em `App.Page`:
  - `public/assets/js/shared/page-helpers.js`
- Reuso desse helper em:
  - `public/assets/js/shared/record-edit-common.js`
  - `public/assets/js/modules/audiencias/pauta-list.js`
  - `public/assets/js/modules/audiencias/pauta-edit.js`
  - `public/assets/js/modules/audiencias/pauta-create.js`

### Resultado
- Menos duplicacao de logica de resolve/fallback de toast pos-reload.
- Compatibilidade preservada (fallback local mantido em todos os pontos).

## Trilha B - Utilitarios JS
### Acoes aplicadas
- `postJson(...)` de prioridades passou a priorizar helper compartilhado quando disponivel:
  - `public/assets/js/modules/audiencias/pauta-edit-prioridades.js`

### Resultado
- Reuso direto de `App.RecordEditCommon.postJson(...)` quando carregado.
- Fallback fetch local mantido para evitar regressao.

## Trilha C - Reuso PHP (autorizacao)
### Acoes aplicadas
- Novo helper:
  - `app/helpers/DeleteAuthorization.php`
- Endpoints migrados para usar `DeleteAuthorization::usuarioPodeExcluir(...)`:
  - `public/api/cartorio-oficios/delete.php`
  - `public/api/cartorio-cartas-expedidas/delete.php`
  - `public/api/cartorio-cartas-recebidas/delete.php`
  - `public/api/cartorio-cejusc/delete.php`
  - `public/api/cartorio-ceapa/delete.php`
  - `public/api/cartorio-editais/delete.php`

### Resultado
- Regra de perfil de exclusao centralizada.
- Menor risco de divergencia entre endpoints equivalentes.

## Trilha D - Candidatos sem uso
### Evidencias
- `public/assets/js/shared/navigation.js`: apenas definicao de `setupNavigationButton(...)` encontrada, sem chamadas no repositorio.
- `app/services/Produtividade.php` (legado): sem referencias apos auditoria do modulo Produtividade (views `produtividade*.php`, `public/api/produtividade/*`, helpers/js do modulo).

### Acao aplicada nesta etapa
- Removido `public/assets/js/shared/navigation.js` (evidencia de ausencia total de referencias no repositorio).
- Removido `app/services/Produtividade.php` apos auditoria completa do modulo Produtividade; fluxo canonico em `ProdutividadeService`.
- Removido `public/assets/js/shared/form-utils.js`: `FormUtils` / nome do arquivo sem referencias em PHP/HTML/JS do repositorio.

## Validacao tecnica
- JS lints (arquivos alterados): sem erros.
- PHP sintaxe (`php -l`) dos arquivos alterados: sem erros.
- PHP sintaxe (`php -l`) em todos os `public/api/produtividade/**/*.php` (22 arquivos): sem erros.

## Pendencias para proxima rodada
- Expandir padronizacao de resposta JSON em endpoints de mesma familia (quando aprovado).

---

## Onda 4 — Consolidacao extra (DELETE + RecordEditCommon toast)

### PHP — autorizacao de exclusao alinhada
- `DeleteAuthorization::perfisGabineteExclusao()` para fluxos Gabinete / Sala de Midias (`ADMIN`, `CHEFIA`).
- Endpoints atualizados para usar o helper:
  - `public/api/cartorio-guias/delete.php`
  - `public/api/gabinete-oficios/delete.php`
  - `public/api/gabinete-monitoramento-eletronico/delete.php`
  - `public/api/sala-midias/delete.php`

### JS — fonte unica via `App.Page`
- `notifyByKey` em `public/assets/js/shared/record-edit-common.js` passa a delegar primeiro para `App.Page.notifyByKey` (beneficia todas as telas que usam `App.RecordEditCommon`).

### Validacao
- `php -l` nos PHP alterados: sem erros.
- Lint JS (`record-edit-common.js`): sem erros.

---

## Onda 5 — Admin feriados + toast shared restante

### PHP
- `DeleteAuthorization::perfisAdminOuChefia()` (DRY com `perfisGabineteExclusao()`).
- `public/api/admin/feriados/delete.php` migrado para `DeleteAuthorization::usuarioPodeExcluir(..., perfisAdminOuChefia())` (mensagens 403 inalteradas).

### JS (shared)
- `notifyByKey` passa a priorizar `App.Page.notifyByKey` quando existir, com fallback ao catalogo local:
  - `public/assets/js/shared/oficios-list.js`, `oficios-edit.js`
  - `public/assets/js/shared/gme-list.js`, `gme-edit.js`, `gme-create.js`
  - `public/assets/js/shared/cartorio-simple-edit.js`, `cartorio-guias-edit.js`, `cartorio-alocacao-direcionada-edit.js`
  - `public/assets/js/shared/cartorio-editais-list.js`, `cartorio-guias-list.js`

### Validacao
- `php -l` em `app/helpers/DeleteAuthorization.php` e `public/api/admin/feriados/delete.php`: sem erros.
- Lint nos JS alterados da Onda 5: sem erros.

---

## Onda 6 — Padronizacao global (plano de execucao + micro-lotes)

### Escopo
- Inventario e contratos registrados em `docs/00-foundation/varredura-global-padronizacao-plano-execucao.md`.
- Micro-lotes de baixo risco em JS (toast/delegacao e storage key Sala de Midias) e PHP (helper JSON + pilotos em deletes).

### JS
- `public/assets/js/shared/cartorio-task-list.js`: `notifyByKey` em desalocar passa a priorizar `App.Page.notifyByKey`.
- `public/assets/js/modules/audiencias/midias-list.js`, `midias-edit.js`: chave `salamidias.postReloadToast`; `midias-edit` usa `App.Page.createSecondClickGuard` quando disponivel.

### PHP
- Novo: `app/helpers/ApiJsonResponse.php` (`readJsonPayload`, `send`).
- Pilotos: `public/api/cartorio-guias/delete.php`, `public/api/cartorio-editais/delete.php` usando o helper sem alterar mensagens nem codigos HTTP.

### Governanca
- `docs/00-foundation/governanca-toast-app-page.md`: excecao estrutural `salamidias.postReloadToast`.

### Validacao (esta onda)
- Ver secao 5 de `varredura-global-padronizacao-plano-execucao.md` e comandos executados no PR/commit.
