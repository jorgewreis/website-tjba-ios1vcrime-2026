# REI-73 — cobertura estática cartorio-editais

## Escopo e fonte

Base: main remota confirmada `6b47f8249fd6653585dd025cd6d3427e2baa141d`. Oito de oito handlers da família `public/api/cartorio-editais/` lidos integralmente com `git show`; serviço, repositório e consumidores rastreados nos trechos abaixo. Relatório de 25/11/2026 UTC por integration_readiness. Nenhum teste, banco, HTTP, e-mail ou Docker executado nesta rodada. Apenas este relatório temporário foi escrito. Não altera produto, PR ou Linear e não certifica a auditoria integral.

Instruções aplicadas: jev-router e regras locais de preservação/escopo; coordenação pela sessão principal. Fonte canônica foi o blob Git, não a árvore compartilhada. Decisões prévias de leitura interna sem gate setorial não foram convertidas em requisito novo de setor responsável para objetos Editais.

## Contrato comum

Todos8 usam ApiAccessGuard. Sete mutações usam write=true e exigem POST/origem confiável; list.php usa write=false, sem restrição explícita de verbo/origem, embora leia GET. `app/helpers/ApiAccessGuard.php:13-35` autentica via Security, busca perfis efetivos do ID da sessão e avalia a página. `app/access/PageAccessRegistry.php:46-48` define editais/editaisedit/editaiscreate como área Cartório; AccessEvaluator.php:15,71 e ProfileNormalizer.php:10 permitem composto ESTAGIARIO/SERVIDOR-CARTORIO e elevados ADMIN/CHEFIA, incluindo aliases normalizados DIRETORA/MAGISTRADA. `UsuarioPerfisAutorizacao.php:15-24` aplica supressão operacional de ADMIN. A regra de perfil da página não é autorização por alocação individual.

`BootstrapHelper.php:27` verifica POST, esquema/host/porta de Origin ou Referer. Fallback de ID em GET dentro de uma mutação não permite GET atravessar o guard. A exclusão acrescenta DeleteAuthorization com conjunto padrão ADMIN/CHEFIA/DIRETORA/MAGISTRADA (`DeleteAuthorization.php:10,27`); composto Cartório sozinho não basta.

## Matriz por endpoint

Paths da primeira coluna relativos a public/api/cartorio-editais/. Serviço S = app/services/EditaisService.php; repositório R = app/repositories/EditaisRepository.php.

| Handler / operação | Guard / método | Fonte de objeto e controles observados | Consumidor rastreado |
|---|---|---|---|
| create.php / cadastrar edital | linha4, editaiscreate, POST confiável | JSON/POST16-20; ator sessão22-24; formato CNJ no campo processo27-42. S75-136 exige processo/tipo/prazo, pode criar classe/processo103-105 e insere pai134. Não cria etapas no mesmo pedido. | public/assets/js/shared/cartorio-editais-create.js:79 |
| criar-primeira-subtarefa.php / inicializar etapas | linha4, editaiscreate, POST confiável | edital_id/editalId no corpo ou GET22; S139-166 confirma pai. Se qualquer etapa existir, recalcula status e retorna; senão insere duas etapas e atualiza pai sem transação abrangente própria. Não verifica estado terminal antes desse ramo. | mesmo JS:99, segundo fetch após create |
| concluir-subtarefa.php / publicação ou decurso | linha4, editaisedit, POST confiável | subtarefa_id/subtarefaId corpo ou GET24. Ator mesclado com sessão25 e novamente em S172; helper sobrescreve usuario_id do cliente. S174 resolve subtarefa, pai via edital_id190. Endpoint37-42 transaciona statusAnterior, serviço e evento dashboard. S169-272 não bloqueia replay por status terminal/etapa já concluída. | public/assets/js/shared/cartorio-editais-list.js:241 e bindConcludeButtons |
| list.php / consultar | linha4, editais, write=false | GET status/processo/tipo15-17, página23-25; limite15 fixo. S36-61 busca lista/contagem e DecursoInfo com persistirCalculo padrão false414. Sem purge na listagem atual. | Busca literal em árvore rastreada não encontrou chamada além de documentação; não prova desuso. Views podem chamar serviço diretamente. |
| reconciliar.php / reparar etapas e recalcular | linha4, editaisedit, POST confiável | JSON edital_ids array14; S528-546 limita15, tipos string/int, tamanho128, deduplica; ordena IDs585-588. Mutex calendário no MySQL552-559, transação/lote566+, lock pai493 e590. Ignora concluída/cancelada594-599. | public/assets/js/shared/cartorio-editais-list.js:163-171, botão explícito com confirmação |
| update.php / editar | linha8, editaisedit, POST confiável | id corpo21; S876-892 permite observacoes E status diretamente. Não exige existência, estado anterior ou enum de status no serviço. Handler30 ignora bool e retorna success31. | app/views/internal/cartorio-editais-edit.php:223; public/assets/js/shared/cartorio-simple-edit.js:141 envia somente id/observacoes |
| cancel.php / cancelar | linha4, editaisedit, POST confiável | id corpo ou GET34; sessão adicional21-25. S895-901 só exige id não vazio e atualiza status cancelada. Handler43 ignora bool e retorna success44. | app/views/internal/cartorio-editais-edit.php:225; list.php:506; app/helpers/RegistroMetadataHelper.php:56 |
| delete.php / excluir pai e dependências | linha4, editaisedit, POST confiável + perfil elevado22-27 | JSON via ApiJsonResponse31, id corpo ou GET33. S903-940 recusa TX prévia, inicia TX, lock pai914, exclui somente subtarefas/alocados desse pai925-927, commit e rollback. Pai inexistente resulta deleted=false. | app/views/internal/cartorio-editais-edit.php:227 |

