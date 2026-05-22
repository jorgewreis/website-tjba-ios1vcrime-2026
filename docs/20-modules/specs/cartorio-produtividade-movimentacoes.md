# Catálogo de movimentações de produtividade (cartório)

Este documento registra as movimentações que **geram lançamento automático** na produtividade do cartório.
O catálogo técnico canônico de `event_code` está em:

- `app/config/cartorio-produtividade-registry.php`
- `app/config/cartorio-produtividade-list-texts.php`

## Regras funcionais (fechadas)

- Titularidade do lançamento: sempre usuário da sessão/ação (`usuario_id` nas APIs de concluir subtarefa, via `CartorioSubtarefaUsuarioSessao`; fallback em `CartorioProdutividade::record` se a sessão estiver ativa).
- `update` genérico: não gera produtividade automática.
- `desalocar`: não gera produtividade automática.
- `cancel`/`delete`: não geram novo positivo; disparam espelho de estorno de saldo (quando houver saldo positivo prévio).
- Estorno: idempotente por contexto de referência (evita débito duplicado para o mesmo cancelamento/exclusão).

## Mapa: subtarefas no repositório × `event_code`

Cada linha é o valor de `tipo` persistido no repositório de subtarefas (ou operação de criação). O `event_code` é `cartorio-<módulo>.subtarefa.<tipo>` ou `cartorio-<módulo>.create`.

| Módulo | Operação / `tipo` (subtarefa) | `event_code` |
|--------|-------------------------------|--------------|
| **Guias** | *(create)* | `cartorio-guias.create` |
| Guias | `detalhamento_informacoes` | `cartorio-guias.subtarefa.detalhamento_informacoes` |
| Guias | `calculo_pena_multa` | `cartorio-guias.subtarefa.calculo_pena_multa` |
| Guias | `expedicao_guia` | `cartorio-guias.subtarefa.expedicao_guia` |
| Guias | `aguardando_assinatura` | `cartorio-guias.subtarefa.aguardando_assinatura` |
| Guias | `juntada_automatica` | `cartorio-guias.subtarefa.juntada_automatica` |
| Guias | `gerar_pdf` | `cartorio-guias.subtarefa.gerar_pdf` |
| Guias | `envio_guia` | `cartorio-guias.subtarefa.envio_guia` |
| Guias | `juntada_termo_envio` | `cartorio-guias.subtarefa.juntada_termo_envio` |
| **Cartas expedidas** | *(create)* | `cartorio-cartas-expedidas.create` |
| Cartas expedidas | `aguardando_envio` | `cartorio-cartas-expedidas.subtarefa.aguardando_envio` |
| Cartas expedidas | `aguardando_devolucao` | `cartorio-cartas-expedidas.subtarefa.aguardando_devolucao` |
| **Cartas recebidas** | *(create)* | `cartorio-cartas-recebidas.create` |
| Cartas recebidas | `ato_ordinatorio_cumprimento` | `cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_cumprimento` |
| Cartas recebidas | `expedicao_documento` | `cartorio-cartas-recebidas.subtarefa.expedicao_documento` |
| Cartas recebidas | `juntada_certidao_oficial` | `cartorio-cartas-recebidas.subtarefa.juntada_certidao_oficial` |
| Cartas recebidas | `ato_ordinatorio_devolucao` | `cartorio-cartas-recebidas.subtarefa.ato_ordinatorio_devolucao` |
| Cartas recebidas | `envio_processo` | `cartorio-cartas-recebidas.subtarefa.envio_processo` |
| Cartas recebidas | `juntada_termo_envio` | `cartorio-cartas-recebidas.subtarefa.juntada_termo_envio` |
| Cartas recebidas | `arquivamento_definitivo` | `cartorio-cartas-recebidas.subtarefa.arquivamento_definitivo` |
| **Ofícios** | *(create)* | `cartorio-oficios.create` |
| Ofícios | `enviar_oficio` | `cartorio-oficios.subtarefa.enviar_oficio` |
| Ofícios | `juntar_termo_envio` | `cartorio-oficios.subtarefa.juntar_termo_envio` |
| **Medidas protetivas** | *(create)* | `cartorio-medidas-protetivas.create` |
| Medidas protetivas | `cadastro_partes_pje` | `cartorio-medidas-protetivas.subtarefa.cadastro_partes_pje` |
| Medidas protetivas | `expedicao_mandados_intimacao` | `cartorio-medidas-protetivas.subtarefa.expedicao_mandados_intimacao` |
| Medidas protetivas | `cadastro_bnmp` | `cartorio-medidas-protetivas.subtarefa.cadastro_bnmp` |
| **Editais** | *(create)* | `cartorio-editais.create` |
| Editais | `expedicao_edital` | `cartorio-editais.subtarefa.expedicao_edital` |
| Editais | `envio_djen` | `cartorio-editais.subtarefa.envio_djen` |
| Editais | `certificacao_publicacao` | `cartorio-editais.subtarefa.certificacao_publicacao` |
| Editais | `certificacao_decurso_prazo` | `cartorio-editais.subtarefa.certificacao_decurso_prazo` |
**Guias — aliases legados** (normalizados em `GuiasService::canonicalTipoSubtarefaGuia` antes do `event_code`): `geracao_pdf` / `pdf` → `gerar_pdf`; `expedir_guia` / `expedicao` → `expedicao_guia`; etc.

**PDF (guias, cartas expedidas):** pontos efetivos = 1 + 0,1 × documentos (`CartorioProdutividade::pontosFormulaGeracaoPdf`); regra em BD é apenas referência (`pontos_calculados_override`).

## Estorno visível na lista

| `event_code` / uso | Config de texto |
|--------------------|-----------------|
| Espelho de estorno | `cartorio-interno.estorno-saldo` |

## Observações de manutenção

- Qualquer novo `event_code` deve ser incluído nos dois arquivos de configuração (`registry` e `list-texts`) **e** em `produtividade_regras` (seed).
- Os textos exibidos na lista de produtividade devem ser ajustados somente em `app/config/cartorio-produtividade-list-texts.php`.
- Para regra SQL de produtividade, usar o seed em `docs/database/seed-cartorio-produtividade-regras.sql` e, em ambientes já populados, `seed-cartorio-produtividade-regras-update-pontos.sql` quando necessário.
