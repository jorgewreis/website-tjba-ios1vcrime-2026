# Onda 3 — Relatorio de Execucao (PHP + JS)

## Escopo executado
- Varredura global de duplicacao e candidatos a codigo possivelmente sem uso em `public/assets/js`, `app/`, `public/api` e `app/views`.
- Classificacao por risco/impacto com backlog tecnico de unificacao gradual.
- Execucao de micro-lote de baixo risco com fallback preservado.
- Registro de itens sensiveis sem remocao automatica.

## Inventario consolidado (evidencias)

### JS — Duplicacoes relevantes
- `function notifyByKey(...)`: 31 ocorrencias em arquivos de modulo/shared (padrao repetido de fallback para catalogo).
- `function postJson(...)`: 3 ocorrencias principais (alem de wrappers), com assinaturas semelhantes.
- Confirmacao destrutiva (`createSecondClickGuard`/`requireSecondClick`): repeticoes em varios editores, parte ja centralizada em `App.Page` e `App.RecordEditCommon`.
- Navegacao direta com `location.href`: padrao disseminado em muitos arquivos; parte dos fluxos ja migrou para `data-nav-href` + `navigate-buttons.js`.

### PHP — Duplicacoes relevantes
- Regras de permissao de exclusao com arrays de perfis repetidos em views/API.
- Blocos de cancelamento/exclusao em endpoints com estrutura muito parecida (auth, validacao de id, chamada de service, payload JSON).
- Regras de produtividade espelhadas em multiplos services e endpoints (com helper central `CartorioProdutividade` ja em uso).

### Candidatos a codigo possivelmente sem uso (status atualizado em auditoria posterior)
- `public/assets/js/shared/navigation.js`: **removido** depois de confirmada ausencia de referencias.
- `app/services/Produtividade.php`: **removido** apos auditoria do modulo Produtividade (sem referencias); fluxo canonico em `ProdutividadeService`.

## Classificacao de risco e backlog

### Baixo risco (executar primeiro)
1. Centralizar wrappers `notifyByKey` em `App.Page`.
2. Migrar consumidores para `App.Page.notifyByKey` com fallback mantido.
3. Marcar artefatos legados com comentario de compatibilidade/deprecacao (sem excluir).

### Medio risco (executar em micro-lotes)
1. Unificar `postJson` em helper unico e adaptar chamadas legadas por modulo.
2. Consolidar regras repetidas de permissao em helper PHP comum para endpoints internos.
3. Reduzir duplicacao de notificacoes por modulo com catalogo central + chaves padronizadas.

### Alto risco (depende de decisao explicita antes de remover)
1. ~~Remover definitivamente `navigation.js`~~ — feito em auditoria posterior (sem referencias).
2. ~~Remover `app/services/Produtividade.php`~~ — feito apos auditoria do modulo Produtividade.
3. Refatorar blocos de API compartilhados para camada comum (risco de regressao de contrato HTTP).

## Decisoes guiadas (sensivel)
- Nenhuma remocao definitiva foi executada nesta etapa.
- Itens sensiveis foram retidos e marcados para decisao explicita antes de exclusao.
- Cada item sensivel manteve estrategia de rollback simples: restaurar referencia anterior sem impacto de dados.

## Micro-lote aplicado (baixo risco)

### 1) Nova centralizacao em `App.Page`
- Arquivo: `public/assets/js/shared/page-helpers.js`
- Acao: adicao de `notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage)` em `App.Page`.
- Garantia: fallback para `notify(...)` quando catalogo nao estiver disponivel.

### 2) Consumo migrado em modulo de Audiencias
- Arquivo: `public/assets/js/modules/audiencias/pauta-edit-prioridades.js`
- Acao: `notifyByKey` local passa a priorizar `App.Page.notifyByKey`.
- Garantia: mantido fallback local para `App.ToastCatalog.notifyByKey` e `notify(...)`.

### 3) Legado identificado na Onda 3 (limpeza aplicada depois)
- `navigation.js` e `app/services/Produtividade.php` foram tratados em auditoria guiada posterior (remocao apos ausencia de referencias).

## Validacao executada
- Diagnostico IDE (`ReadLints`) nos arquivos JS alterados -> sem erros.

## Status final da Onda 3 (plano anexado)
- Inventario global: concluido.
- Classificacao e priorizacao: concluido.
- Decisoes guiadas (sensiveis): concluido sem remocao automatica.
- Micro-lotes de baixo risco: concluido.
- Relatorio e rastreabilidade: concluido neste documento.
