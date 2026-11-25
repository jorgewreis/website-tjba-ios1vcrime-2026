# REI-73 — matriz da família de APIs Gabinete

## Base, responsabilidade e limite

Inspeção estática de 20/20 handlers rastreados em `public/api/gabinete-*`, no SHA main `6b47f8249fd6653585dd025cd6d3427e2baa141d` (ref local e remoto previamente conferidos). Data da consolidação: 25/11/2026 UTC. Fonte lida com `git show origin/main:<path>` e `git grep`, preservando árvore compartilhada. Responsável por este relatório: agente integration_readiness, coordenado pela sessão principal. Apenas este relatório novo foi gravado. Não houve teste, conexão a banco, envio de e-mail, modificação de produto, Linear ou PR. Esta é cobertura completa do inventário desta família, não validação funcional completa dos fluxos.

## Controles comuns e legenda

Todos os 20 handlers chamam ApiAccessGuard. `app/helpers/ApiAccessGuard.php:13` exige Security::isAuthenticated, consulta perfis efetivos do ID da sessão, permite qualquer chave de página informada e recusa falha de consulta. Identidade não é aceita do corpo. Fonte da autenticação: `config/security.php:22`; esta rodada não certifica todos os caminhos de revogação de sessão.

**P** = POST obrigatório e origem confiável (18 handlers; get.php e list.php de réus presos são R): guard write=true chama `app/helpers/BootstrapHelper.php:27`, valida método, host, esquema e porta de Origin ou Referer. Não significa token CSRF individual. GET com id como fallback continua recusado no guard; fallback de parâmetro não torna rota GET.

**R** = write=false; método não restringido explicitamente pelo handler, parâmetros vêm de GET. Não equivale a ausência de autenticação.

**M** = política all_internal: composto ESTAGIARIO/SERVIDOR de CARTORIO/GABINETE/AUDIENCIAS ou elevado. **G** = área Gabinete: composto ESTAGIARIO/SERVIDOR-GABINETE ou elevado. `app/access/PageAccessRegistry.php:65-82`, `AccessEvaluator.php:15,71`, `ProfileNormalizer.php:10` (DIRETORA/MAGISTRADA normalizados para CHEFIA). Perfis lidos passam também por supressão operacional de ADMIN: `app/helpers/UsuarioPerfisAutorizacao.php:15-24`. Não inferir perfil permitido apenas por texto de mensagem.

Não foi identificada regra de setor responsável por processo nos handlers desta família. Isso não prova bypass: a regra documentada de mutação processo-parte não pode ser transplantada para todo objeto Gabinete sem decisão. Leitura interna de processos sigilosos não deve receber gate setorial por inferência.

## Matriz: monitoramento eletrônico (5)

Prefixo `public/api/gabinete-monitoramento-eletronico/`. Guard em todos: linha 12. Serviço: `app/services/GabineteMonitoramentoEletronicoService.php`.

| Rota / operação | Método / chave / perfil | Fonte e controles do objeto | Consumidor comprovado |
|---|---|---|---|
| create.php — cadastrar | P / monitoramentogabinetecreate / M | Corpo JSON ou POST; CNJ formato handler30-48; service125+, vínculo processo-parte deve pertencer ao processo178-180. Classe/processo e eventual vínculo antes de beginTransaction194. | app/views/internal/gabinete-monitoramento-eletronico-create.php:97 |
| reus-partes.php — consultar réus | P / monitoramentogabinetecreate / M | processo_id30 ou numero_processo31; service73 consulta vínculos; método de nome legado ensureProcessoEListarReusParaCadastro102 apenas consulta processo e réus existentes110-121. Não sincroniza nem cria atualmente. | mesma view:95 |
| concluir-subtarefa.php — transicionar | P / monitoramentogabineteedit / M | subtarefa_id/subtarefaId30; data_inicio31; ator sessão32. Service242-330 resolve pai pelo filho, lock pai→filho, reconfirma vínculo e rejeita pai cancelado/finalizado e filho concluído. | app/views/internal/gabinete-monitoramento-eletronico-list.php:459 |
| cancel.php — cancelar | P / monitoramentogabineteedit / M | id JSON/POST ou GET30; service335-358 lock pai, finalizada recusada, cancelada idempotente, remove filhos e atualiza pai no escopo atômico. | list.php:442 e edit.php:188 nas views internas do módulo |
| delete.php — excluir | P / monitoramentogabineteedit / M | id30; service361-400 confirma pai/ano, exige maior sequencial anual, escopo atômico com serialização anual. Não há filtro adicional ADMIN/CHEFIA neste handler. Diferença de ofícios não basta para declarar falha. | app/views/internal/gabinete-monitoramento-eletronico-edit.php:189 |

Respostas de infraestrutura destes cinco handlers são genéricas; InvalidArgumentException expõe mensagem funcional. A transação das transições não demonstra atomicidade de criação de classes/processos/vínculos anteriores à transação de create.

## Matriz: ofícios Gabinete (3)

Prefixo `public/api/gabinete-oficios/`. Serviço `app/services/OficiosGabineteService.php`.

