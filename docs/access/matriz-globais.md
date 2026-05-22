# Matriz — Secções globais

| page | Regra no registry | Quem acede (resumo) |
|------|-------------------|---------------------|
| dashboard | `all_internal` | Pelo menos um perfil `ESTAGIARIO-*` ou `SERVIDOR-*`; ou elevado |
| dashboardexterno | `externo_only` | Utilizador externo |
| alterardados | `all_internal` | Idem dashboard |
| notificacoes, configuracoes, documentos | `all_internal` | Idem |
| feriadoslist | `all_internal` | Idem |
| skills, skillspdfmarkdown | `all_internal` | Qualquer utilizador interno (perfil composto ou elevado); menu na seção **Ferramentas** |
| monitoramentogabinete, monitoramentogabinetecreate, monitoramentogabineteedit | `all_internal` | Qualquer interno com `ESTAGIARIO-*` ou `SERVIDOR-*` (cartório, gabinete ou audiências); menu **somente** em **Consultas**; cadastro/conclusão/cancelamento permitidos a todos os internos com acesso (decisão produto 2026-05) |

**Monitoramento — decisões de produto (implementado 2026-05):**

- Menu: um único atalho na seção **Consultas** (não duplicar em Gabinete).
- Operação: todos os internos com perfil composto (`ESTAGIARIO` / `SERVIDOR` + elevados) podem usar lista, cadastro, edição e subtarefas.
- Rótulos: breadcrumb/título **neutros** (“Interno - Monitoramento Eletrônico”); rotas/API mantêm prefixo `gabinete-` no código.
- Cancelar/excluir: internos com perfil composto; exclusão na edição ainda exige ser o **último número sequencial** do ano.
- Lista: padrão **Em aberto** (oculta `finalizada` e `cancelada`); opção **Todos os status** no filtro.
| usuarios*, feriadoscreate, feriadosedit | `gestao` | Só `ADMIN` / `CHEFIA` (elevado) |
| consultapartes, instituicoespublicas, partesinstituicoeslist | `consultas_partes` | Consultor externo + interno com composto + elevado |
| consultaprocessos, consultaprocessosedit | `consultas_processos` | Só interno com composto + elevado (não externo) |
| 401, 403, 404, 500, 503 | `all_internal` | Interno com composto ou elevado |

Consulte `AccessEvaluator::externoCanAccessPage` para o detalhe de partes vs processos para utilizadores externos.