## Achados, confiança e limites

### Confirmado anteriormente em execução sintética — REI-319

Reenvio divergente de publicação concluída altera datas/metadados; após decurso concluído ou cancelamento reativa pai/decurso, enquanto eventos permanecem iguais. Evidência preservada em `C:/Users/jorge/AppData/Local/Temp/rei73-editais-replay-20261124`: fixture PHP, snapshots e replay-results.json. Laboratório SQLite em memória com serviços/repositórios reais, não HTTP/MySQL/produção. Não foi reexecutado nesta rodada.

Comparação `git diff b9828e5b 6b47f824 -- app/services/EditaisService.php public/api/cartorio-editais app/repositories/EditaisRepository.php app/repositories/EditaisSubtarefasRepository.php` retornou vazio. Logo os arquivos centrais da reprodução permanecem idênticos nesta base. Não estender isso a cobertura integral de dependencies/runtime. Fonte Linear REI-319 e comentário62c67856 (25/11 00:10Z) registram revisão independente e diferenças do harness: não executa guard HTTP nem primeiro merge da sessão. Replay idêntico, concorrência, MySQL e rollback continuam sem prova desse artefato. Não criar duplicata.

### Caminhos adicionais identificados estaticamente, ainda sem reprodução

1. **update aceita status fora da UI**: S882 permite campo status; R142-167 o persiste com bind, sem enum visível. Um usuário autorizado a editar pode enviar status diretamente e alterar apenas pai, sem sincronizar etapas. A UI legítima envia só observações. Confirmado o caminho de encaminhamento; efeito concreto depende schema/constraints e precisa fixture HTTP/snapshot. Não afirmar exploração anônima nem regra de negócio definitiva. Conciliar com REI-319 (proteção de terminais) antes de abrir novo item.
2. **sucesso sem alteração/registro**: update.php30-31 e cancel.php43-44 descartam bool; R167 retorna rowCount>0. ID inexistente ou payload sem campos permitidos pode produzir success:true sem persistência. Caminho estático claro, não reproduzido; distinguir no-op idempotente de registro inexistente antes de definir contrato. REI-149 trata padrão semelhante em Instituições, não cobre automaticamente Editais.
3. **inicialização pode recalcular terminal**: S145-155 confirma pai, mas não estado; com etapas existentes atualiza status derivado. Repetição após cancelamento pode retirar status cancelada, hipótese diferente de concluir publicação. Não demonstrada pela fixture319, não atribuir seus três PASS a esta rota. Priorizar extensão sintética coordenada com REI-100/319.
4. **criação em dois pedidos**: create.js79-118 cria pai e só depois etapas; falha do segundo retorna ao formulário sem recuperar ID. REI-100 já cobre atomicidade/retentativa/duplicação; não nova issue. Processo/classe gravados antes do pai em S103-105 relacionam REI-88 e precisam fronteira transacional conjunta.
5. **fronteira de erro anterior ao try**: sete handlers abrem conexão fora do try; reconciliar.php21 a abre dentro. concluir-subtarefa.php25 também consulta usuário antes do try por CartorioSubtarefaUsuarioSessao.php18-21. Respostas catch500 da família são genéricas; isso não comprova JSON uniforme se conexão/merge falhar antes. Registrar como cobertura a testar, relacionada REI-67, sem afirmar vazamento em produção.
6. **concorrência**: reconciliação e delete usam locks de pai; conclusão usa transação externa, mas não o mesmo lock explícito no trecho174-255. Preservação de invariantes entre concluir/cancelar/reconciliar/excluir demanda duas conexões sintéticas. Presença de transação não prova serialização, ausência de lock explícito não prova cada interleaving.

