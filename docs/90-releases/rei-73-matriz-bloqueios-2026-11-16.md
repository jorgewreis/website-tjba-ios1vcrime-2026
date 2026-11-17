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
| REI-68 | PR #411 pronto para revisão formal, HEAD `aa67922557a6b82ef650e91de5c86ea6a1cb4e32`, base `bca62b99`, MERGEABLE | Sincronizado por merge normal; upload 7 cenários, origem 18 casos, PHP lint e diff-check passaram; checks exatos `44005804836`/`44005807687` SUCCESS; revisão independente sem blocker de código | Ainda sem review formal no GitHub; UI autenticada e validação de sessão/DB/proxy reais pendentes; suíte positiva de auth/atas continua condicionada ao gate REI-93; mantém dependência REI-73 |
| REI-69 | PR #415 draft, HEAD `ef3b70d3838604e72c90bf66ba87474b337b3ea3`, base `bca62b99`, MERGEABLE | Após merge normal de main: testes SQLite, 27 cenários do guard, runner MySQL/InnoDB descartável e PHP lint passaram; checks exatos `44006419836` e `44006425307` passaram | REI-73 continua dependência; migração `ALGORITHM=COPY` não aplicada, volume/espaço/janela precisam de validação operacional; browser/sessão real ausentes |
| REI-70 | PR #407 não draft, HEAD `211d5fb786602cfc51ae24e49de0cbd82935cc5a`, base `bca62b99`, MERGEABLE | PHP lint `44006335659`/`44006338607`; revisão independente sem blocker nos 12 endpoints; runner MySQL 8.4/InnoDB no HEAD atual passou 20 leituras, 40 recusas sem escrita e 8 escritas autorizadas, com limpeza confirmada | Review formal pendente; sessão/browser/proxy reais não demonstrados; Linear mantém bloqueio por REI-73 |
| REI-71 | Sem PR aberto identificado | Testes sintéticos reportados em `b8cee7c…`; ancestral do SHA publicado `f5e657fc…`, run `43839505824` | Sessões e perfis autenticados reais não cobertos; issue concluída não encerra auditoria global |
| REI-72 | PR #428 draft, HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, base `bca62b99` | Após merge normal: testes reais SQLite e HTTP sintético afetados passaram; checks exatos `44001675569` e `44001683252` passaram | Concorrência MySQL, browser/sessão e política de autorização por registro seguem pendentes |
| REI-93 (gate de runner) | PR #410 draft, HEAD `ae93c5a90acc59040837128c4bd9ab636865a751`, base `bca62b99` | Rebase por merge normal; guard-rejection tests (3 casos), PHP lint e `git diff --check` passaram; checks exatos `44003036029` e `44003043091` passaram; MERGEABLE | Testes positivos continuam suspensos até comprovar identidade/procedência do daemon, isolamento DB/SMTP/rede e limpeza limitada; sem aprovação formal |
| REI-88 | PR #412 HEAD `9864fee20366a79a4a70159b71247361fbd85660`; #416 HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72` | Heads atuais na base `bca62b99`, MERGEABLE; checks lint #412 `44007291338`/`44007287960` e #416 `44007610762`/`44007606352` SUCCESS | Autorização de mudança de sigilo, transações de todos os callers e suíte MySQL no CI pendentes; PR relacionada #413 (REI-78) HEAD `3ae132890cbfeded4866f6221558c3735d6361b9`; checks de interação `44014409184` e lint `44014409208`/`44014402312` SUCCESS. Finding P1 resolvido no código: DEV/PROD executam migration staged antes do rsync; revisão Codex sem major issues e independente favorável. Tentativa DEV anterior `44011610849` falhou antes da conexão DB CLI e rollback `44011900429` restaurou SHA reportado `82b80a54…`; migration não foi reexecutada após a correção. PR #413 agora está pronto para revisão formal; fonte CLI segura, migration pós-correção e smoke autenticado seguem pendentes. Revisões automatizadas anteriores cobrem SHAs antigos; nova revisão solicitada no HEAD atual |

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

