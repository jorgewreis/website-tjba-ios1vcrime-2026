# Matriz — Secções globais

| page | Regra no registry | Quem acede (resumo) |
|------|-------------------|---------------------|
| dashboard | `all_internal` | Pelo menos um perfil `ESTAGIARIO-*` ou `SERVIDOR-*`; ou elevado |
| dashboardexterno | `externo_only` | Utilizador externo |
| alterardados | `all_internal` | Idem dashboard |
| notificacoes, configuracoes, documentos | `all_internal` | Idem |
| feriadoslist | `all_internal` | Idem |
| usuarios*, feriadoscreate, feriadosedit | `gestao` | Só `ADMIN` / `CHEFIA` (elevado) |
| consultacep | `consultas_cep` | Externo (estudante/consultor conforme regra) + interno com composto + elevado |
| consultapartes, instituicoespublicas, partesinstituicoeslist | `consultas_partes` | Consultor externo + interno com composto + elevado |
| consultaprocessos, consultaprocessosedit | `consultas_processos` | Só interno com composto + elevado (não externo) |
| 401, 403, 404, 500, 503 | `all_internal` | Interno com composto ou elevado |

Consulte `AccessEvaluator::externoCanAccessPage` para o detalhe CEP vs partes.