| Rota / operação | Método / chave / perfil | Fonte e controles do objeto | Consumidor comprovado |
|---|---|---|---|
| create.php — cadastrar | P / oficiosgabinete / G, guard13 | JSON/POST; valida CNJ23-41; criado_por substituído pela sessão43. Service64+, transação77. Não há chave create distinta no registro de páginas. | app/views/internal/gabinete-oficios-list.php:427 |
| cancel.php — cancelar | P / oficiosgabineteedit / G, guard12 | Sessão obrigatória adicional24; perfis30; id38 também fallback GET. Service139-170: existente, cancelada idempotente, regra autor ou ADMIN/CHEFIA e data de criação no limite inclusivo de 3 dias (`created >= strtotime('-3 days')`) via OficioRegistroMetadataHelper.php:50,203-214; validação de estado e autorização antecede update. | list.php:429 e edit.php:160 nas views do módulo; app/helpers/OficioRegistroMetadataHelper.php:138 |
| delete.php — excluir | P / oficiosgabineteedit / G, guard13 | DeleteAuthorization23-32 exige perfil literal ADMIN/CHEFIA após perfis efetivos; id41. Service174-194 exige maior sequencial do ano. Leitura de máximo e exclusão não têm escopo atômico próprio no trecho. | app/views/internal/gabinete-oficios-edit.php:161 |

Conexão destes handlers ocorre fora do try; catch Throwable retorna getMessage em create54/cancel54/delete57, relacionado REI-67. Diferença entre normalização do guard e interseção literal em DeleteAuthorization.php:27-39 / OficioRegistroMetadataHelper.php:186-200 exige teste de perfis legados, não prova escalada. Hipótese de corrida na regra último sequencial/cancelamento requer teste sintético; não foi reproduzida aqui. Evitar duplicar auditoria Ofícios já ativa.

## Matriz: réus presos (12)

Prefixo `public/api/gabinete-reus-presos/`. Serviço `app/services/GabineteReusPresosService.php`. **C** = contexto principal/substituto escolhido por GET contexto; construtor46-50 seleciona repository, cujo findById filtra id + controle_tipo (`app/repositories/GabineteReuPresoRepository.php:19-29`); updates também filtram contexto54-91. Contexto não é uma identidade independente nem um bypass demonstrado: ambas páginas usam G.

| Rota / operação | Método / chave / perfil | Fonte e controle equivalente | Consumidor comprovado |
|---|---|---|---|
| create.php | P / reuspresoscreate / G, guard4 | JSON/POST; user_cadastro sobrescrito pela sessão22; C; service117. | app/views/internal/gabinete-reus-presos-create.php:92 |
| get.php | R / reuspresos OU reuspresossubstituto / G, guard4 | GET id16; C; service findById251, 404 se ausente. | Nenhuma referência literal encontrada em busca rastreada integral; não declarar rota sem uso. |
| list.php | R / mesmas chaves / G, guard4 | GET paginação/q/nome/processo21-29; C; service listAtivos162. | Nenhuma referência literal encontrada; views podem consumir serviço diretamente. |
| update.php | P / reuspresosedit / G, guard4 | id22,nome23 corpo; C; service139-158 confirma existência/ativo antes update. | public/assets/js/shared/gabinete-reus-presos-edit.js:77 |
| archive.php | P / reuspresosedit / G, guard8 | id corpo27; C; service600+ existente, já arquivado retorna; prioridades reconciliadas. | mesmo JS:507 |
| save-processo.php | P / reuspresosedit / G, guard4 | Corpo ao service334; C; réu existente/ativo350-356; link_id existente deve pertencer ao réu378-380. Classe/processo criados antes dessa validação369-374. Vínculo, custódia, prioridade, parte e revisão são efeitos distintos sem transação abrangente visível no serviço. | mesmo JS:204,382 |
| unlink-processo.php | P / reuspresosedit / G, guard4 | link_id22; service442-470 busca vínculo, resolve pai e exige ativo em C; remove/reconcilia/escolhe principal. | mesmo JS:228 |
| set-principal.php | P / reuspresosedit / G, guard4 | reu_preso_id22/link_id23; service472-493 exige réu ativo em C e vínculo pertencente; limpa e marca em escritas separadas. | mesmo JS:147 |
| processo-meta.php | P / reuspresosedit / G, guard4 | CNJ corpo25; leitura processo/classe33-43; SQL custódia50-61 filtra controle_tipo. Guard de escrita não significa que query escreve. | mesmo JS:287 |
| add-movimentacao.php | P / reuspresosedit / G, guard4 | Corpo + user_cadastro sessão22; C; service499-565 valida data real/não futura antes consultas, réu ativo e vínculos; grava por vínculo e recalcula revisão. | mesmo JS:437 |
| delete-movimentacao.php | P / reuspresosedit / G, guard4 | reu_preso_id/movimentacao_id25-26; service569-597 confirma pai ativo em C e movimento pertencente; exclui e recalcula. | mesmo JS:473 |
| send-revisao-resumo-email.php | P / reuspresosedit / G, guard4 | Perfil adicional literal ADMIN ou MAGISTRADA22-27. Sem objeto do corpo; sendResumo36 agrega dados. Não foi enviado e-mail. | app/views/internal/gabinete-reus-presos-list.php:405 |