- **Candidato:** PR #411, base `bca62b99…`, HEAD `aa67922557a6b82ef650e91de5c86ea6a1cb4e32`, pronto para revisão formal. Comentários registram ajuste para `ApiAccessGuard::requireAccess(['salaata'], true)`, teste endpoint com 7 cenários e guard POST com 18. Revisão independente não encontrou blocker de código; seguem pendentes smoke autenticado, sessão/DB reais e dependência REI-73.
- **CI no SHA exato:** checks `44005804836` e `44005807687` concluíram SUCCESS; cobrem PHP lint, não fluxo funcional autenticado.
- **Bloqueado por ambiente/não verificado:** upload autenticado via browser pendente; suites protegidas dependem de sandbox autorizado. Revisão independente de código concluída sem blocker, mas review formal do GitHub segue pendente.
- **Próximo gate:** executar UI autenticada em fixture isolada quando o Chrome DevTools MCP e ambiente estiverem disponíveis; obter review formal sobre o HEAD final.

### REI-69 — partes do processo e autorização por unidade responsável

**Aceite acompanhado:** inventariar endpoints/consumidores; exigir sessão, permissão de operação e autorização do registro para leitura/escrita; proteger método/origem em mutações; rejeições sem escrita e permitir caso autorizado; revisar endpoints relacionados.

- **Candidato:** PR #415, base `bca62b99…`, HEAD `ef3b70d3838604e72c90bf66ba87474b337b3ea3`, draft. Política decidida: permissão da operação mais unidade responsável, via `processos.id_setor_responsavel → setores.id_setor`; leitura sigilosa não concede escrita. Ausência de setor legado falha fechada; sem backfill automático.
- **Validado no SHA exato (parcial):** checks `44006419836` e `44006425307` passaram PHP lint. PR/comentário reporta runner sintético SQLite e MySQL 8.4/InnoDB, positivos/negativos de leitura/mutação e migração idempotente/rollback; não reproduzido nesta revisão.
- **Somente inspeção estática/bloqueado por ambiente:** migração aditiva nullable `CHAR(30)` e FK usa `ALGORITHM=COPY, LOCK=SHARED`; não foi executada em DEV/PROD. Medição de volume/espaço e janela de mudança necessárias. Sessão/browser real não demonstrados.
- **Próximo gate:** revisar migração contra schema real e preparar medição/plano antes de aplicar; manter legado sem responsável negado até atribuição autorizada.

### REI-70 — instituições públicas e política CONSULTOR

**Aceite acompanhado:** inventário de endpoints; sessão/operação/registro e proteção de escrita; pedidos anônimos/não autorizados sem mutação; CONSULTOR externo limitado à instituição/setor/vínculos ativos, preservando concessões internas de perfil misto.

- **Candidato:** PR #407, base `bca62b99…`, HEAD `211d5fb786602cfc51ae24e49de0cbd82935cc5a`, aberto, não draft e MERGEABLE.
- **Evidência no HEAD atual:** policy8, guard52, anônimo12; SQLite com 40 recusas sem escrita e 8 escritas autorizadas; HTTP sintético; MySQL 8.4/InnoDB descartável executado nesta atualização: 20 leituras, 40 recusas sem escrita e 8 escritas autorizadas. O runner encerrou com exit 0, confirmou schema/arquivos sintéticos removidos e sentinela preservada. Usa Docker Desktop local, rede interna, banco em tmpfs sem portas/mounts e código readonly.
- **CI no SHA exato:** checks `44006335659` e `44006338607` passaram PHP lint. Review formal ainda pendente. O schema do teste é sintético; não demonstra schema real, sessão de produção, proxy ou autorização em ambiente real.
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
- **Candidatos atuais:** #412 (Carta, HEAD `9864fee20366a79a4a70159b71247361fbd85660`) e #416 (Pauta, HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72`), ambos sobre base `bca62b99…`, drafts e MERGEABLE. Checks lint exatos passaram: #412 `44007291338`/`44007287960`; #416 `44007610762`/`44007606352`. PR #413 (REI-78, HEAD `3ae132890cbfeded4866f6221558c3735d6361b9`) está MERGEABLE e pronto para revisão formal. Checks de interação `44014409184` e lint `44014409208`/`44014402312` passaram; revisão Codex não encontrou major issues e revisão independente confirmou a correção do P1. O P1 foi resolvido no código: migration staged roda antes do rsync em DEV/PROD. A tentativa DEV anterior `44011610849` falhou antes de executar SQL por configuração DB CLI ausente; rollback reportado `44011900429` restaurou o SHA `82b80a54…`. A nova migration ainda não foi executada: configuração CLI segura e smoke autenticado permanecem gates de ambiente.
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

