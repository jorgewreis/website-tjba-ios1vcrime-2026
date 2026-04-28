# Varredura Guiada - Fase 1 (JS Toast/Notificacao)

## Escopo executado
- Dominio: JavaScript de toast/notificacao.
- Foco: reduzir duplicacao de wrapper `notifyByKey` e reforcar fonte unica em `App.Page`.
- Regra aplicada: nenhuma remocao definitiva, apenas centralizacao com fallback preservado.

## Inventario (evidencia tecnica)
- `function notifyByKey(...)` repetido em diversos modulos JS.
- `function notify(type, title, message)` repetido em diversos modulos JS.
- Uso de `queuePostReloadToast(...)` e `flushPostReloadToast(...)` espalhado em listagens/edicoes.
- `App.Page.notifyByKey` ainda com baixa adocao direta antes desta fase.

## Classificacao e decisao aplicada
- Item: wrappers locais `notifyByKey` duplicados.
- Risco: baixo.
- Decisao do usuario: migracao em lote maior (todos os arquivos-alvo desta frente).
- Estrategia: cada wrapper local passa a priorizar `App.Page.notifyByKey(...)`; fallback local mantido para `ToastCatalog` e `notify(...)`.

## Arquivos alterados
- `public/assets/js/modules/produtividade/produtividade-list.js`
- `public/assets/js/modules/audiencias/pauta-list.js`
- `public/assets/js/modules/audiencias/pauta-create.js`
- `public/assets/js/modules/audiencias/atas-list.js`
- `public/assets/js/modules/audiencias/atas-estudantes.js`
- `public/assets/js/modules/audiencias/midias-create.js`
- `public/assets/js/modules/audiencias/midias-edit.js`
- `public/assets/js/modules/audiencias/midias-list.js`
- `public/assets/js/modules/audiencias/pauta-edit.js`
- `public/assets/js/modules/audiencias/pauta-parte-edit.js`

## Garantias de compatibilidade
- Se `App.Page.notifyByKey` estiver indisponivel, o fluxo continua via `App.ToastCatalog.notifyByKey`.
- Se catalogo indisponivel/falhar, o fallback local de `notify(...)` permanece.
- Nenhuma alteracao de banco de dados.
- Nenhuma exclusao de arquivo/codigo nesta fase.

## Validacao
- Lint dos arquivos JS alterados: sem erros.

## Proximo passo recomendado
- Expandir a mesma consolidacao para wrappers de `queuePostReloadToastByKey` e `notify` onde ainda houver duplicacao local, mantendo o mesmo padrao de decisao tecnica antes de cada alteracao.
