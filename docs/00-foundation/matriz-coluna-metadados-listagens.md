# Matriz: coluna de metadados nas listagens

Auditoria da última coluna (`container-data-create` / `_registro_metadata.php`) por página de listagem.

| # | Página | Helper | Cadastro (nome) | Atualização (nome) | Fonte cadastro | Fonte atualização | Status pós-correção |
|---|--------|--------|-----------------|-------------------|----------------|-------------------|---------------------|
| 1 | cartorio-cartas-recebidas-list | `cartas_recebidas` | SQL `CriadoPorNome` | Última subtarefa `concluida` + JSON | `criado_por` | `SubtarefasAll` + `dados_especificos` | Corrigido |
| 2 | cartorio-cartas-expedidas-list | `cartas_expedidas` | idem | idem | idem | idem | Corrigido |
| 3 | cartorio-guias-list | `guias` | idem | idem | idem | idem | Corrigido |
| 4 | cartorio-editais-list | `editais` | idem | idem | idem | idem | Corrigido |
| 5 | cartorio-oficios-list | OficioRegistroMetadataHelper | SQL | `UltimoEditorNome` ofício | `criado_por` | `atualizado_por` ofício | OK (exceção) |
| 6 | gabinete-oficios-list | OficioRegistroMetadataHelper | idem | idem | idem | idem | OK (exceção) |
| 7 | gabinete-monitoramento-eletronico-list | `gabinete_monitoramento` | SQL | `UltimoEditorNome` (subtarefa sem JSON) | `criado_por` | `atualizado_por` tarefa pai | OK (exceção) |
| 8 | sala-audiencias-pauta-list | `sala_pauta` | SQL | `UltimoEditorNome` | `criado_por` | `atualizado_por` | OK (exceção) |
| 9 | sala-midias-list | `sala_midias` | SQL | `UltimoEditorNome` | `criado_por` | `atualizado_em` | OK (exceção) |
| 10 | sala-audiencias-atas-list | `sala_atas` | "Cadastrado pelo sistema" | Upload / subtarefa mídia / pauta | regras SQL | `fromListRowSalaAtas` | OK (exceção) |
| 11 | Outras `*-list` cartório | — | — | — | — | — | Nenhuma pendente de migração encontrada |

**Legenda:** módulos 1–4 usam `movement_from_subtarefa` em `RegistroMetadataHelper`; salvar formulário sem concluir subtarefa não gera linha "Atualizado por" na coluna.