**Snapshot de PRs abertos (2026-11-17, reconciliado após abertura do #435):** 17 PRs estão abertos e `MERGEABLE`, sem conflito de base: #406, #407, #408, #410, #411, #412, #413, #415, #416, #425, #427, #428, #429, #431, #432, #433 e #435. #406, #407, #408, #411, #413 e #435 estão prontos para revisão formal; os outros 11 permanecem em draft. Em #406 (REI-130), os checks exatos `44005434964`/`44005441059` passaram, revisão independente não encontrou blocker de código, e os cinco testes sintéticos da issue foram reexecutados no HEAD `899383612e0f2d7795abd3d04b5742ad69b01047`, todos PASS; Codex code/security reviews também sem findings. MySQL concorrente, navegador autenticado e ambiente continuam pendentes. #407 permanece bloqueada por REI-73; #408 aguarda revisão formal e gates de ambiente. #411 recebeu revisão independente sem blocker de código e passou a pronto para revisão formal; permanece sem aprovação formal, depende da REI-73 e aguarda smoke autenticado/sessão real. #413 também passou a pronto para revisão formal após correção do P1 de migração pós-rsync; configuração DB CLI, migration pós-correção e smoke DEV não foram validados. #435 (REI-73) está pronto para revisão; lint do HEAD `dc410b552019a4af40664b8084f32862976c7de1` (run `44022278280`) passou e revisão independente não encontrou blocker no diff, mas não há aprovação formal e os gates gerais da auditoria permanecem. Foram solicitadas novas revisões automatizadas para #406, #407, #411 e #413; ainda não há resultado observável para esses pedidos. #405 e #409 foram fechados como duplicados de #428 após confirmar contenção dos commits; nenhum PR foi mesclado nesta atualização. A consulta mostrou checks por HEAD SUCCESS nos 17 PRs, inclusive o teste de interação do #413; #431 está no HEAD `a746c23da000b506550967e355aaf7514b7cdb5b` e seu lint (run `44022732784`) passou. Nenhum PR listado tem aprovação formal; branch protection retorna 403 e não foi possível confirmar a política obrigatória de aprovação. `MERGEABLE` significa apenas ausência de conflito de branch; revisão, aceitação funcional e gates de ambiente permanecem independentes.

**Adendo pós-snapshot (2026-11-17):** foi aberto o PR #435 (`fix(REI-73): validate student enrollment session and origin`), HEAD `dc410b552019a4af40664b8084f32862976c7de1`, pronto para revisão e `MERGEABLE`. No HEAD da base `bca62b99`, `sala-audiencias-ata/inscrever.php` aceitava ID de sessão sem validar token ativo/inatividade nem origem confiável do POST. #435 aplica `ApiAccessGuard::requireAccess(['estudapainel'], true)` antes de consultar usuário ou serviço e preserva a exigência de estudante externo. O harness HTTP sintético e os 52 cenários do guard com dependências simuladas passaram; PHP lint passou; CI do HEAD `dc410b55` (run `44022278280`) concluiu SUCCESS. Revisão independente não encontrou blocker no diff. O PR ainda não tem aprovação formal nem foi mesclado; portanto a rota permanece aberta na base e a REI-73 continua incompleta. O teste não valida token no banco nem sessão/browser real.

**Próxima ação recomendada:** obter review formal em #435, #406, #407, #408, #411 e #413; pedidos automatizados recentes para #406, #407, #411 e #413 ainda aguardam resultado, e #435 não tem aprovação formal registrada. #407 continua bloqueado por REI-73. #406 está pronto para review, mas manter gates MySQL concorrente, navegador autenticado e ambiente antes de merge/deploy. #411 tem revisão independente favorável ao código, com smoke autenticado e dependência REI-73 pendentes. #413 teve o P1 de workflow corrigido e está pronto para review; fonte CLI segura, migration pré-publicação no DEV e smoke autenticado continuam pendentes. #408 depende de revisão formal e gates de ambiente. REI-67 tem candidato #433, ainda bloqueado por esta auditoria e sem deploy. Não há conflito de branch no inventário aberto. A política de aprovação da main segue sem confirmação (API retornou 403). Não concluir issues até demonstrar critérios funcionais e operacionais.

## Apêndice A — inventário estático de rotas no snapshot atual

**Snapshot e método:** `bca62b990c2893937e723e23de50651d67736cd7`, lido do blob versionado (`git ls-tree`/`git archive`), não do checkout principal sujo em `d2d05a8e48be47e685afd7bcd76e9ff5d6b0aaf9`. Em 2026-11-17 a varredura encontrou 154 arquivos PHP sob `public/api`: 150 handlers presumidos pelo caminho físico e quatro helpers/configurações. O baseline `f195d596` tinha 153 arquivos; a adição é `cartorio-editais/reconciliar.php`.

| Sinal observado no handler | Total | Método/controle que o código visível indica | Limite |
|---|---:|---|---|
| `ApiAccessGuard`, escrita | 88 | `write=true`: autenticação, permissão de página/ação, POST e origem confiável | Perfil/permission key deve ser lido por handler; guard não prova autorização por objeto |
| `ApiAccessGuard`, leitura | 22 | Autenticação e permissão de página | Cinco impõem GET; 17 não restringem o método no próprio handler |
| Controles próprios/helper | 20 | Sessão, perfil, página e/ou método; nove mutações adicionais chamam POST/origem confiável | Helpers/dispatch dinâmico e semântica por objeto precisam rastreamento |
| Só presença de `$_SESSION['id_usuario']` | 12 | Existência de sessão identificada | Não demonstra perfil, autorização da operação, origem ou autorização do registro |
| Público, desativado ou tombstone | 8 | Inclui cadastro público, catálogo, busca pública, feature flag, 503 e 404 fixo | A classificação por handler não verifica proxy, cotas ou configuração externa |
| Helper/configuração, não handler | 4 | Excluídos da contagem de rotas | Falsos positivos da busca por arquivos PHP |

### 110 handlers com `ApiAccessGuard`

As **88 escritas** que chamam o guard com `write=true` são:

- `cartorio-cartas-expedidas`: `cancel`, `concluir-subtarefa`, `create`, `criar-primeira-subtarefa`, `delete`, `sync-processo-datajud`, `update`.
- `cartorio-cartas-recebidas`: `avancar`, `cancel`, `concluir-subtarefa`, `create`, `criar-primeira-subtarefa`, `delete`, `update`.
- `cartorio-editais`: `cancel`, `concluir-subtarefa`, `create`, `criar-primeira-subtarefa`, `delete`, `reconciliar`, `update`.
- `cartorio-guias`: `alocar`, `cancel`, `cancelar-subtarefa`, `concluir-subtarefa`, `create`, `criar-primeira-subtarefa`, `delete`, `desalocar`, `update`.
- `cartorio-oficios`: `cancel`, `concluir-subtarefa`, `create`, `delete`, `desalocar`.
- `gabinete-monitoramento-eletronico`: `cancel`, `concluir-subtarefa`, `create`, `delete`, `reus-partes`.
- `gabinete-oficios`: `cancel`, `create`, `delete`.
- `gabinete-reus-presos`: `add-movimentacao`, `archive`, `create`, `delete-movimentacao`, `processo-meta`, `save-processo`, `send-revisao-resumo-email`, `set-principal`, `unlink-processo`, `update`.
- `instituicoes-publicas`: `create`, `update`; `instituicoes-publicas/setores`: `create`, `delete`, `update`; `instituicoes-publicas/telefones`: `create`, `delete`, `update`.
- `pessoas`: `create`, `update`; `pessoas/documentos`: `create`, `delete`; `pessoas/telefones`: `create`, `delete`, `update`; `pessoas/vinculos-instituicoes`: `create`, `delete`, `update`.
- `processos-partes`: `create`, `delete`, `update`; `processos`: `prioridades-add`, `update-classe`.
- `sala-audiencias-pauta`: `alocar`, `cancel`, `create`, `delete`, `desalocar`, `partes-create`, `partes-delete`, `partes-update`, `prioridades-add`, `prioridades-remove`, `set-status-painel`, `update`.

As **22 leituras** que chamam o guard com `write=false` são:

- `cartorio-cartas-expedidas`: `buscar-cidade`, `list`; `cartorio-cartas-recebidas`: `buscar-cidade`, `list`; `cartorio-editais`: `list`.
- `cartorio-guias`: `check-duplicate`, `list`, `reus-por-processo`; `consulta-processos`: `get`, `list`.
- `gabinete-reus-presos`: `get`, `list`; `instituicoes-publicas`: `get`, `list`, `search`, `setores`.
- `pessoas`: `get`, `list`, `search-by-name`; `processos-partes`: `list`; `sala-audiencias-pauta`: `occupied-slots`, `prioridades-list`.

Os cinco endpoints de leitura com GET explícito são `instituicoes-publicas/{get,list,search,setores}.php` e `processos-partes/list.php`. Os outros 17 não restringem método no handler.

### Controles próprios, sessão simples e endpoints sem autenticação local

**20 handlers com controle próprio/helper:** `admin/feriados/{create,delete,list,update}.php`; `alterar_senha.php`; `cartorio-cartas/reiniciar.php`; `cartorio-cejusc/{create,delete,list,update}.php`; `dashboard/resumo.php`; `internal-list-export/{csv,pdf}.php`; `sala-audiencias-ata/{download-interno,download,inscrever,upload}.php`; `sala-audiencias-pauta/{resumo,status-bulk}.php`; `session/set-admin-mode.php`. Nove mutações (três feriados, reiniciar carta, três CEJUSC, status-bulk e set-admin-mode) chamam verificação de POST/origem confiável. `alterar_senha.php` exige autenticação, POST e token CSRF; os dois exports delegam acesso ao `_common.php` e dispatch dinâmico. Os downloads de atas checam inscrição usuário–audiência no serviço (`SalaAudienciasAtaService.php`), enquanto `inscrever.php` e `upload.php` não mostram guard de origem nem token CSRF no handler.

**12 handlers que apenas verificam presença de identidade de sessão no próprio endpoint:** `assuntos/find-by-codigo.php`, `classes/find-by-codigo.php`, `processos/{assunto-link,assunto-read,assunto-sync,assunto-unlink,classe-read,classe-sync,datajud-sync,prioridades-list,prioridades-read,prioridades-remove}.php`. Seis são mutações (`assunto-link`, `assunto-sync`, `assunto-unlink`, `classe-sync`, `datajud-sync`, `prioridades-remove`) e não restringem POST no handler. Os serviços centrais de assunto/classe/prioridade examinados recebem IDs e conexão, não a identidade do chamador; isso é lacuna de evidência para investigar, não prova de exploração. `prioridades-add` e `update-classe` são exceções guardadas no mesmo módulo. Esse conjunto se relaciona com REI-72, REI-75 e REI-76; não criar tarefa duplicada sem comparar os aceites e PRs existentes.

**Oito handlers públicos/desativados/tombstone:** `cadastro_estudantes.php` (cadastro público POST), `email_hint.php` (404 fixo), `config/toast-messages.php` (catálogo GET/HEAD), `datajud/search-processo.php` (busca pública com CORS `*`), `mni/consultar-processo.php` (503 anterior à lógica de autenticação), `pje-publico/search-processo.php` (flag por padrão desabilitada), `sala-audiencias-pauta/tipos-list.php` (catálogo) e `tjba/metas-cartorio.php` (integração pública). Alcance, cotas, proxy e configuração operacional não foram verificados.

**Quatro falsos positivos, não rotas:** `admin/_auth_admin_diretora_json.php`, `datajud/config.php`, `mni/config.php` e `internal-list-export/_common.php`.

### Resultado e limites do apêndice

Chamadas diretas indicativas foram vistas, por exemplo, em `public/api/cartorio-cartas-expedidas/create.php`, `public/api/processos-partes/create.php` e `public/api/sala-audiencias-pauta/create.php`. `ApiAccessGuard` autoriza por página/operação, não recebe ID de registro e, sozinho, não prova autorização por objeto. O helper de origem compara `Origin`/`Referer` com a origem da requisição e requer POST; não é token CSRF. `.htaccess` versionado não declara autenticação para `public/api`; servidor/proxy efetivos não foram inspecionados.

Esta contagem classifica comportamento textual no handler, não mapeia ainda cada permission key/perfil resolvido dinamicamente nem cada regra de serviço/repositório. Não foram feitos requests, sessão autenticada, leitura de configuração de produção, testes ou inferências de explorabilidade nesta varredura. A matriz por permissão exata e o rastreamento de autorização por objeto seguem pendentes; esta ampliação não muda REI-73 para concluída nem remove suas dependências.

## Delta de validação — 2026-11-17

Esta seção complementa o snapshot acima e registra verificações executadas depois da revisão inicial da matriz.

- **REI-76 / PR #428:** no worktree limpo e no HEAD e83aced08b63ae6b4d7b51d4d67552225922217a, passaram seis testes HTTP, guard, mapper, sincronização e rollback: classe-object-http, datajud-sync-objeto-http, rei76-access-sintetico, mapper-sync-real, sync-atomico e sync-rollback-repositorios. Os testes cobrem perfis/processo local, proveniência, semântica de lista vazia explícita e rollback com dados sintéticos/SQLite. Os checks PHP lint 44001675569 e 44001683252 também passaram. Revisão independente read-only não encontrou blocker estático. Permanecem sem prova browser/sessão real, DataJud externo, concorrência MySQL, aprovação formal e resolução do gate REI-73.
- **REI-88 / PR #416:** no worktree limpo e no HEAD 098cce0f3f22c18ea97e4b077b68f8a47d4adf72, o runner run-pauta-transaction-mysql-isolated.ps1 terminou com exit 0 em MySQL 8.4/InnoDB descartável. Passaram cinco cenários de preservação do sigilo/classe, rejeição sem escrita parcial, rollback pós-insert, rollback de processo novo e criação nova válida. Cleanup removeu schema/containers/volume próprios, preservou a sentinela e uma pós-checagem encontrou zero containers/volumes rotulados. git diff --check passou. A revisão independente confirmou um gap em Cartas Expedidas: payload truthy ou classe ausente pode elevar sigilo sem consultaprocessosedit nem autorização do processo. A regra depende da autorização por processo da REI-69; a issue permanece aberta.
- **REI-73 / PR #431:** acrescentado neste branch um inventário estático versionável do snapshot bca62b990c2893937e723e23de50651d67736cd7: 154 arquivos PHP, 150 handlers presumidos e quatro helpers/configurações; 88 escritas e 22 leituras com ApiAccessGuard, 20 handlers com controles próprios, 12 que apenas testam presença de id_usuario, oito públicos/desativados/tombstones e quatro falsos positivos. As rotas foram agrupadas por módulo/operação e limitações de método/origem/objeto foram registradas. Ainda falta validar permission key/perfil exato e rastrear todos os serviços/objetos.

A classificação acima é estática e não representa execução de autenticação, proxy ou integração externa. Os worktrees de teste REI-76 e REI-88 permaneceram limpos; o único diff local desta atualização está neste documento da PR #431. Nenhuma issue foi fechada por esta evidência parcial.

### Apêndice B — resolução estática das chaves e perfis

**Fontes no mesmo snapshot `bca62b990c2893937e723e23de50651d67736cd7`:** `ApiAccessGuard`, `PageAccessRegistry`, `AccessEvaluator`, `ProfileNormalizer`, `UsuarioPerfisAutorizacao` e `Usuario`. A análise abaixo descreve as regras de código; não consulta cadastros de usuários e não prova a autorização efetiva de uma sessão real.

- `ApiAccessGuard::requireAccess` carrega perfis associados ao usuário da sessão, aplica a supressão de `ADMIN` conforme o modo operacional e chama `AccessEvaluator::canAccessPage`. Quando recebe múltiplas chaves, o primeiro acesso autorizado concede; as chaves são alternativas em OR. `write=true` também requer POST e origem confiável. A origem confiável compara `Origin`/`Referer` com a origem da requisição e não equivale a token CSRF.
- `area_a` exige perfil composto `ESTAGIARIO-{CARTORIO|GABINETE|AUDIENCIAS}` ou `SERVIDOR-{CARTORIO|GABINETE|AUDIENCIAS}` da área correspondente. `ADMIN` e `CHEFIA` passam pelo bypass elevado. `DIRETORA` e `MAGISTRADA` são normalizados para `CHEFIA`.
- `all_internal`, `consultas_partes` e `consultas_processos` exigem perfil interno composto ou bypass elevado. `CONSULTOR` externo tem acesso apenas às páginas `consultapartes`, `instituicoespublicas` e `partesinstituicoeslist`; não recebe acesso a consultas de processos. Se classificado como interno, o ramo externo não é usado.
- `EXACT_PAGE_PROFILES` está vazio: nenhuma chave aplica atualmente lista de perfis exatos. A capacidade `exact` existe no código, mas não é regra ativa.
- Chave desconhecida resulta em `deny`, antes do bypass de perfil elevado. Isso se aplica a `estudaata`, chamada por `public/api/sala-audiencias-ata/download.php:30`, mas ausente do registry: o endpoint rejeita também ADMIN/CHEFIA por essa verificação. `estudapauta` e `estudapainel`, em contraste, têm regra `estudantes_readonly` (ADMIN interno; estudante externo).

**Exemplos de chaves nos handlers inspecionados:** `cartorio-cartas-expedidas/create.php` exige `cartasexpedidascreate`; `processos-partes/create.php` aceita `processospartescreate` OU `consultaprocessosedit`; `processos-partes/delete.php` aceita `processospartesedit` OU `consultaprocessosedit`; `processos/update-classe.php` exige `consultaprocessosedit`. A rota `sala-audiencias-ata/download.php` tem a chave não registrada acima. A lista integral handler→chave e o rastreamento dos serviços/objetos ainda precisam ser anexados após revisar todas as chamadas dinâmicas e condições locais.

**Implicação para REI-73:** mesmo essa resolução estática não demonstra vínculo/escopo por registro, perfis realmente atribuídos, sessão/autenticação de navegador, origem vista no proxy ou controles de infraestrutura. A exceção `estudaata` é um bloqueio funcional aparente a confirmar com critério da issue/fluxo consumidor antes de correção; não presumir que todo perfil deva acessar download de ata.

### Apêndice C — chaves por handler nos 110 endpoints guardados

Inventário extraído do snapshot `bca62b990c2893937e723e23de50651d67736cd7`. Em cada combinação OR, basta uma chave permitida. A regra da chave é descrita no Apêndice B; a tabela não prova autorização por objeto.

**Escritas `write=true` (88):**

| Chave(s) | Handlers |
|---|---|
| `cartasexpedidascreate` | `cartorio-cartas-expedidas/create.php` |
| `cartasexpedidasedit` | `cartorio-cartas-expedidas/{cancel,concluir-subtarefa,criar-primeira-subtarefa,delete,sync-processo-datajud,update}.php` |
| `cartasrecebidascreate` | `cartorio-cartas-recebidas/create.php` |
| `cartasrecebidasedit` | `cartorio-cartas-recebidas/{avancar,cancel,concluir-subtarefa,criar-primeira-subtarefa,delete,update}.php` |
| `consultapartescreate` OR `consultaprocessosedit` | `pessoas/create.php` |
| `consultapartesedit` | `pessoas/update.php` |
| `consultapartesedit` OR `partesinstituicoesedit` OR `partesinstituicoescreate` | `pessoas/documentos/{create,delete}.php`; `pessoas/telefones/{create,delete,update}.php`; `pessoas/vinculos-instituicoes/{create,delete,update}.php` |
| `consultaprocessosedit` | `processos/update-classe.php` |
| `editaiscreate` | `cartorio-editais/{create,criar-primeira-subtarefa}.php` |
| `editaisedit` | `cartorio-editais/{cancel,concluir-subtarefa,delete,reconciliar,update}.php` |
| `guiascreate` | `cartorio-guias/create.php` |
| `guiasedit` | `cartorio-guias/{alocar,cancel,cancelar-subtarefa,concluir-subtarefa,criar-primeira-subtarefa,delete,desalocar,update}.php` |
| `instituicoespublicascreate` | `instituicoes-publicas/create.php` |
| `instituicoespublicasedit` | `instituicoes-publicas/update.php`; `instituicoes-publicas/setores/{create,delete,update}.php`; `instituicoes-publicas/telefones/{create,delete,update}.php` |
| `monitoramentogabinetecreate` | `gabinete-monitoramento-eletronico/{create,reus-partes}.php` |
| `monitoramentogabineteedit` | `gabinete-monitoramento-eletronico/{cancel,concluir-subtarefa,delete}.php` |
| `oficioscartorioedit` | `cartorio-oficios/{cancel,concluir-subtarefa,create,delete,desalocar}.php` |
| `oficiosgabinete` | `gabinete-oficios/create.php` |
| `oficiosgabineteedit` | `gabinete-oficios/{cancel,delete}.php` |
| `processospartescreate` OR `consultaprocessosedit` | `processos-partes/create.php` |
| `processospartesedit` | `processos-partes/update.php` |
| `processospartesedit` OR `consultaprocessosedit` | `processos-partes/delete.php` |
| `reuspresoscreate` | `gabinete-reus-presos/create.php` |
| `reuspresosedit` | `gabinete-reus-presos/{add-movimentacao,archive,delete-movimentacao,processo-meta,save-processo,send-revisao-resumo-email,set-principal,unlink-processo,update}.php` |
| `salapainel` | `sala-audiencias-pauta/set-status-painel.php` |
| `salapautacreate` | `sala-audiencias-pauta/create.php` |
| `salapautaedit` | `processos/prioridades-add.php`; `sala-audiencias-pauta/{alocar,cancel,delete,desalocar,prioridades-add,prioridades-remove,update}.php` |
| `salapautaparteedit` | `sala-audiencias-pauta/{partes-create,partes-delete,partes-update}.php` |

**Leituras `write=false` (22):**

| Chave(s) | Handlers | Método explícito |
|---|---|---|
| `cartasexpedidas` | `cartorio-cartas-expedidas/{buscar-cidade,list}.php` | Nenhuma restrição local observada |
| `cartasrecebidas` | `cartorio-cartas-recebidas/{buscar-cidade,list}.php` | Nenhuma restrição local observada |
| `consultapartes` | `pessoas/{get,list,search-by-name}.php` | Nenhuma restrição local observada |
| `consultaprocessos` | `consulta-processos/{get,list}.php` | Nenhuma restrição local observada |
| `consultaprocessos` OR `processosparteslist` | `processos-partes/list.php` | GET |
| `editais` | `cartorio-editais/list.php` | Nenhuma restrição local observada |
| `guias` | `cartorio-guias/{check-duplicate,list,reus-por-processo}.php` | Nenhuma restrição local observada |
| `reuspresos` OR `reuspresossubstituto` | `gabinete-reus-presos/{get,list}.php` | Nenhuma restrição local observada |
| `salapautaedit` | `sala-audiencias-pauta/{occupied-slots,prioridades-list}.php` | Nenhuma restrição local observada |
| `instituicoespublicas` | `instituicoes-publicas/{get,list,search}.php` | GET |
| `instituicoespublicas` OR `partesinstituicoescreate` | `instituicoes-publicas/setores.php` | GET |

**Pendência:** reconciliar todas as rotas fora do guard, autorização por registro e fluxos de perfil em sessão real. Os seis handlers de mutação session-only, as limitações externas e a chave `estudaata` não registrada estão enumerados nos Apêndices A/B.
