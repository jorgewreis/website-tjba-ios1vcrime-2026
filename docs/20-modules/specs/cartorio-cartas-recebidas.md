# Especificação: Submódulo Cartas Recebidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas recebidas** (precatórias recebidas de outros tribunais). Fluxo: **Cadastro no sistema** → Ato ordinatório - Cumprimento → Expedição de documento → Juntada de certidão do oficial de justiça → Ato ordinatório - Devolução → Envio do processo (Devolução da Carta) → Juntada de termo de envio → Arquivamento definitivo.

## 2. Entidade principal: Carta Recebida

- **Tabela:** `cartas_recebidas`
- **Identificador:** `id` (prefixo CPR, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, comarca_origem_id, cidade_origem, uf_origem, data_recebimento, finalidade, prazo_dias, status, observacoes, numero_processo_destino, vara_destino_id, vara_destino_nome (opcionais), created_at, updated_at.

## 3. Status da tarefa principal

- `pendente`
- `em_andamento`
- `concluída`
- `cancelada`

(Exclusão padrão na listagem: concluída e cancelada; usuário filtra se quiser ver.)

## 4. Fluxo e subtarefas (8 etapas)

1. **Cadastro no sistema** — linha fixa da timeline (sem botão; “Carta recebida e cadastrada”).
2. **Ato ordinatório - Cumprimento** — subtarefa opcional; além do botão concluir, há botão **Cancelar** (marca a subtarefa como cancelada e libera a próxima).
3. **Expedição de documento**
4. **Juntada de certidão do oficial de justiça** — complemento: select (resultado do cumprimento).
5. **Ato ordinatório - Devolução**
6. **Devolução da Carta / Envio do processo** — complemento: select (tipo de envio). Tribunal e Comarca de destino **não são informados pelo usuário**; vêm da comarca cadastrada na carta (comarca_origem_id, cidade_origem, uf_origem) e são exibidos automaticamente na timeline.
7. **Juntada de termo de envio**
8. **Arquivamento definitivo**

Tipos internos (ordem 1 a 7): `ato_ordinatorio_cumprimento`, `expedicao_documento`, `juntada_certidao_oficial`, `ato_ordinatorio_devolucao`, `envio_processo`, `juntada_termo_envio`, `arquivamento_definitivo`.

### 4.1. Cancelar subtarefa (ordem 1)

- Apenas a subtarefa **Ato ordinatório - Cumprimento** (ordem 1) pode ser cancelada.
- Ao cancelar: a subtarefa recebe status `cancelada` e a próxima (Expedição de documento) passa a `em_andamento`.
- A tarefa principal permanece em `em_andamento`.

### 4.2. Tabela de referência (títulos e complementos)

| Ordem | Tipo interno               | Título da tarefa (atual)                          | Título do botão                     | Complemento |
|-------|----------------------------|---------------------------------------------------|-------------------------------------|-------------|
| 1     | ato_ordinatorio_cumprimento | Aguardando ato ordinatório de cumprimento         | Informar ato ordinatório de cumprimento | Nenhum. Botão Cancelar disponível. |
| 2     | expedicao_documento         | Aguardando expedição de documento para cumprimento | Informar expedição de documento     | Nenhum |
| 3     | juntada_certidao_oficial    | Aguardando cumprimento pelo oficial de justiça    | Informar juntada de certidão        | Select: Cumprido integralmente, Cumprido parcialmente, Cumprido negativamente, Não cumprido |
| 4     | ato_ordinatorio_devolucao   | Aguardando ato ordinatório de devolução           | Informar ato ordinatório de devolução | Nenhum |
| 5     | envio_processo              | Aguardando envio do processo ao juízo de origem  | Informar envio do processo           | Select: E-mail, Malote Digital, PJe, eSAJ, ePROC. Tribunal/comarca da carta (automático). |
| 6     | juntada_termo_envio         | Aguardando juntada de termo de envio              | Informar juntada                     | Nenhum |
| 7     | arquivamento_definitivo     | Aguardando arquivamento definitivo                | Informar arquivamento definitivo     | Nenhum |

## 5. Alocação

- Regra em `alocacoes_regras`: `ALLOCATE-CARTASRECEBIDAS` (um usuario_id).
- Tipos de alocado: `rule` (regra), `specific` (movimentação/subtarefa/edição), `all` (usuário principal no cadastro).
- Quem cadastra é alocado com tipo `all`; regra só grava tipo `rule` se for usuário diferente do cadastro.
- Máximo 5 alocados por carta; evitar duplicata (carta_id + usuario_id).

## 6. Permissões

- **Cancelar tarefa:** SERVIDOR, ADMIN, DIRETORA, MAGISTRADA. Botão oculto se status cancelada ou concluída.
- **Excluir tarefa:** ADMIN, DIRETORA, MAGISTRADA.
- **Alterar regra de alocação:** ADMIN, DIRETORA, MAGISTRADA (demais só visualização).

## 7. Migração

- Script `scripts/migrate-cartas-recebidas-7-subtarefas.php`: substitui as antigas 4 subtarefas pelas 7 novas em todas as cartas; carta passa a ter as 7 subtarefas com ordem 1 em `em_andamento` e demais `pendente`, e status da carta `em_andamento`. Executar uma vez após o deploy.

## 8. Referências

- [docs/00-foundation/escopo-produto.md](../../00-foundation/escopo-produto.md) — Submódulo Cartas Recebidas.
- [cartorio-cartas-expedidas.md](cartorio-cartas-expedidas.md) — especificação do submódulo Cartas Expedidas (fluxo e textos).
