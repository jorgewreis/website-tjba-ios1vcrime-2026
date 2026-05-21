# Especificação: Submódulo Cartas Recebidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas recebidas** (precatórias recebidas de outros tribunais). Fluxo atual: **Cadastro no sistema** → **Devolução da Carta ao Juízo Deprecante** → **Arquivamento definitivo com baixa**.

Etapas intermediárias antigas (ato ordinatório de cumprimento/devolução, expedição de mandado/ofício, certidão do oficial, comprovante de envio) foram **descontinuadas** no fluxo ativo; registros históricos permanecem no banco e são auto-concluídos na listagem/edição.

## 2. Entidade principal: Carta Recebida

- **Tabela:** `cartas_recebidas`
- **Identificador:** `id` (prefixo CPR, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, comarca_origem_id, cidade_origem, uf_origem, data_recebimento, finalidade, prazo_dias, status, observacoes, numero_processo_destino, vara_destino_id, vara_destino_nome (opcionais), created_at, updated_at.

## 3. Status da tarefa principal

- `aguardando cumprimento` — status inicial após cadastro (primeira subtarefa ativa em andamento)
- `em_andamento` — após conclusão da devolução (ordem 5), enquanto aguarda arquivamento
- `concluída`
- `cancelada`

(Exclusão padrão na listagem: concluída e cancelada; usuário filtra se quiser ver.)

## 4. Fluxo e subtarefas (3 etapas na UI)

1. **Cadastro no sistema** — linha fixa da timeline (sem botão; “Carta recebida e cadastrada”).
2. **Devolução da Carta / Envio do processo** (ordem 5, tipo `envio_processo`) — complemento: select (tipo de envio). Tribunal e Comarca de destino **não são informados pelo usuário**; vêm da comarca cadastrada na carta e são exibidos automaticamente na timeline.
3. **Arquivamento definitivo** (ordem 7, tipo `arquivamento_definitivo`)

### 4.1. Tabela de referência (títulos e complementos)

| Ordem | Tipo interno            | Título da tarefa (atual)                               | Título do botão                  | Complemento |
|-------|-------------------------|--------------------------------------------------------|----------------------------------|-------------|
| 5     | envio_processo          | Aguardando devolução da Carta ao Juízo Deprecante      | Informar devolução               | Select: E-mail, Malote Digital, PJe, eSAJ, ePROC |
| 7     | arquivamento_definitivo | Aguardando arquivamento definitivo com baixa           | Informar arquivamento definitivo | Nenhum |

### 4.2. Registros antigos

- Cartas com subtarefas das ordens 1–4 e 6 (tipos descontinuados) têm essas linhas **auto-concluídas** ao listar ou abrir a edição.
- A timeline de movimentações antigas (ordens 1–4, 6) continua exibindo textos históricos quando já concluídas.

## 5. Alocação

- Regra em `alocacoes_regras`: `ALLOCATE-CARTASRECEBIDAS` (um usuario_id).
- Tipos de alocado: `rule` (regra), `specific` (movimentação/subtarefa/edição), `all` (usuário principal no cadastro).
- Quem cadastra é alocado com tipo `all`; regra só grava tipo `rule` se for usuário diferente do cadastro.
- Máximo 5 alocados por carta; evitar duplicata (carta_id + usuario_id).

## 6. Permissões

- **Cancelar tarefa:** SERVIDOR, ADMIN, DIRETORA, MAGISTRADA. Botão oculto se status cancelada ou concluída.
- **Excluir tarefa:** ADMIN, DIRETORA, MAGISTRADA.
- **Alterar regra de alocação:** ADMIN, DIRETORA, MAGISTRADA (demais só visualização).

## 7. Referências

- [docs/00-foundation/escopo-produto.md](../../00-foundation/escopo-produto.md) — Submódulo Cartas Recebidas.
- [cartorio-cartas-expedidas.md](cartorio-cartas-expedidas.md) — especificação do submódulo Cartas Expedidas (fluxo e textos).
