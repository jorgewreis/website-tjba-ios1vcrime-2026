# Rastreamento de usuário em tarefas (listagens)

Contrato da **última coluna** das páginas `*-list.php` que usam `_registro_metadata.php` (`container-data-create`).

## Estrutura da coluna

| Linha (DOM) | Texto | Origem |
|-------------|-------|--------|
| `.data-create-user` | `Cadastrado por {Nome}` | `CriadoPorNome` (SQL `sqlSelectAuditoriaExtra`) |
| `.data-create-date` | `Em DD/MM/AAAA às HH:MM:SS` | `created_at` da tarefa |
| `.data-update-date` | `Atualizado em DD/MM/AAAA por {Nome}` ou `Cancelado em DD/MM/AAAA por {Nome}` (sem hora) | Ver regras abaixo |

Formatação centralizada em `RegistroMetadataHelper::formatarLinhaAutor` e `formatarEventoComAutor` (nome compactado com `compactarNome`).

## Regra de atualização (módulos cartório com subtarefas)

Módulos com `movement_from_subtarefa` em `RegistroMetadataHelper::MODULES`:

- cartas_recebidas, cartas_expedidas, guias, editais, medidas_protetivas

**Atualização na coluna** = última subtarefa com `status = concluida` em `SubtarefasAll`, usando:

- data: `updated_at` da subtarefa
- autor: `dados_especificos` (`nome` / `usuario_id` + lookup), via `resolveUltimaSubtarefaConcluida`

Salvar o formulário da tarefa pai **sem** concluir subtarefa **não** exibe linha "Atualizado em" (mesmo que `updated_at` da tarefa mude).

## Gravação em subtarefas

Toda conclusão de subtarefa (API ou service) deve persistir `usuario_id` e `nome` em `dados_especificos`:

- APIs: `CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao` antes do service
- Services: o mesmo merge no início de `concluirSubtarefa` (camada defensiva)
- Auto-conclusões (ex.: cartas expedidas ordem 5/6): merge de sessão antes de `marcarConcluidaComDados`

Cadastro da tarefa pai: `criado_por` no `INSERT` + join `CriadoPorNome` na listagem.

## Exceções (sem `movement_from_subtarefa`)

| Módulo | Atualização na coluna |
|--------|----------------------|
| Ofícios (cartório/gabinete) | `OficioRegistroMetadataHelper` → `atualizado_por` do ofício |
| Gabinete monitoramento | Subtarefas sem JSON; `UltimoEditorNome` da tarefa |
| Sala pauta / mídias | `atualizado_por` / `atualizado_em` |
| Sala atas | `fromListRowSalaAtas` (upload, subtarefa mídia, pauta) |

## Implementação

- Helper: `app/helpers/RegistroMetadataHelper.php`
- Partial: `app/views/internal/_registro_metadata.php`
- Matriz de páginas: `docs/00-foundation/matriz-coluna-metadados-listagens.md`

## Checklist de QA (coluna)

Por módulo com subtarefas:

1. Registro novo: coluna mostra **Cadastrado por Nome** + **Em …**; sem linha de atualização.
2. Após concluir subtarefa na lista: **Atualizado em … por Nome** do executor.
3. Cancelamento (se aplicável): **Cancelado em … por Nome**.
4. Registro antigo sem `usuario_id` no JSON: atualização pode aparecer só com data (sem `por`).

Exceções (ofícios, sala, monitoramento): validar `por Nome` via `atualizado_por` quando a tarefa foi editada/cancelada no fluxo do módulo.
