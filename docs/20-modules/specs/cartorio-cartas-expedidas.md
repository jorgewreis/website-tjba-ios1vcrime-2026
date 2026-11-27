# Especificação: Submódulo Cartas Expedidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas expedidas** (precatórias, rogatórias e de ordem expedidas pelo cartório). Fluxo de **duas etapas**: envio da carta e juntada da devolução.

## 2. Fluxo geral

1. Cadastro no sistema  
2. **Enviar** — método de envio (e-mail, malote, PJe, etc.)  
3. **Juntar** — resultado da devolução da carta  

## 3. Entidade principal: Carta Expedida

- **Tabela:** `cartas_expedidas`
- **Identificador:** `id` (prefixo CPE, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, finalidade, cidade_destino, uf_destino, comarca_destino_id, prazo_dias, status, observacoes, created_at, updated_at.

## 4. Status da tarefa principal

| Status (BD) | Exibição (UI) | Classe CSS |
|-------------|---------------|------------|
| `em_andamento` | Aguardando envio | status-current |
| `aguardando envio` | Aguardando envio | status-current |
| `aguardando cumprimento` | Aguardando cumprimento | status-pending |
| `concluída` | Finalizada | status-finished |
| `cancelada` | Cancelada | status-cancelled |

A UI **não** exibe o rótulo genérico «Em andamento» nem o valor bruto `em_andamento` ao usuário.

Quando as duas subtarefas estão concluídas, a tarefa passa a **concluída**.

Quando o status visível é diferente de **Aguardando envio**, a linha principal mostra o PNG `Button-Previous--Streamline-Ultimate.png` antes do botão de avançar. A ação reinicia o registro existente: principal em `em_andamento` (Aguardando envio), envio em `em_andamento` e devolução em `pendente`. O reinício é transacional e preserva os eventos de conclusão já registrados no dashboard.

## 5. Subtarefas (2 etapas)

Tipos em `CartasExpedidasSubtarefasRepository`: `aguardando_envio` (ordem **1**), `aguardando_devolucao` (ordem **2**).

O módulo **não** registra produtividade (`CartorioProdutividade`).

### 5.1 Envio (ordem 1)

- **Status da subtarefa (ativa):** `em_andamento` — span «Aguardando envio de documentos» (status-current)  
- **Status da tarefa:** `em_andamento` — badge «Aguardando envio»  
- **Title da Tarefa (lista):** Aguardando envio  
- **Botão:** **Enviar**  
- **Complemento:** Select — E-mail, Malote Digital, PJe, eSAJ, ePROC  
- **Timeline (pendente):** Aguardando envio de documentos  
- **Timeline (concluída):** Carta enviada para [tribunal]… / Envio realizado por [usuario] em [data]  

### 5.2 Devolução (ordem 2)

- **Status da subtarefa (ativa):** `pendente` — span «Aguardando cumprimento» (status-pending)  
- **Status da tarefa:** `aguardando cumprimento` — badge «Aguardando cumprimento» (status-pending)  
- **Title da Tarefa (lista):** Aguardando cumprimento  
- **Botão:** **Juntar**  
- **Complemento:** Select — Não cumprida, Cumprida negativamente, Cumprida parcialmente, Cumprida integralmente  
- **Timeline (pendente):** Aguardando cumprimento  
- **Timeline (concluída):** Carta devolvida [resultado] / Juntada de carta devolvida realizada por [usuario] em [data]  

### 5.3 Valores de resultado de devolução (backend)

| Valor (backend)              | Rótulo exibido           |
|------------------------------|--------------------------|
| `devolvida_sem_cumprimento`  | Não cumprida             |
| `cumprida_negativamente`    | Cumprida negativamente   |
| `cumprida_parcialmente`     | Cumprida parcialmente    |
| `cumprida_positivamente`    | Cumprida integralmente   |

## 6. Filtros da listagem

- Aguardando envio (`em_andamento` + `aguardando envio`)  
- Aguardando cumprimento  
- Finalizada / Cancelada  

## 7. Regras de validação

- Ao concluir envio ou devolução, o complemento obrigatório (método de envio ou resultado) deve estar preenchido.

## 8. Migração de dados legados

Script único: `scripts/migrate-cartas-expedidas-fluxo-2-etapas.php` — remove subtarefas antigas, renumerar ordens 1/2 e normaliza status de tarefas abertas.

## 9. Textos de UI (fonte única)

- [CartasExpedidasUiCopy.php](../../../app/helpers/CartasExpedidasUiCopy.php) — títulos, timeline, botões Enviar/Juntar, badges.

## 10. Referências

- [CartasExpedidasSubtarefasRepository](../../../app/repositories/CartasExpedidasSubtarefasRepository.php)
- A única interface de listagem abre em `/frame.php?page=cartasexpedidas`. O endereço `cartasexpedidasnova` continua compatível, com a mesma tela e os mesmos filtros, sem item próprio no menu.
- Views: [cartorio-cartas-expedidas-list.php](../../../app/views/internal/cartorio-cartas-expedidas-list.php), [cartorio-cartas-expedidas-edit.php](../../../app/views/internal/cartorio-cartas-expedidas-edit.php)
- [cartorio-cartas-recebidas.md](cartorio-cartas-recebidas.md)
