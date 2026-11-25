# REI-73 — Cartas Expedidas: matriz parcial

Base inspecionada: `6b47f8249fd6653585dd025cd6d3427e2baa141d`; inspeção estática de 9/9 handlers em `public/api/cartorio-cartas-expedidas/`. Não é validação HTTP, de login, de banco ou de concorrência. Todos chamam `ApiAccessGuard`; as chaves de página desta família têm política Cartório (`app/access/PageAccessRegistry.php:58-60`): compostos SERVIDOR-CARTORIO e ESTAGIARIO-CARTORIO e elevados ADMIN/CHEFIA, com DIRETORA/MAGISTRADA normalizadas. A exclusão exige adicionalmente perfil literal ADMIN, CHEFIA, DIRETORA ou MAGISTRADA. Escritas usam `write=true` (POST e origem confiável); leituras usam `write=false`, sem restrição explícita de método no guard. O filtro de chaves de página não prova autorização por objeto.

| Handler | Operação, controle e fonte | Consumidor identificado |
|---|---|---|
| buscar-cidade.php | Leitura, chave cartasexpedidas. Cidade e UF por GET/POST; reconhece CCM e, nos demais casos, consulta primeiro nome/cidade sem UF no repositório. A confirmação de homônimos e comportamento integrado fica pendente. | views de criação e edição de carta. |
| list.php | Leitura, cartasexpedidas. Paginação fixa 15, filtros GET classe/status; serviço retorna lista e contagens. | A view de listagem usa serviço diretamente; chamada HTTP literal não foi identificada na busca dirigida. |
| create.php | Escrita, cartasexpedidascreate. CNJ formatado e chave idempotente de 64 hex; usuário da sessão; serviço cria carta/processo/subtarefas com transação ou savepoint e tratamento de colisão de chave; o mecanismo não comprova atomicidade no schema e engines efetivos. | `public/assets/js/shared/cartorio-cartas-expedidas-create.js`. |
| update.php | Escrita, cartasexpedidasedit. ID no corpo ou GET; service valida ID e repository limita colunas aceitas, incluindo status. Não se infere autorização setorial do processo a partir do guard. | view de edição. |
| cancel.php | Escrita, cartasexpedidasedit. Confere sessão; service atualiza status por ID, sem exclusão das subtarefas. | view de edição e módulo v2 de listagem. |
| delete.php | Escrita, cartasexpedidasedit e `DeleteAuthorization`, que exige perfil elevado literal; transação bloqueia carta e remove suas subtarefas e alocações. | view de edição. |
| concluir-subtarefa.php | Escrita, cartasexpedidasedit. Por ID da subtarefa, serviço bloqueia carta e filhos, valida ordem/estado, torna repetição explícita e atualiza registro de conclusão dentro da transação. Requer InnoDB nas tabelas verificadas pelo helper. | `public/assets/js/modules/cartorio/cartas-expedidas/v2-list.js`. |
| criar-primeira-subtarefa.php | Escrita, cartasexpedidasedit. Bloqueia carta e filhos; exige carta não terminal e ausência de subtarefas, cria envio e devolução na transação. | Nenhuma referência literal identificada na busca dirigida; isso não comprova ausência de uso. |
| sync-processo-datajud.php | Escrita, cartasexpedidasedit. Recebe CNJ, exige sessão, consulta integração e sincroniza classe e assuntos quando retornados; chamadas de sincronização separadas. Não foi feita consulta externa nesta auditoria. | módulo v2 de listagem. |

## Reconciliação e limites

- REI-168: conclusão e recuperação foram trabalhadas e verificadas em outra etapa; esta leitura não repete nem amplia os testes MySQL.
- REI-88: o serviço de criação passa sigilo calculado a `ensureProcess`; o caso de alteração de processo existente foi reproduzido separadamente com SQLite sintético e está pendente de correção. Alteração de sigilo deve ocorrer somente na edição do processo, pelos perfis autorizados a editá-lo, conforme decisão do usuário.
- REI-67: vários tratamentos de exceção retornam `getMessage()` em erros 500; conclusão de subtarefa tem mensagem genérica. Algumas conexões são obtidas fora do `try`; classificação de todas as falhas exige execução isolada.
- REI-153, REI-76 e REI-72: sincronização de classe/assuntos deve ser conciliada com as frentes existentes; não se afirma atomicidade entre as duas chamadas.
- Cancelamento e update: mudanças de status e sua relação com subtarefas exigem teste de transições, falhas e concorrência. Não se declara defeito sem reproduzir o cenário e verificar contrato.
- Os consumidores acima são referências encontradas por busca textual dirigida; integrações indiretas podem existir. Não foram alterados produto, banco ou serviço externo.
