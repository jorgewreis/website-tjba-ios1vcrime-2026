# Especificação: Submódulo Cartas Recebidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas recebidas** (precatórias recebidas de outros tribunais). Fluxo: conferência inicial → expedição de mandado → certificação → PDF → devolução à origem → arquivamento (com regra especial vinculada à audiência quando aplicável).

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

## 4. Subtarefas (ordem fixa)

1. Conferência inicial  
2. Expedição de mandado  
3. Certificação  
4. PDF  
5. Devolução à origem  
6. Arquivamento  

Tipos internos (ex.): `conferencia_inicial`, `expedicao_mandado`, `certificacao`, `pdf`, `devolucao_origem`, `arquivamento`.

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
- Implementação espelhada em Cartas Expedidas (views, repositórios, serviço, APIs).
