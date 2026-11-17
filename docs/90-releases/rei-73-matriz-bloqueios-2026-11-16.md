# REI-73 — matriz incremental de bloqueios e evidências

**Snapshot:** 2026-11-17 (America/Bahia)
**Base inspecionada:** `origin/main` em `bca62b990c2893937e723e23de50651d67736cd7` (inclui merge de #430)
**Worktree desta documentação:** `codex/rei73-blocker-matrix-20261117`
**Escopo:** oito issues originalmente ligadas como bloqueadas por REI-73 (REI-66 a REI-72 e REI-88). A relação com REI-71, concluída no Linear, foi removida em 2026-11-17; permanecem sete dependências nativas abertas. A matriz registra REI-71 como evidência histórica, sem tratá-la como bloqueio atual. Esta matriz é uma reconciliação incremental; não certifica a auditoria completa.

## Como ler os estados

- **Validado no SHA exato:** há evidência registrada de execução vinculada ao SHA indicado. Vale somente para o escopo descrito.
- **Somente inspeção estática:** revisão de código, diff ou inventário; não demonstra comportamento executado.
- **Não verificado:** não há evidência atual suficiente para afirmar o critério.
- **Bloqueado por ambiente:** depende de sessão, infraestrutura, acesso, janela ou isolamento não demonstrado disponível.

Resultados mencionados em comentários/corpos de PR são relatos e não foram reproduzidos nesta revisão. SHA de PR é distinto de SHA publicado. PR aberto, check verde, push ou deploy isolado não demonstram aceite integral.

## Estado resumido

| Issue | PR/candidato observado | Evidência mais forte registrada | Gate ainda aberto |
|---|---|---|---|
| REI-66 | Sem PR aberto identificado | Testes sintéticos reportados em `cfb38fbf…`; correção `d00eaf5e…`; deploy reportado no run `43783469473`, SHA `086db6b…` | Document root e coexistência de writers/caminho legado não demonstrados |
| REI-67 | PR #433 draft, HEAD `0a3aa89d4065effcccd8709e8235113dcb468543`, base `bca62b99`, MERGEABLE | Inventário estático de 150 entrypoints; lint 154 APIs; teste sintético público/admin verifica 502/500, validação 400 e ausência de sentinelas; checks #433 `44010126803`/`44010173678` passaram | Revisão estática independente sem bloqueio; sessões/DB/upstream reais, cobertura de todos contratos e deploy não verificados; continua bloqueada por REI-73 |
| REI-68 | PR #411 draft, HEAD `aa67922557a6b82ef650e91de5c86ea6a1cb4e32`, base `bca62b99`, MERGEABLE | Sincronizado por merge normal; upload 7 cenários, origem 18 casos e PHP lint passaram; checks exatos `44005804836`/`44005807687` SUCCESS | UI autenticada e revisão independente pendentes; suíte positiva de auth/atas continua condicionada ao gate REI-93 |
| REI-69 | PR #415 draft, HEAD `ef3b70d3838604e72c90bf66ba87474b337b3ea3`, base `bca62b99`, MERGEABLE | Após merge normal de main: testes SQLite, 27 cenários do guard, runner MySQL/InnoDB descartável e PHP lint passaram; checks exatos `44006419836` e `44006425307` passaram | REI-73 continua dependência; migração `ALGORITHM=COPY` não aplicada, volume/espaço/janela precisam de validação operacional; browser/sessão real ausentes |
| REI-70 | PR #407 não draft, HEAD `211d5fb786602cfc51ae24e49de0cbd82935cc5a`, base `bca62b99`, MERGEABLE | Checks exatos de PHP lint `44006335659`/`44006338607` passaram. Revisão independente de segurança não encontrou blocker nos 12 endpoints | Revisão formal pendente; sessão/browser/proxy não demonstrados; MySQL do HEAD atual não verificado; Linear mantém bloqueio por REI-73 |
| REI-71 | Sem PR aberto identificado | Testes sintéticos reportados em `b8cee7c…`; ancestral do SHA publicado `f5e657fc…`, run `43839505824` | Sessões e perfis autenticados reais não cobertos; issue concluída não encerra auditoria global |
| REI-72 | PR #428 draft, HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, base `bca62b99` | Após merge normal: testes reais SQLite e HTTP sintético afetados passaram; checks exatos `44001675569` e `44001683252` passaram | Concorrência MySQL, browser/sessão e política de autorização por registro seguem pendentes |
| REI-93 (gate de runner) | PR #410 draft, HEAD `ae93c5a90acc59040837128c4bd9ab636865a751`, base `bca62b99` | Rebase por merge normal; guard-rejection tests (3 casos), PHP lint e `git diff --check` passaram; checks exatos `44003036029` e `44003043091` passaram; MERGEABLE | Testes positivos continuam suspensos até comprovar identidade/procedência do daemon, isolamento DB/SMTP/rede e limpeza limitada; sem aprovação formal |
| REI-88 | PR #412 HEAD `9864fee20366a79a4a70159b71247361fbd85660`; #416 HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72` | Heads atuais na base `bca62b99`, MERGEABLE; checks lint #412 `44007291338`/`44007287960` e #416 `44007610762`/`44007606352` SUCCESS | Autorização de mudança de sigilo, transações de todos os callers e suíte MySQL no CI pendentes; PR relacionada #413 (REI-78) HEAD `3ae132890cbfeded4866f6221558c3735d6361b9`; checks de interação `44014409184` e lint `44014409208`/`44014402312` SUCCESS. Finding P1 resolvido no código: DEV/PROD executam migration staged antes do rsync; revisão Codex sem major issues e independente favorável. Tentativa DEV anterior `44011610849` falhou antes da conexão DB CLI e rollback `44011900429` restaurou SHA reportado `82b80a54…`; migration não foi reexecutada após a correção. PR #413 permanece draft; smoke autenticado e fonte CLI segura seguem pendentes |

## Critérios e evidências por issue

### REI-66 — cadastro de estudante e conta existente

**Aceite acompanhado:** cadastro público não altera conta existente sem fluxo autorizado/prova; preserva dados/perfis; serializa verificação e atribuição concorrente; testes sintéticos para conta sem perfil, estudante, interno, nova conta e concorrência sem dados pessoais reais.

- **Validado no SHA exato (parcial, evidência reportada):** `tests/api-security/student-existing-account.test.py` e `tests/api-security/admin-student-cpf-lock.test.py` passaram em `cfb38fbf…`; lint também reportado. Correção `d00eaf5e…` está na ancestralidade de `main`; deploy reportado no run `43783469473` para `086db6b…`.
- **Somente inspeção estática/não verificado:** configuração efetiva do document root e coexistência do endpoint legado `api/users.php` com outros writers não foram comprovadas no runtime.
- **Limitação:** testes não reproduzidos nesta matriz; deploy não prova a versão/configuração de todos os servidores nem cobertura de cada writer.
- **Próximo gate:** comprovar os caminhos de escrita e a versão servida na configuração publicada.

### REI-67 — respostas de erro interno nas APIs

**Aceite acompanhado:** inventariar/corrigir respostas internas em APIs públicas e autenticadas preservando contratos legítimos de validação; expor mensagem genérica ao cliente e diagnóstico restrito/sanitizado sem PII/credenciais; testar exceções nos dois contextos.

- **Candidato atual:** PR #433 draft, HEAD `0a3aa89d4065effcccd8709e8235113dcb468543`, base `bca62b99…`, MERGEABLE. A branch limpa registra handler genérico para 150 entrypoints, sanitização de exceções inesperadas e logs, mantendo validação/duplicidade explícitas.
- **Validado no SHA exato (escopo delimitado):** checks `44010126803` e `44010173678` passaram PHP lint. O teste sintético `tests/api-security/api-error-redaction.test.py` verifica respostas/logs sem sentinela e status 502/500, preserva uma validação 400 e confere o registro em todos os 150 entrypoints; revisão independente estática não encontrou bloqueador após correção do mapeamento DataJud.
- **Não verificado:** integração DataJud, sessão/credenciais e banco reais; todos os contratos de validação; handler fatal em runtime. Teste usa stubs para DataJud, Security e DB; inventário textual não executa individualmente cada endpoint.
- **Próximo gate prioritário de REI-73:** revisar os demais fluxos e dependências com cobertura autenticada/ambiente permitida; manter #433 em draft sem merge/deploy até concluir REI-73.

### REI-68 — upload de ata e proteção de escrita

**Aceite acompanhado:** proteção de sessão/operação compatível com multipart e perfil atual; POST válido; rejeitar token/origem ausente ou inválido e sessão inválida sem mutar banco/arquivo; fluxo legítimo de upload via UI em browser isolado.

- **Candidato:** PR #411, base `bca62b99…`, HEAD `aa67922557a6b82ef650e91de5c86ea6a1cb4e32`, draft. Comentários registram ajuste para `ApiAccessGuard::requireAccess(['salaata'], true)`, teste endpoint com 7 cenários e guard POST com 18.
- **CI no SHA exato:** checks `44005804836` e `44005807687` concluíram SUCCESS; cobrem PHP lint, não fluxo funcional autenticado.
- **Bloqueado por ambiente/não verificado:** upload autenticado via browser pendente; suites protegidas dependem de sandbox autorizado. Revisão independente final pendente.
- **Próximo gate:** executar UI autenticada em fixture isolada e obter check/revisão sobre o HEAD final.

### REI-69 — partes do processo e autorização por unidade responsável

**Aceite acompanhado:** inventariar endpoints/consumidores; exigir sessão, permissão de operação e autorização do registro para leitura/escrita; proteger método/origem em mutações; rejeições sem escrita e permitir caso autorizado; revisar endpoints relacionados.

- **Candidato:** PR #415, base `bca62b99…`, HEAD `ef3b70d3838604e72c90bf66ba87474b337b3ea3`, draft. Política decidida: permissão da operação mais unidade responsável, via `processos.id_setor_responsavel → setores.id_setor`; leitura sigilosa não concede escrita. Ausência de setor legado falha fechada; sem backfill automático.
- **Validado no SHA exato (parcial):** checks `44006419836` e `44006425307` passaram PHP lint. PR/comentário reporta runner sintético SQLite e MySQL 8.4/InnoDB, positivos/negativos de leitura/mutação e migração idempotente/rollback; não reproduzido nesta revisão.
- **Somente inspeção estática/bloqueado por ambiente:** migração aditiva nullable `CHAR(30)` e FK usa `ALGORITHM=COPY, LOCK=SHARED`; não foi executada em DEV/PROD. Medição de volume/espaço e janela de mudança necessárias. Sessão/browser real não demonstrados.
- **Próximo gate:** revisar migração contra schema real e preparar medição/plano antes de aplicar; manter legado sem responsável negado até atribuição autorizada.

### REI-70 — instituições públicas e política CONSULTOR

**Aceite acompanhado:** inventário de endpoints; sessão/operação/registro e proteção de escrita; pedidos anônimos/não autorizados sem mutação; CONSULTOR externo limitado à instituição/setor/vínculos ativos, preservando concessões internas de perfil misto.

- **Candidato:** PR #407, base `bca62b99…`, HEAD `211d5fb786602cfc51ae24e49de0cbd82935cc5a`, aberto, não draft e MERGEABLE.
- **Evidência reportada em HEADs anteriores:** policy8, guard52, anônimo12; SQLite com 40 recusas sem escrita e 8 escritas autorizadas; HTTP sintético; MySQL 8.4 descartável com 20 leituras, 40 recusas, 8 escritas e limpeza, tudo reportado no HEAD `76a6e75f…`. Esses testes não foram repetidos no HEAD atual `211d5fb…`.
- **CI no SHA exato:** checks `44006335659` e `44006338607` passaram PHP lint. Review formal ainda pendente. Fixture não tinha constraints/FKs; não demonstra schema real, sessão de produção, proxy ou autorização de leitura em ambiente real.
- **Próximo gate:** checks reproduzíveis no CI contra schema fiel, fluxo autenticado isolado autorizado e review formal.

### REI-71 — monitoramento e consultas internas

**Aceite acompanhado:** endpoints/consumidores; autorização `all_internal` para Cartório/Gabinete/Audiências; método/origem confiáveis em escritas; testes negativos/positivos sem escrita em rejeições.

- **Validado no SHA exato (escopo sintético reportado):** `tests/monitoramento/guard-simulated.test.php` (90 casos), `authorized-dispatch.test.php` (5 endpoints), `json-dispatch.test.py`, `consulta-sigilo.test.php` e `consulta-reus-sem-escrita.test.php` passaram, reportadamente, na base `b8cee7c…`, descendente do SHA publicado `f5e657fc…` (run `43839505824`). Smoke anônimo 401 também reportado.
- **Bloqueado por ambiente/não verificado:** identidades/serviços sintéticos; login/sessão real e infraestrutura não comprovados. 401 anônimo não valida autorização positiva por perfil.
- **Limitação:** Linear está em Concluído, mas isso fecha apenas a issue individual conforme sua evidência registrada, não REI-73.
- **Próximo gate:** anexar fluxos autenticados isolados ou registrar indisponibilidade e limites no inventário global.

### REI-72 — vínculo de assuntos e sincronização DataJud

**Aceite acompanhado:** sessão válida, permissão por operação/registro, método + origem confiável/CSRF; revisar APIs relacionadas; rejeições sem mutação; validar proveniência dos assuntos do cliente.

- **Candidato atual:** PR #428 permanece draft. HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, com merge normal da base atual `bca62b990c2893937e723e23de50651d67736cd7`; o conflito/defasagem com a base foi resolvido. PRs #405 e #409 foram fechadas como duplicatas após confirmar que seus commits estão contidos em #428.
- **Validado no SHA exato (reproduzido após merge):** `php tests/datajud/mapper-sync-real.test.php`, `php tests/datajud/sync-atomico.test.php`, `php tests/datajud/sync-rollback-repositorios.test.php`, `php tests/processos/assunto-sync-payload.test.php`, `php tests/processos/assunto-sync-service-validation.test.php`, `python -B tests/processos/datajud-sync-objeto-http.test.py` e `php tests/guias/conclusao-atomicidade.test.php` passaram. O mapper, serviço e repositórios foram exercitados em SQLite; os dois endpoints foram exercitados via harness HTTP sintético.
- **CI no SHA exato:** runs `44001675569` e `44001683252` passaram para `e83aced08b63ae6b4d7b51d4d67552225922217a`.
- **Limitações não resolvidas:** a fixture HTTP usa dublês e não testa sessão autenticada; a suíte separada cobre mapper/serviços/repositórios reais em SQLite. Concorrência MySQL, browser/sessão real e DataJud vivo continuam sem evidência. A issue ainda requer resolver/documentar autorização por registro para link/unlink/read/sync. Sem merge ou deploy do PR.
- **Próximo gate:** fechar a política de escopo do registro e validar concorrência MySQL e sessão isolada. REI-73 continua bloqueada/incompleta até os gates individuais e a cobertura global serem demonstrados.
### REI-88 — preservação de sigilo e atomicidade em `ensureProcess`

**Aceite acompanhado:** não apagar sigilo sem mudança explicitamente autorizada; auditar callers; atomicidade de processo/classe/pauta; testes MySQL sintéticos para processo sigiloso, rejeições/falhas, processo novo e mudança autorizada.

- **Evidência na base:** mudança `ensureProcess`/transação de pauta presente na ancestralidade da base atual `bca62b99…` (`97da36…`, conforme PR #416); deploy reportado no run `43884547610`. Testes SQLite e cinco casos MySQL isolados reportados por #416.
- **Candidatos atuais:** #412 (Carta, HEAD `9864fee20366a79a4a70159b71247361fbd85660`) e #416 (Pauta, HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72`), ambos sobre base `bca62b99…`, drafts e MERGEABLE. Checks lint exatos passaram: #412 `44007291338`/`44007287960`; #416 `44007610762`/`44007606352`. PR #413 (REI-78, HEAD `3ae132890cbfeded4866f6221558c3735d6361b9`) está MERGEABLE e em draft. Checks de interação `44014409184` e lint `44014409208`/`44014402312` passaram; revisão Codex não encontrou major issues e revisão independente confirmou a correção do P1. O P1 foi resolvido no código: migration staged roda antes do rsync em DEV/PROD. A tentativa DEV anterior `44011610849` falhou antes de executar SQL por configuração DB CLI ausente; rollback reportado `44011900429` restaurou o SHA `82b80a54…`. A nova migration ainda não foi executada: configuração CLI segura e smoke autenticado permanecem gates de ambiente.
- **Somente inspeção estática/lacunas:** callers relevantes: `SalaAudienciasPautaService`, `ReuManager`, `OficiosCartorioService`, `OficiosGabineteService` e callers de Guias. Falta comprovar ownership transacional de cada caminho, propagação/captura de exceções e rollback de efeitos anteriores. Testes Carta/Pauta não provam todos os callers.
- **Não verificado:** autorização para alteração explícita de sigilo e por processo não demonstrada em teste formal. PRs sem suíte MySQL ligada ao CI; sem merge/deploy dos PRs de teste.
- **Dependência:** REI-69 fornece vínculo processo→setor responsável. REI-88 não deve tratar leitura de sigiloso como autorização de escrita; alteração explícita deve seguir permissão de operação e acesso ao processo.
- **Próximo gate:** fechar matriz caller→writes→transação→propagação e testar os caminhos que escrevem antes da validação ou fora da transação.

## Reconciliação e limites de REI-73

1. Relações nativas restantes em Linear: REI-66, REI-67, REI-68, REI-69, REI-70, REI-72 e REI-88. A relação com REI-71 foi removida após confirmar seu estado `Concluído`; a issue continua listada nesta matriz como recorte concluído, sem bloquear a auditoria. Relações adicionais não foram promovidas a bloqueio sem evidência. PR #430 foi mesclado por Jorge; `origin/main` agora aponta para `bca62b990c2893937e723e23de50651d67736cd7`. PRs #410, #415 e #429 integraram essa base por merge normal e continuam abertos.
2. PRs não formam correspondência um-para-um. REI-66 e REI-71 aparecem integradas/deployadas segundo evidência registrada; REI-67 carece de candidato atribuível; REI-72 tem candidatos sobrepostos; REI-88 compartilha trabalho com REI-78.
3. Documentos ligados à auditoria (inventário textual de APIs de 15/11 e auditoria WebApp de 15/11) declaram limites heurísticos/estáticos, usam baseline anterior e não cobrem integralmente rotas atuais, dependências, fronteiras de dados, sessões autenticadas, UI renderizada ou infraestrutura. Contagem textual não equivale a matriz de autorização validada.
4. Para REI-93, a sincronização e os testes de rejeição foram reproduzidos no HEAD `ae93c5a…`; os checks CI daquele SHA estão verdes. A suíte positiva, smoke, deploy, migration e acesso a dados reais não foram executados. Para as outras evidências, comentários, PRs e runs indicados são a origem; revalidar se SHA mudou.
5. **REI-73 continua incompleta.** Faltam inventário executável de rotas/operações e controles, revisão de fluxos críticos/recuperação/concorrência, UI e autenticação isolada, reconciliação integral com issues e limitações por seção. Não mover REI-73 para concluída com esta matriz.

**Snapshot de PRs abertos (2026-11-17, consulta após PR #433):** 16 PRs estão abertos e `MERGEABLE`, sem conflito de base: #406, #407, #408, #410, #411, #412, #413, #415, #416, #425, #427, #428, #429, #431, #432 e #433. #407 e #408 estão prontos para revisão; os outros 14 permanecem em draft, incluindo #413 após finding P1 de deploy aceito. #405 e #409 foram fechados como duplicados de #428 após confirmar que seus commits estão contidos nele; nenhum PR foi mesclado nesta atualização. #406, #407, #408, #411, #412, #415, #416 e #425 foram sincronizados por merge normal. Em #408, os cinco testes direcionados e os dois checks de PHP lint passaram após a sincronização. Revisão independente não encontrou achados funcionais ou de segurança P1/P2; o apontamento documental P3 foi corrigido e o PR foi marcado pronto para revisão formal. Não há decisão de revisão registrada nem smoke autenticado. Na consulta atual, checks por HEAD dos 16 PRs estão SUCCESS, inclusive os três de lint e o teste de interação do #413. `reviewDecision` está vazio nos PRs consultados. `MERGEABLE` significa apenas ausência de conflito de branch; revisão, aceitação funcional e gates de ambiente permanecem independentes.

**Próxima ação recomendada:** priorizar revisão formal e gates de aceite dos PRs `MERGEABLE`, começando por #408 e #407. #407 continua bloqueado por REI-73. #413 teve o P1 de workflow corrigido e permanece draft até validar fonte CLI segura, migration pré-publicação no DEV e smoke autenticado. #408 não tem decisão formal e depende de gates de ambiente. REI-67 agora tem candidato #433, ainda bloqueado por esta auditoria e sem deploy. Não há conflito de branch no inventário aberto. A política de aprovação da main segue sem confirmação (API retornou 403). Não concluir issues até demonstrar critérios funcionais e operacionais.
