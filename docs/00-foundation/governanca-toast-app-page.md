# Governanca de Toast em JS (App.Page)

## Objetivo

Padronizar o uso de notificacoes e fila de toast pos-reload no frontend JavaScript, adotando `App.Page` como fonte unica de verdade para reduzir duplicacoes, regressao funcional e inconsistencias entre modulos.

## API canonica

- `App.Page.notify(type, title, message)`
- `App.Page.queuePostReloadToast(typeOrPayload, title, message, storageKey)`
- `App.Page.flushPostReloadToast(storageKey, defaults)`
- `App.ToastCatalog.load(options)`
- `App.ToastCatalog.resolve(key, vars, options)`
- `App.ToastCatalog.notifyByKey(key, vars, options)`

## Regras operacionais

- Novo codigo nao deve criar `notify`, `queuePostReloadToast` ou `flushPostReloadToast` local.
- Em migracao, wrappers locais sao aceitos apenas como adapter temporario.
- Excecoes precisam de justificativa, owner e condicao de saida.
- Dominio pode manter `storageKey` proprio quando houver isolamento funcional necessario.

## Inventario de classificacao (varredura inicial)

### Delegar direto para App.Page (baixo risco)

- `public/assets/js/shared/record-edit-common.js`
- `public/assets/js/shared/cartorio-task-list.js`
- `public/assets/js/shared/gme-list.js`
- `public/assets/js/shared/oficios-edit.js`
- `public/assets/js/modules/audiencias/midias-list.js`
- `public/assets/js/modules/audiencias/pauta-list.js`
- `public/assets/js/modules/audiencias/pauta-list-estudantes.js`

### Delegar com adapter temporario (medio risco)

- `public/assets/js/shared/oficios-list.js`
- `public/assets/js/shared/gme-create.js`
- `public/assets/js/shared/cartorio-simple-edit.js`
- `public/assets/js/shared/cartorio-guias-edit.js`
- `public/assets/js/shared/cartorio-cartas-recebidas-edit.js`
- `public/assets/js/modules/audiencias/midias-edit.js`

### Excecoes explicitas aprovadas para manter comportamento adicional

1. `public/assets/js/shared/oficios-list.js`
- **Motivo:** `flushPostReloadToast` participa de fluxo com logica extra de destaque visual (`highlightField`) alem do toast.
- **Escopo da excecao:** manter adapter local apenas para composicao com highlight.
- **Condicao de saida:** extrair estrategia de highlight para helper dedicado e delegar 100% para `App.Page.flushPostReloadToast`.
- **Owner sugerido:** frontend-interno.

2. Namespaces de storage key por dominio de audiencias
- **Arquivos afetados:** `public/assets/js/modules/audiencias/pauta-list.js`, `public/assets/js/modules/audiencias/pauta-list-estudantes.js`.
- **Motivo:** isolamento de fila por contexto funcional (`salapauta.postReloadToast`, `estudapauta.postReloadToast`).
- **Escopo da excecao:** manter apenas diferenca de `storageKey`, sem alterar API canonica.
- **Condicao de saida:** nao se aplica (excecao estrutural valida).
- **Owner sugerido:** modulo-audiencias.

## Backlog de migracao recomendado (ondas pequenas)

1. Onda 1 (baixo risco): `record-edit-common`, `cartorio-task-list`, `gme-list`.
2. Onda 2 (baixo/medio): `pauta-list`, `pauta-list-estudantes`, `midias-list`.
3. Onda 3 (medio): `cartorio-simple-edit`, `cartorio-guias-edit`, `cartorio-cartas-recebidas-edit`, `gme-create`.
4. Onda 4 (excecao controlada): `oficios-list` com separacao de highlight.

## Checklist de revisao por PR

- Nao criou nova funcao local de notify/fila.
- Usa API canonica `App.Page`.
- Preserva mensagens de negocio e tipo de toast.
- Preserva comportamento pos-reload.
- Excecoes (se houver) foram registradas neste documento.

## Historico

- 2026-04-28: documento criado com base em varredura global inicial de duplicacoes JS.
- 2026-04-28: adicionada camada central `App.ToastCatalog` (consumo do endpoint `/api/config/toast-messages.php`) sem migracao funcional de telas.
