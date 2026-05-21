# Especificação: Submódulo Cartas Expedidas (m4)

## 1. Visão geral

Submódulo do Módulo de Cartório (m4) para gestão de **cartas expedidas** (precatórias, rogatórias e de ordem expedidas pelo cartório). Controle do fluxo desde o cadastro até a juntada da carta devolvida.

## 2. Fluxo geral

1. Expedição da Carta  
2. Cadastro no sistema  
3. Juíza assina  
4. Gera PDF's da carta e documentos correlatos  
5. Envia documentos  
6. Juntada de termo de envio  
7. Juntada de informações do andamento  
8. Juntada da carta devolvida  

## 3. Entidade principal: Carta Expedida

- **Tabela:** `cartas_expedidas`
- **Identificador:** `id` (prefixo CEP, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, finalidade, cidade_destino, uf_destino, comarca_destino_id, prazo_dias, status, observacoes, created_at, updated_at.

## 4. Status da tarefa principal

- `pendente` — exibido como **Pendente** (status-pending)
- `em_andamento` — exibido como **Em andamento** (status-current)
- `concluída` — exibido como **Finalizada** (status-finished)
- `cancelada` — exibido como **Cancelada** (status-cancelled)

Quando todas as subtarefas estão concluídas, a tarefa é **Concluída** (status-finished).

## 5. Subtarefas (fluxo ativo)

Tipos ativos em `CartasExpedidasSubtarefasRepository` (ordens 1 → 3 → 6): `aguardando_assinatura`, `aguardando_envio`, `aguardando_devolucao`.

Tipos descontinuados (auto-concluídos em cartas antigas): `aguardando_gerar_pdf`, `aguardando_juntada_termo`, `aguardando_informacoes`.

O módulo **não** registra produtividade (`CartorioProdutividade`).

### 5.1 Subtarefa 1: Aguardando assinatura (ordem 1)

- **Status da subtarefa:** status-pending  
- **Status da tarefa:** Pendente (status-pending)  
- **Title da Tarefa:** Carta aguardando assinatura  
- **Title do Button da Subtarefa:** Informar assinatura  
- **Complemento:** —  
- **Status Timeline (sem fazer):** Aguardando assinatura pela juíza  
- **Complemento Timeline (sem fazer):** —  
- **Status Timeline (concluída):** Carta assinada pela juíza em [data] - [hora]  
- **Complemento Timeline (concluída):** Informação confirmada por [usuario] em [data] - [hora]  

### 5.2 Subtarefa 2: Aguardando envio (ordem 3)

- **Status da subtarefa:** status-pending  
- **Status da tarefa:** Em andamento (status-current)  
- **Complemento:** Select — E-mail, Malote Digital, PJe, eSAJ, ePROC  
- **Title da Tarefa:** Aguardando envio de Carta  
- **Title do Button da Subtarefa:** Informar envio  
- **Status Timeline (sem fazer):** Aguardando envio de documentos  
- **Complemento Timeline (sem fazer):** —  
- **Status Timeline (concluída):** Carta enviada para [sigla TJ - nome tribunal] - Comarca de [nome comarca envio] pelo/por [tipo envio]  
- **Complemento Timeline (concluída):** Envio realizado por [usuario] em [data] - [hora]  

### 5.3 Subtarefa 3: Devolução (ordem 6)

- **Status da subtarefa:** status-pending  
- **Status da tarefa:** Em andamento (status-current)  
- **Complemento:** Select — Não cumprida, Cumprida negativamente, Cumprida parcialmente, Cumprida integralmente  
- **Title da Tarefa:** Aguardando cumprimento  
- **Title do Button da Subtarefa:** Informar juntada de carta devolvida  
- **Status Timeline (sem fazer):** Aguardando devolução da Carta  
- **Complemento Timeline (sem fazer):** —  
- **Status Timeline (concluída):** Carta devolvida [complemento] (ex.: Cumprida integralmente)  
- **Complemento Timeline (concluída):** Juntada de carta devolvida realizada por [usuario] em [data] - [hora]  

### 5.4 Valores de resultado de devolução (backend)

Valores armazenados em `dados_especificos.resultado_devolucao` e rótulos exibidos:

| Valor (backend)              | Rótulo exibido           |
|------------------------------|--------------------------|
| `devolvida_sem_cumprimento`  | Não cumprida             |
| `cumprida_negativamente`    | Cumprida negativamente   |
| `cumprida_parcialmente`     | Cumprida parcialmente    |
| `cumprida_positivamente`    | Cumprida integralmente   |

## 6. Regras de validação

- Ao concluir uma subtarefa que exige complemento (método de envio, resultado devolução), o sistema deve bloquear a conclusão se o complemento não estiver preenchido.

## 7. Referências

- [CartasExpedidasSubtarefasRepository](../../../app/repositories/CartasExpedidasSubtarefasRepository.php) — constantes de tipo e ordem.
- Views: [cartorio-cartas-expedidas-list.php](../../../app/views/internal/cartorio-cartas-expedidas-list.php), [cartorio-cartas-expedidas-edit.php](../../../app/views/internal/cartorio-cartas-expedidas-edit.php).
- [cartorio-cartas-recebidas.md](cartorio-cartas-recebidas.md) — submódulo espelhado (cartas recebidas).
