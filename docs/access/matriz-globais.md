# Matriz — Secções globais

| page | Regra no registry | Quem acede (resumo) |
|------|-------------------|---------------------|
| dashboard | `all_internal` | Pelo menos um perfil `ESTAGIARIO-*` ou `SERVIDOR-*`; ou elevado |
| dashboardexterno | `externo_only` | Utilizador externo |
| alterardados | `all_internal` | Idem dashboard |
| notificacoes, configuracoes, documentos | `all_internal` | Idem |
| feriadoslist | `all_internal` | Idem |
| skills, skillspdfmarkdown, skillspdfsplit | `all_internal` | Qualquer utilizador interno (perfil composto ou elevado); menu na seção **Ferramentas** |
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

## Consulta de vínculos de processos e partes

Decisão de Jorge em 15/11/2026 (horário da Bahia), para a API
`processos-partes`: os perfis internos que já estejam autorizados à operação
de consulta podem consultar seus dados mesmo quando o processo for sigiloso.
A consulta continua condicionada à sessão válida e à autorização da operação;
essa decisão não concede acesso a perfis externos nem amplia as permissões de
criar, alterar ou excluir vínculos. Aplicam-se as regras de sigilo específicas
de outras áreas e operações.

Esta é uma exceção de consulta transversal no escopo descrito acima; não
estabelece acesso geral a outros registros setoriais. A decisão documenta a
política, sem afirmar que todos os caminhos da API foram validados.

## Consulta e alteração de assuntos de processos

Decisão de Jorge em 16/11/2026 (horário da Bahia), específica à implantação da
1ª Vara Crime: perfis internos já autorizados à operação de consulta podem ler
assuntos de qualquer processo desta instalação, inclusive sigiloso; operações
de alteração exigem a autorização de edição correspondente. Perfis externos
não acessam esses endpoints. A decisão não concede acesso a outras áreas nem
estabelece uma exceção geral ao isolamento setorial. Sincronizações DataJud só
podem persistir lote completo cuja origem, número CNJ e ausência de sigilo
público estejam confirmados no servidor.

A implementação deve preservar a origem manual dos vínculos e limitar o
desvínculo manual a esses vínculos. Esta regra registra a política acordada;
testes sintéticos, autenticação real e MySQL devem ser relatados separadamente.
