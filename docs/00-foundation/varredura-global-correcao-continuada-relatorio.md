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
  - `public/api/cartorio-medidas-protetivas/delete.php`

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

## Validacao tecnica
- JS lints (arquivos alterados): sem erros.
- PHP sintaxe (`php -l`) dos arquivos alterados: sem erros.

## Pendencias para proxima rodada
- Expandir padronizacao de resposta JSON em endpoints de mesma familia (quando aprovado).
