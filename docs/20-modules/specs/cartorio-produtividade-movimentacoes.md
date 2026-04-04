# Catálogo de movimentações de produtividade (cartório)

Este documento registra as movimentações que **geram lançamento automático** na produtividade do cartório.
O catálogo técnico canônico de `event_code` está em:

- `app/config/cartorio-produtividade-registry.php`
- `app/config/cartorio-produtividade-list-texts.php`

## Regras funcionais (fechadas)

- Titularidade do lançamento: sempre usuário da sessão/ação.
- `update` genérico: não gera produtividade automática.
- `desalocar`: não gera produtividade automática.
- `cancel`/`delete`: não geram novo positivo; disparam espelho de estorno de saldo (quando houver saldo positivo prévio).
- Estorno: idempotente por contexto de referência (evita débito duplicado para o mesmo cancelamento/exclusão).

## Eventos por módulo

- `cartorio-guias`
  - `cartorio-guias.create`
  - `cartorio-guias.subtarefa.*`
- `cartorio-oficios`
  - `cartorio-oficios.create`
  - `cartorio-oficios.subtarefa.*`
- `cartorio-medidas-protetivas`
  - `cartorio-medidas-protetivas.create`
  - `cartorio-medidas-protetivas.subtarefa.*`
- `cartorio-editais`
  - `cartorio-editais.create`
  - `cartorio-editais.subtarefa.*`
- `cartorio-cejusc`
  - `cartorio-cejusc.create`
  - `cartorio-cejusc.subtarefa.*`
- `cartorio-cartas-recebidas`
  - `cartorio-cartas-recebidas.create`
  - `cartorio-cartas-recebidas.subtarefa.*`
- `cartorio-cartas-expedidas`
  - `cartorio-cartas-expedidas.create`
  - `cartorio-cartas-expedidas.subtarefa.*`
- `cartorio-ceapa`
  - `cartorio-ceapa.create`

## Observações de manutenção

- Qualquer novo `event_code` deve ser incluído nos dois arquivos de configuração (`registry` e `list-texts`).
- Os textos exibidos na lista de produtividade devem ser ajustados somente em `app/config/cartorio-produtividade-list-texts.php`.
- Para regra SQL de produtividade, usar o seed em `docs/database/seed-cartorio-produtividade-regras.sql`.
