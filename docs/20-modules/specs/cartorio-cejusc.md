# Especificação: Submódulo Cejusc (Cartório)

## 1. Visão geral

Submódulo do Módulo de Cartório para gestão de **tarefas Cejusc**: envio da decisão ao Cejusc (setor de fiscalização) para que o denunciado participe de curso restaurativo. O escopo cobre apenas a parte da Vara Crime: **(1) Enviar a decisão** e **(2) Juntar o comprovante de envio**. O retorno do Cejusc (ofício sobre cumprimento) fica fora do escopo.

## 2. Entidade principal: Tarefa Cejusc

- **Tabela:** `cejusc_tarefas`
- **Identificador:** `id` (prefixo CJT, 30 caracteres)
- **Campos principais:** processo_origem_id, numero_processo_origem, reu_id (FK para reus.id_reu), status, created_at, updated_at.
- **Vínculos:** Uma tarefa está vinculada a **um processo** (por número) e **um réu** (denunciado que deve fazer o curso). Utiliza-se a tabela existente `reus` e o vínculo em `processoReus` (numero + id_reu), reutilizável em outros módulos (ex.: Guias).

## 3. Status da tarefa principal

- `pendente`
- `em_andamento`
- `concluída`
- `cancelada`

## 4. Subtarefas (ordem fixa)

1. **Enviar Decisão** — tipo interno: `enviar_decisao` (ordem 1)  
2. **Juntar termo de envio** — tipo interno: `juntar_termo_envio` (ordem 2)

Fluxo: ao concluir a subtarefa 1, a subtarefa 2 é ativada/criada; ao concluir a 2, a tarefa principal passa a `concluída`.

## 5. Alocação

- Regra em `alocacoes_regras`: `ALLOCATE-CEJUSC` (um usuario_id).
- Tipos de alocado: `rule` (regra), `specific`, `all` (quem cadastrou).
- Tabela `cejusc_alocados`: cejusc_tarefa_id, usuario_id, tipo. Limite de alocados por tarefa (ex.: 5).

## 6. Permissões

- **Cancelar tarefa:** SERVIDOR, ADMIN, DIRETORA, MAGISTRADA. Botão oculto se status cancelada ou concluída.
- **Excluir tarefa:** ADMIN, DIRETORA, MAGISTRADA.
- **Alterar regra de alocação:** ADMIN, DIRETORA, MAGISTRADA (botão "Alterar regra de alocação" na create e link para rules).

## 7. Cadastro

- **Campos obrigatórios:** Número do processo, Nome do réu.
- Sem DataJud obrigatório para classe; sem cidade/estado/finalidade. Classe do processo pode ser exibida na listagem/edição se existir no BD.
- Réu: cadastro/busca via ReuManager (reus); vínculo ao processo em processoReus; tarefa grava reu_id.

## 8. Referências

- Plano de implementação: módulo Cejusc (views, repositórios, serviço, APIs).
- Implementação espelhada em Cartas Recebidas (listagem, create, edit, rules, alocação).