O JS compartilhado acrescenta contexto=substituto em postJson26-28. Por isso ler apenas URL literal não demonstra mutação no contexto errado. get/list sem consumidor textual comprovado não devem ser removidos por esta auditoria.

## Reconciliação com Linear e concorrência entre sessões

Fontes diretas: https://linear.app/reis-developer/issue/REI-73 e issues abaixo. Consultados descrição/estado e os dois comentários mais recentes de 73/94/95/112/121; três de118. Não foi paginado todo o histórico nesta rodada.

- REI-73: comentários b6fcc9c4 (25/11 00:26Z) e fb97dea8 mostram continuidade em sigilo/comarca por outra sessão. Não duplicados neste relatório. Matriz versionada anterior: docs/90-releases/rei-73-inventario-a506e451.csv é textual, não prova funcional atual.
- REI-118, Em andamento: comentário a0c8270e (20/11 18:16Z) já comprova em laboratório consulta sem escrita, POST protegido, GET recusado e ausência de endpoint explícito de sincronização. Código atual confirma consulta estrita. Não reabrir alegação antiga de escrita GET como fato atual.
- REI-94/95, Em andamento: comentários 8fca8e54/bda9b566 (20/11 16:22Z) relatam candidato experimental amplo de transações e trava nomeada por banco, sem commit/push. Main ainda tem clearPrincipal + update490-493 e unlink sem recalc442-470. Reusar essas issues; não presumir candidato publicado nem iniciar correção concorrente. Riscos de prioridade/rollback de save-processo e movimentações cabem em reconciliação dessa frente antes de criar issue nova.
- REI-112, Em revisão: comentário 9fdbe345 (21/11) registra teste sintético e deploy anteriores, faltando timezone efetivo e fluxo autenticado. Main tem validação servidor507-520; não repetir que aceita futuro sem validação.
- REI-121, A fazer: comentário explícito fa79bc3f (15/11 19:15Z), do usuário: “CHEFIA deve sempre incluir MAGISTRADA também”. Este esclarecimento posterior à descrição original deve orientar reconciliação. Handler atual ainda testa apenas ADMIN/MAGISTRADA literais. Divergência estática concreta para CHEFIA sem outro perfil aceito; HTTP sintético pendente. Não equiparar outras identidades por hipótese além dessa decisão.
- REI-67: erros técnicos expostos e conexão fora try dos ofícios permanecem relacionados; não nova issue nesta rodada.
- REI-88: efeitos em processo/classe antes de transação no monitoramento e save-processo precisam ser comparados ao contrato já definido de preservação; nenhuma alteração ou teste novo feito aqui.

## Próximas verificações isoladas, ordenadas

1. Harness HTTP sem banco real: todos20 com anônimo, sessão revogada, CONSULTOR/ESTUDANTE, compostos das três áreas, CHEFIA, MAGISTRADA legado, ADMIN efetivo/suprimido; Nos 18 handlers P, método inadequado e origem/porta divergente devem impedir despacho; get.php e list.php de réus presos têm somente o guard de leitura, sem essas restrições de método/origem. Inspecionar bootstraps e sentinela de conexão antes de rodar.
2. REI-121: serviço de e-mail stub que conta despacho, CHEFIA puro contra ADMIN/MAGISTRADA e perfis negados. Não enviar e-mail. Comparar resultado à decisão acima.
3. REI-94/95: coordenar candidato existente; dados sintéticos para IDs de outro contexto, vínculos/movimentos de outro réu, réu arquivado, rollback após clearPrincipal e recálculo após unlink. SQLite pode mostrar estado parcial; concorrência/locks exigem MySQL descartável previamente autorizado e isolado.
4. REI-118: reaproveitar tests/monitoramento/consulta-reus-sem-escrita.test.php e authorized-dispatch.test.php; método e ausência de sincronização são aceites separados. Não mudar contrato GET apenas para passar teste.
5. Criação monitoramento: falha depois de ensureProcess/addParte antes de beginTransaction, snapshot classe/processo/vínculo/pai/filhos, conciliar88. Não afirmar perda ou violação de setor sem reprodução/contrato.
6. UI: consumidores listados, principal/substituto, erros 400/403/500 e recuperação. Os arquivos existentes tests/reus-presos/movimentacao-data.test.php e tests/monitoramento/* foram inventariados, não executados. Não atribuir PASS desta rodada.

## Conclusão

Inventário estático completo desta família: 5 monitoramento + 3 ofícios + 12 réus presos =20; zero sem ApiAccessGuard. Matriz não certifica segurança global nem fecha REI-73. Controles de objeto foram rastreados nos caminhos centrais; inferência de segurança baseada só no guard foi evitada. Prioridades imediatas são reconciliação121 e testes de objeto/rollback alinhados94/95. Não há evidência nova executável nem impedimento de infraestrutura constatado nesta rodada, pois execução não fazia parte do escopo.