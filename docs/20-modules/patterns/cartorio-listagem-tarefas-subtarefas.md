# Padrão: listagem cartório — tarefa principal e subtarefas

Referência alinhada ao módulo **Sala de Audiências — Mídias** e aplicada de forma consistente aos fluxos do **cartório** com tarefa principal + subtarefas.

## Regras de status da tarefa principal

| Situação | Status da tarefa principal |
|----------|----------------------------|
| Nenhuma subtarefa concluída (etapas ainda não finalizadas) | **Pendente** |
| Pelo menos uma subtarefa concluída e ainda não todas | **Em andamento** |
| Todas as subtarefas concluídas (conforme regras do módulo) | **Finalizada** — no banco/código pode aparecer como `concluída`, `concluida`, `finalizada` etc., conforme o módulo |

- Ao **cadastrar** a tarefa principal, o status inicial deve ser **Pendente** (não “Em andamento” só porque a primeira subtarefa está liberada para trabalho).
- A primeira subtarefa pode permanecer em **em andamento** na tabela de subtarefas enquanto o usuário não concluir a etapa; isso não antecipa o status da tarefa principal para “Em andamento”.

## Listagem (filtro de status)

- Com **nenhum** status selecionado no filtro (visão padrão), a lista **não** deve exibir tarefas **finalizadas** (nem canceladas, quando o módulo já tratava assim).
- O usuário vê tarefas finalizadas **somente** ao escolher explicitamente o status “Finalizada” / “Concluída” (rótulo conforme a tela).
- Nos selects de filtro, a opção padrão deve deixar isso explícito, por exemplo: **“Todas exceto finalizadas”** (em vez de “Mostrar todos”).

Implementação típica: repositório com `NOT IN` para status final + cancelado quando `status` do filtro está vazio; totais por status para os parênteses podem continuar contando todas as linhas (incluindo finalizadas).

## Código

- Helper opcional: `CartorioTarefaStatusPrincipalHelper::principalStatusPadrao()` — calcula o status principal a partir de um array de subtarefas e da lista de valores que contam como “concluído”. Em **Guias**, incluir `pulada` nessa lista quando a etapa for ignorada pelo fluxo (equivalente a etapa encerrada para aquele passo).
- Módulos **excluídos** deste alinhamento geral de listagem/status: **produtividade** (telas de produtividade) e **ofícios do cartório** (`cartorio-oficios-*`), por decisão de produto.

## Referência cruzada

- Regras de alocação e telas create/edit seguem contratos existentes (`cartorio-alocacoes-padrao`, UI create/edit).
- Toast e botões: regras Cursor do repositório.