## Reconciliação de Linear

Consulta por edital no projeto retornou lista sem página seguinte. Lidas integralmente issues100/107/114/115/319 e comentários recentes limitados (1 ou2 por issue); não inventário completo de todos os históricos/PRs.

- [REI-319](https://linear.app/reis-developer/issue/REI-319): Planejado, replay reproduzido e revisão independente. Usar como fonte para reabertura; não corrigida nesta base.
- [REI-100](https://linear.app/reis-developer/issue/REI-100): Em andamento, descrição registra candidata #447 com rollback/idempotência/recuperação/concorrência sintéticos. Candidata não equivale a main; a main mantém dois pedidos e criação de etapas sequencial. Não iniciar implementação concorrente.
- [REI-115](https://linear.app/reis-developer/issue/REI-115): estado atual Concluído; descrição inicial diz endpoint sem TX, mas código atual37-42 tem TX. Comentário7b339acb relata merge#426/deploy3517a74 e SQLite, ressalvando smoke autenticado pendente naquele momento. Não apagar a distinção temporal nem inferir resultado atual só do comentário histórico. Atomicidade de entrada inválida não resolve replay válido319.
- [REI-114](https://linear.app/reis-developer/issue/REI-114): Em revisão. Comentário e18fe6f2 relata testes preservação/HTTP/MySQL isolados em21/11; recuperação de feriados e ambiente publicado ainda não comprovados. Main listEditais não purga e reconciliação é explícita; não repetir destruição histórica como comportamento atual.
- [REI-107](https://linear.app/reis-developer/issue/REI-107): Concluído. Main delete limita dependências por edital, sem limpeza global. Último comentário retornado fala de experimento de guias, não prova adicional desta exclusão. Não presumir cobertura universal pelo status.

## Plano de cobertura restante

1. Coordenar com dono319/100 antes de executar. HTTP sintético com guard real e bootstrap isolado: sete POSTs contra método/origem inválidos, anônimo/revogado, perfis Cartório/Gabinete/Audiências/externos/elevados e ADMIN suprimido. list somente autenticação/perfil, sem exigir POST.
2. Snapshots de pai/etapas/eventos para update(status), id inexistente e inicialização de pai cancelado/concluído; serviço real e SQL adaptado podem demonstrar lógica, sem declarar equivalência MySQL.
3. Reusar tests/editais/rei115-atomic-conclusion.test.php, preservar-feriados-historicos.test.php, reconciliar-http.test.py, reconciliar-toast.test.js, rei114-calendar-concurrency-mysql.test.php e rei134-decurso.test.php após conferir isolamento. Inventariados, não executados nesta rodada.
4. MySQL descartável e duas conexões para concorrência, índices/constraints e falhas intermediárias; ambiente não acessado nem classificado indisponível nesta rodada.
5. Navegador: falha do segundo pedido, repetição, mensagens400/403/500, reconciliação e contadores. Código de recuperação do botão existe no JS de lista, mas inspeção não comprova comportamento visual/autenticado.

Conclusão: inventário completo8/8 desta família; regras e caminhos críticos mapeados; replay previamente comprovado conciliado, suspeitas adicionais explicitadas e nenhum novo defeito declarado reproduzido. Auditoria funcional integral permanece pendente.
