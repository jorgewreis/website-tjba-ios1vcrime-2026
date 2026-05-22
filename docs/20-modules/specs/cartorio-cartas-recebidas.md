# Especificação: Submódulo Cartas Recebidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas recebidas** (precatórias recebidas de outros tribunais). Fluxo: **Cadastro** → **Cumprimento** → **Devolução**.

Textos canônicos: [`app/helpers/CartasRecebidasUiCopy.php`](../../../app/helpers/CartasRecebidasUiCopy.php).

## 2. Entidade principal: Carta Recebida

- **Tabela:** `cartas_recebidas`
- **Identificador:** `id` (prefixo CPR, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, comarca_origem_id, cidade_origem, uf_origem, data_recebimento, finalidade, prazo_dias, status, observacoes, created_at, updated_at.

## 3. Status da tarefa principal

| Status | Quando |
|--------|--------|
| `aguardando cumprimento` | Após cadastro; subtarefa 1 (`cumprimento`) em andamento |
| `aguardando devolução` | Após concluir cumprimento; subtarefa 2 (`devolucao`) em andamento |
| `concluída` | Após concluir devolução |
| `cancelada` | Cancelamento da tarefa |

(Exclusão padrão na listagem: concluída e cancelada; usuário filtra se quiser ver.)

## 4. Subtarefas (2 etapas ativas)

| Ordem | Tipo | Título da tarefa (UI) | Botão na listagem | Complemento |
|-------|------|------------------------|-------------------|-------------|
| 1 | `cumprimento` | Aguardando cumprimento | Cumprir | Nenhum |
| 2 | `devolucao` | Aguardando devolução | Devolver | Nenhum |

- Cadastro cria as duas subtarefas: ordem 1 `em_andamento`, ordem 2 `pendente`.
- Concluir ordem 1: tarefa → `aguardando devolução`; ordem 2 → `em_andamento`.
- Concluir ordem 2: tarefa → `concluída`.

## 5. Timeline (edição)

- Cadastro: «Carta recebida e cadastrada».
- Duas linhas de subtarefa com textos de `CartasRecebidasUiCopy` / `CartorioTimelineHelper` (`cartas_recebidas`).
- Concluídas: «Cumprimento registrado» e «Carta devolvida ao juízo deprecante».

## 6. Migração de dados legados

Script único: [`scripts/migrate-cartas-recebidas-2-subtarefas.php`](../../../scripts/migrate-cartas-recebidas-2-subtarefas.php) — remove subtarefas antigas e recria ordens 1–2; normaliza `em_andamento` da tarefa principal para `aguardando devolução`.

## 7. Alocação

- Regra em `alocacoes_regras`: `ALLOCATE-CARTASRECEBIDAS` (um usuario_id).
- Tipos de alocado: `rule`, `specific`, `all`.
- Máximo 5 alocados por carta.

## 8. Permissões

- **Cancelar tarefa:** SERVIDOR, ADMIN, DIRETORA, MAGISTRADA.
- **Excluir tarefa:** ADMIN, DIRETORA, MAGISTRADA.
- **Alterar regra de alocação:** ADMIN, DIRETORA, MAGISTRADA.

## 9. Referências

- [cartorio-cartas-expedidas.md](cartorio-cartas-expedidas.md) — padrão de helper de textos (`CartasExpedidasUiCopy`).
- [docs/00-foundation/escopo-produto.md](../../00-foundation/escopo-produto.md)
