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
| REI-68 | PR #411 não draft, HEAD `bd7763c1eed4673b573a9977664fca5e24c5bce7`, base `d7683cc9`, MERGEABLE | No HEAD sincronizado passaram upload 7 casos sintéticos, origem 18, PHP lint, SQLite pauta e diff-check; CI PHP Syntax `44033967517` SUCCESS | REI-73 continua bloqueadora; sessão/token contra DB, UI autenticada, proxy/TLS e ambiente publicado sem validação |
| REI-69 | PR #415 draft, HEAD `62e56f54d56e0e5f8aef4be8f5b13963b32c5821`, base `d7683cc9`, MERGEABLE | Testes de listagem/mutação por setor, guard 27 e transação de pauta SQLite passaram após sync; CI `44030341991` SUCCESS | REI-73 continua dependência; migration `ALGORITHM=COPY` exige volume/espaço/janela validados; runner MySQL da REI-69 não foi repetido após sync; sem browser/sessão real |
| REI-70 | PR #407 não draft, HEAD `7cf7942632e20cb7be421e8ef6f5b5a7216f45d2`, base `d7683cc9`, MERGEABLE | Policy 8, guard 52, anônimo 12, SQLite 40 recusas/8 escritas, CONSULTOR ativo/HTTP e MySQL 20 leituras/40 recusas/8 escritas passaram; CI `44033757808` SUCCESS | Linear mantém REI-73 como bloqueio; navegador/sessão/proxy live não demonstrados |
| REI-71 | Sem PR aberto identificado | Testes sintéticos reportados em `b8cee7c…`; ancestral do SHA publicado `f5e657fc…`, run `43839505824` | Sessões e perfis autenticados reais não cobertos; issue concluída não encerra auditoria global |
| REI-72 | PR #428 draft, HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, base `bca62b99` | Após merge normal: testes reais SQLite e HTTP sintético afetados passaram; checks exatos `44001675569` e `44001683252` passaram | Concorrência MySQL, browser/sessão e política de autorização por registro seguem pendentes |
| REI-93 (gate de runner) | PR #410 draft, HEAD `0b13fb09cdfedb9242ccb79b2fe96c209c87d55e`, base `bca62b99` | Merge normal; 8 cenários sintéticos de limpeza, teste guard-rejection, parse PowerShell e `git diff --check` PASS; revisão independente sem blocker; checks lint `44026941255`/`44026947831` SUCCESS; MERGEABLE | Runner positivo permanece suspenso: identidade/procedência do daemon e imagens, isolamento DB/SMTP/rede e investigação histórica não comprovados; sem teste de banco/Docker real nesta atualização |
| REI-88 | PR #412 draft, HEAD `9864fee20366a79a4a70159b71247361fbd85660`, base antiga `bca62b99`, não MERGEABLE; #416 mesclada em `d7683cc9`; #413 relacionada (REI-78) está em `3ad3bf9115adcd3226801db185fbfe0c06cf15d4`, base d768, MERGEABLE | Testes #416 MySQL/SQLite e checks `44007610762`/`44007606352` passaram antes do merge; #413 após sync tem CI `44034596685`/`44034596667` SUCCESS | Autorização explícita para alterar sigilo e acesso ao processo seguem pendentes em #412/REI-88; dependências REI-69/73 não removidas; deploy/smoke de #413 não ocorreu |

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
- **Candidatos atuais:** #412 (Carta, HEAD `9864fee20366a79a4a70159b71247361fbd85660`, draft) e #416 (Pauta, HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72`, pronto para revisão), ambos sobre base `bca62b99…` e MERGEABLE. Checks lint exatos passaram: #412 `44007291338`/`44007287960`; #416 `44007610762`/`44007606352`. PR #413 (REI-78, HEAD `3ae132890cbfeded4866f6221558c3735d6361b9`) está MERGEABLE e pronto para revisão formal. Checks de interação `44014409184` e lint `44014409208`/`44014402312` passaram; revisão Codex não encontrou major issues e revisão independente confirmou a correção do P1. O P1 foi resolvido no código: migration staged roda antes do rsync em DEV/PROD. A tentativa DEV anterior `44011610849` falhou antes de executar SQL por configuração DB CLI ausente; rollback reportado `44011900429` restaurou o SHA `82b80a54…`. A nova migration ainda não foi executada: configuração CLI segura e smoke autenticado permanecem gates de ambiente.
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

**Reconciliação de PRs atualizada (2026-11-17):** 17 PRs abertas; 6 fora de draft (#406, #407, #408, #411, #413, #435) e 11 drafts. O GitHub reporta 10 mergeable (#407, #408, #410, #411, #413, #415, #431, #433, #435, #436) e 7 não mergeable (#406, #412, #425, #427, #428, #429, #432). Oito ainda usam a base antiga `bca62b99` (#406, #412, #425, #427, #428, #429, #432, #433). #407/#411/#408/#413 foram sincronizadas com `d7683cc9` por merges normais; suas branches e evidências atuais estão resumidas abaixo. #416 já está mesclada e não conta como PR aberta. Mergeable não significa revisão ou aceite. A proteção da main segue sem leitura (403).

**Adendo pós-snapshot (2026-11-17):** foi aberto o PR #435 (`fix(REI-73): validate student enrollment session and origin`), HEAD `dc410b552019a4af40664b8084f32862976c7de1`, pronto para revisão e `MERGEABLE`. No HEAD da base `bca62b99`, `sala-audiencias-ata/inscrever.php` aceitava ID de sessão sem validar token ativo/inatividade nem origem confiável do POST. #435 aplica `ApiAccessGuard::requireAccess(['estudapainel'], true)` antes de consultar usuário ou serviço e preserva a exigência de estudante externo. O harness HTTP sintético e os 52 cenários do guard com dependências simuladas passaram; PHP lint passou; CI do HEAD `dc410b55` (run `44022278280`) concluiu SUCCESS. Revisão independente não encontrou blocker no diff. O PR ainda não tem aprovação formal nem foi mesclado; portanto a rota permanece aberta na base e a REI-73 continua incompleta. O teste não valida token no banco nem sessão/browser real.

**Próxima ação recomendada (reconciliada 2026-11-17):** #407/#411/#408/#413 deixaram de estar bloqueadas por conflito de base; checks CI atuais estão verdes e suas PRs são mergeable. #407/#411 continuam bloqueadas pela REI-73; #415 permanece draft por REI-73 e gates operacionais da migration. #408 não tem relação bloqueadora, mas ainda requer revisão independente/formal e validação do aceite. #413 aguarda confirmação operacional da conexão CLI/migration no host correto, smoke e revisão/política de merge. #406 ainda precisa sincronização. #416 foi mesclada sem deploy. A proteção da main retorna 403; nenhum merge adicional deve ser inferido apenas de `mergeable=true`.

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

- **REI-88 / PR #416:** no worktree limpo e no HEAD `098cce0f3f22c18ea97e4b077b68f8a47d4adf72`, o runner `run-pauta-transaction-mysql-isolated.ps1` terminou com exit 0 em MySQL 8.4/InnoDB descartável; cinco cenários e cleanup passaram. A PR foi mesclada em `main` no commit `d7683cc9b097dacb7c3bd00add3835cd4737cda1`; isso adiciona cobertura de teste, sem deploy e sem fechar REI-88. A revisão de arquitetura identificou que a criação de Carta pode elevar sigilo sem `consultaprocessosedit` nem autorização do processo específico. A regra depende da autorização por processo da REI-69; a issue permanece aberta.
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

### Delta de rastreamento estático — lookups de assunto/classe e PR #428 (2026-11-17)

- `public/api/assuntos/find-by-codigo.php` e `public/api/classes/find-by-codigo.php` exigem somente `$_SESSION['id_usuario']` não vazio; aceitam lookup por GET e também leem POST JSON quando o parâmetro da query falta. Consultam o catálogo por código normalizado e retornam apenas id/código/descrição (ou `null`), sem dado de processo. Os consumidores encontrados são `consulta-processos-edit.php` / `consulta-processos-edit.js` para assunto e `processos-edit.js` para classe.
- Nos dois lookups, o perfil/operação não é verificado localmente, o método não é restringido e a resposta 500 inclui `$e->getMessage()`. O último ponto já está coberto pela trilha REI-67; não abri issue duplicada. A leitura é de catálogo, e não demonstra exposição de dados de processo nem exploração.
- Reconciliação dos seis handlers previamente classificados como mutação apenas com identidade de sessão: cinco estão no diff atual da PR #428 (`assunto-link`, `assunto-sync`, `assunto-unlink`, `classe-sync`, `datajud-sync`) e `prioridades-remove` aparece na PR #408. Assim, eles não são seis achados sem candidato. A PR #428 acrescenta guard de operação e POST/origem confiável aos cinco, mas seu diff não chama `ProcessoSetorAuthorization` nem `canModifyProcess`/`canAccessProcess`; sua verificação estática de existência do processo não prova acesso do usuário ao registro.
- A REI-72 exige autorização de operação/registro. A busca atual no Notion não encontrou decisão específica sobre a fonte de acesso por registro para esses vínculos/sincronizações. A PR #428 permanece draft até a política ser definida e o acesso por processo ser implementado/testado ou demonstrado por controle equivalente. Esta nota não aplica automaticamente a decisão da REI-69 a outra política.
- Escopo e confiança: leitura estática do snapshot `bca62b990c2893937e723e23de50651d67736cd7` e do diff da PR #428 no HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`; alta confiança quanto aos guards mostrados nos arquivos/diff, sem request HTTP, banco, sessão real ou validação de proxy. Os dois lookups e o objeto de autorização da REI-72 seguem pendentes de reconciliação executável. Nenhuma issue, relação ou PR foi concluída/mesclada por esta inspeção.

### Reconciliação após merge da PR #416 — 2026-11-17

- A PR #416 foi mesclada normalmente em `main` no commit `d7683cc9b097dacb7c3bd00add3835cd4737cda1`; adiciona apenas testes MySQL isolados da transação da pauta. Não houve deploy. A REI-88 permanece aberta por autorização da Carta e dependências REI-69/REI-73.
- O merge alterou metadados de release e adicionou dois arquivos de teste. As branches derivadas da base `bca62b99` passaram a retornar `mergeable_state=dirty`; sincronizei #415, #435 e este PR #431 por merges normais, preservando entradas de changelog e release. Não usei force-push.
- #415 agora está no HEAD `62e56f54d56e0e5f8aef4be8f5b13963b32c5821`, baseado em `d7683cc9`. Retestei listagem isolada, mutações por setor, guard (27 cenários), transação de pauta SQLite e diff do merge; todos passaram. CI PHP lint `44030341991` SUCCESS. PR voltou a `mergeable=true`, mas permanece draft devido REI-73 e preparação da migration `ALGORITHM=COPY` no host/DB correto.
- #435 agora está no HEAD `e7b8fa7c4b4a6b5828c64f95dc6864cab8018602`, também baseado em `d7683cc9`. Passaram o harness HTTP sintético de fronteira, guard simulado (52 cenários), PHP lint e diff do merge; CI `44031088150` SUCCESS. A PR está mergeable e pronta para revisão; sessão/token real e navegador não foram testados.
- Snapshot posterior: 16 PRs permanecem abertas — 6 fora de draft (#406, #407, #408, #411, #413 e #435) e 10 drafts. As branches ainda não sincronizadas com `d7683cc9` precisam ser reavaliadas antes de qualquer merge; `MERGEABLE=false/dirty` foi observado para heads que ainda apontam a base antiga. A PR #431 inclui esta reconciliação após push.
- O Gateway identifica dois logins (`fokuscloud-dev` e `fokuscloud-vps`) no host `srv1053508.hstgr.cloud` e diretórios de hospedagem `/home/ios1vcrime` e `/home/ios1vcrime-novo`; as contas sem sudo não leem a configuração dos vhosts, e a política sudo negou a consulta. Não foi possível associar esses diretórios aos segredos `DEPLOY_HOST`, `DEV_PATH` ou `PROD_PATH`; nenhum dado/tabela foi lido e nenhuma migration foi executada.
- GitHub confirma que o repositório é privado e a leitura de proteção de branch/rulesets retorna 403 (“Upgrade to GitHub Pro or make this repository public”). Não foi possível verificar a política de aprovação obrigatória. Não há arquivo `CODEOWNERS` no checkout.

**Estado:** esta reconciliação não conclui a REI-73 e não remove nenhuma dependência. Ainda faltam fluxos autenticados, acesso por registro, concorrência/recuperação em ambiente isolado e evidência de sessão/proxy. As medições operacionais da migration REI-69 continuam sem host/DB alvo comprovados.

### Atualização posterior da fila de merges — 2026-11-17

- #407 (REI-70): HEAD `7cf7942632e20cb7be421e8ef6f5b5a7216f45d2`, base `d7683cc9`; merge normal, testes sintéticos/SQLite/MySQL reexecutados, CI `44033757808` SUCCESS, aberta/não draft/MERGEABLE. Bloqueio nativo REI-73 permanece.
- #411 (REI-68): HEAD `bd7763c1eed4673b573a9977664fca5e24c5bce7`, base `d7683cc9`; merge normal, testes de upload/origem reexecutados, CI `44033967517` SUCCESS, aberta/não draft/MERGEABLE. Bloqueio REI-73 e smoke autenticado permanecem.
- #408 (REI-75): HEAD `581ad068ccb1a11439a8d619311cdc2b0b79b297`, base `d7683cc9`; merge normal, cinco suítes direcionadas passaram, CI `44034157297` SUCCESS, aberta/não draft/MERGEABLE. Não é nativamente bloqueada por REI-73; revisão/aceite continuam necessários.
- #413 (REI-78): HEAD atual `3ad3bf9115adcd3226801db185fbfe0c06cf15d4`, base `d7683cc9`; merge normal, testes SQLite, MySQL isolado, idempotência e frontend passaram; CI PHP `44034596685` e interação `44034596667` SUCCESS; aberta/não draft/MERGEABLE. Sem deploy; origem segura de conexão CLI, migration no host correto e smoke ainda pendentes.
- #416 foi mesclada em `d7683cc9`. #415 segue draft apesar de `mergeable=true`; #435 está mergeable, mas autenticação DB/browser real não foi demonstrada. Nenhum desses fatos remove dependências Linear.
- Este adendo substitui os números e estados de PR dos snapshots anteriores neste arquivo; a auditoria estática do inventário continua baseada em `bca62b99` e permanece parcial.
### Reconciliação após atualização de main — 2026-11-17

- `origin/main` observado no GitHub: `3576675af7e98cf14a5c1592c8d51f4f255033b6`. A PR #431 foi sincronizada por merge normal com essa base e está OPEN, DRAFT, MERGEABLE/CLEAN. No HEAD documental `8d154e5d65f1ca266003533cd70adc0523c55b62`, os checks PHP lint `44038733784` e `44038737659` passaram; nenhuma suíte funcional foi executada para a atualização documental.
- PR #413 / REI-78: HEAD remoto `d1bae91b7be937bbf3259af10213f466b5b116a3`, base atual `3576675af7e98cf14a5c1592c8d51f4f255033b6`; sincronizada por merge normal, OPEN, fora de draft e MERGEABLE/CLEAN. Interação `44038671138`, PHP lint PR `44038671160` e push `44038664762` passaram. Testes sintéticos anteriores no pai `3ac051fb` passaram, mas não reproduzem concorrência InnoDB. Migration em DEV/PROD e smoke autenticado continuam pendentes; sem merge/deploy.
- PR #436 / REI-141 foi integrada em `00d059608be9e5e72ac69206633ad7ea9d7b03c4`; deploy PROD `44035072372` terminou SUCCESS. O smoke observado foi público/não autenticado: raiz HTTP 200 e asset JS HTTP 200 com conteúdo correspondente ao commit. Testes sintéticos de frontend/backend cobrem confirmação e recusa CCM e cenário não CCM; nenhum cadastro autenticado foi realizado. Linear atualmente mostra REI-141 como Concluído; não inferir sessão autenticada a partir do smoke público.
- Decisão funcional registrada para REI-69/85: sincronizar `partes.profissao` apenas em create/update de vínculo; delete não recalcula nem limpa o campo até existir regra de precedência. O vínculo autoritativo processo/unidade e backfill seguro ainda não foram definidos, portanto REI-69 permanece sem base para migração/conclusão.

A auditoria geral continua parcial: esta reconciliação não valida login real, autorização por objeto em todos os módulos, concorrência/recuperação completa nem controles de proxy/infraestrutura, e não remove bloqueios nativos.

### Inventário atual da fila de merge — 2026-11-17

Consulta ao GitHub após os merges #408 e #436 e a sincronização das branches com `main` `3576675af7e98cf14a5c1592c8d51f4f255033b6`: 15 PRs abertas, 5 fora de draft, 10 em draft; 7 retornaram `mergeable=true` e 8 `false`. `mergeable=true` indica apenas que o GitHub não observa conflito no estado consultado; não prova aceite, revisão, segurança operacional nem autorização para merge.

| PR | Estado/base e HEAD atual | CI observável | Bloqueio que ainda impede merge seguro |
|---|---|---|---|
| #406 / REI-130 | Aberta, fora de draft, mergeable; base `3576675`, HEAD `1075c312` | PHP Syntax `44037350467` SUCCESS; testes direcionados SQLite/Node e lint passaram no HEAD | Revisão formal e validação funcional autenticada/ambiente publicado pendentes |
| #407 / REI-70 | Aberta, fora de draft, mergeable; base `3576675`, HEAD `ab6da94f` | PHP Syntax `44038141424` SUCCESS; policy/guard/HTTP/SQLite passaram; runner MySQL do HEAD anterior não foi repetido | REI-73 continua dependência nativa; sessão autenticada, proxy e ambiente publicado não demonstrados |
| #411 / REI-68 | Aberta, fora de draft, mergeable; base `3576675`, HEAD `2144d321` | PHP Syntax `44037754532` SUCCESS; upload/origem, SQLite e regressões de prioridades/CCM passaram | REI-73 continua dependência nativa; token no banco, navegador autenticado e proxy/TLS pendentes |
| #413 / REI-78 | Aberta, fora de draft, mergeable; base `3576675`, HEAD `d1bae91b` | PHP Syntax `44038671160` e interação `44038671138` SUCCESS; testes de idempotência, CCM, sigilo e migration sintética passaram | Migration ainda não executada no host DEV/PROD correto; configuração DB CLI e smoke autenticado pendentes |
| #435 / REI-73 | Aberta, fora de draft, mergeable; base `3576675`, HEAD `91663745` | PHP Syntax `44039043323` SUCCESS; fluxo HTTP sintético, guard e lint passaram | REI-73 permanece incompleta; token contra DB/browser não demonstrados; teste trusted-origin segue bloqueado pelo guard REI-93 |
| #431 / esta matriz | Draft, aberta, mergeable; base `3576675`, HEAD `ad1e17cd` | PHP Syntax `44039180826` SUCCESS; nenhum teste funcional desta atualização documental | Manter draft enquanto a auditoria REI-73 e os gates descritos permanecerem incompletos |
| #433 / REI-67 | Draft, aberta, mergeable; base `d7683cc9` (anterior à main atual) | Checks reportados em HEAD anterior; não reexecutados nesta reconciliação | REI-73 e cobertura/revisão operacional pendentes; base antiga deve ser reavaliada |
| #410 / REI-93 | Draft, aberta, não mergeable; base `d7683cc9` | Checks anteriores verdes; sem execução nova | Base antiga e gate de sandbox/daemon ainda não comprovado; suíte positiva permanece suspensa |
| #415 / REI-69 | Draft, aberta, não mergeable; base `d7683cc9` | CI/testes verdes no HEAD anterior, sem execução nova | Base antiga; REI-73; migration `ALGORITHM=COPY` precisa de volume/espaço/janela e runner MySQL repetido |
| #429 / REI-77 | Draft, aberta, não mergeable; base `bca62b99` | Checks anteriores; sem execução nova | Base antiga, smoke autenticado durante polling/resposta atrasada e revisão formal pendentes |
| #428 / REI-72/76/153 | Draft, aberta, não mergeable; base `bca62b99` | Checks anteriores; sem execução nova | Base antiga, REI-73 e autorização por objeto ainda não demonstrada |
| #425 / REI-161 | Draft, aberta, não mergeable; base `bca62b99` | Checks anteriores; sem execução nova | Base antiga e smoke autenticado pendente; Chrome DevTools MCP não está disponível nesta sessão |
| #432 / REI-149 | Draft, aberta, não mergeable; base `bca62b99` | Checks do HEAD anterior; sem execução nova | Base antiga, DDL efetivo ainda não comprovado e revisão operacional pendente |
| #412 / REI-88 | Draft, aberta, não mergeable; base `bca62b99` | Checks anteriores; sem execução nova | Base antiga, dependências REI-69/73 e autorização para alterar sigilo pendentes |
| #427 / REI-125 | Draft, aberta, não mergeable; base `bca62b99` | Checks anteriores verdes no HEAD anterior; sem execução nova | Base antiga; mapeamento dos hosts alvo e runtimes CLI/FPM DEV/PROD pendente |

As cinco PRs de código fora de draft e #431 foram sincronizadas por merge normal com a base `3576675`; os respectivos HEADs e resultados estão registrados acima. Isso remove a divergência mecânica dessas branches, mas não libera merges. As outras nove PRs ainda usam bases anteriores; #433 é a única delas que o GitHub reportou mergeable apesar da base antiga. Nenhuma PR foi mesclada neste lote. A revisão das bases restantes deve respeitar as dependências: primeiro REI-73/REI-93 e validações de host/schema, sem tratar check verde como substituto de aceite.

### Apêndice D — reconciliação da fila de merges (2026-11-17, snapshot atual)

Consulta live ao GitHub em 2026-11-17; prevalece sobre os snapshots anteriores deste documento. Há **16 PRs abertas: 6 fora de draft e 10 em draft**. GitHub reporta **14 mergeable e 2 não mergeable**. A base atual de `main` é `3576675af7e98cf14a5c1592c8d51f4f255033b6`.

| PR | Estado e base atual | Bloqueio prioritário observado |
|---|---|---|
| #406 / REI-130 | Fora de draft, mergeable, base atual | Revisão formal e validação funcional autenticada/ambiente publicado. |
| #407 / REI-70 | Fora de draft, mergeable, base atual | Dependência REI-73; MySQL/InnoDB não repetido no HEAD integrado e sessão/ambiente real pendentes. |
| #411 / REI-68 | Fora de draft, mergeable, base atual | Dependência REI-73; sessão/token persistidos, navegador e proxy/TLS reais não demonstrados. |
| #413 / REI-78 | Fora de draft, mergeable, base atual | Migration no host/DB correto, smoke autenticado e revisão formal. |
| #435 / REI-73 | Fora de draft, mergeable, base atual | Critério central da REI-73 ainda incompleto; guard fail-closed REI-93 bloqueia o teste positivo. |
| #437 / REI-167 | Fora de draft, mergeable, base atual | Schema MySQL autoritativo e smoke autenticado do dashboard pendentes. |
| #410 / REI-93 | Draft, mergeable, base atual | Bloqueio operacional antecedente: provar isolamento daemon/imagens, DB/SMTP/rede e investigar o evento antes de reativar testes positivos. |
| #415 / REI-69 | Draft, mergeable, base atual | Dependência REI-73; medir impacto/janela de DDL `ALGORITHM=COPY` no host/DB alvo. |
| #425 / REI-161 | Draft, mergeable, base atual | Smoke autenticado pendente; Chrome DevTools MCP não está disponível nesta sessão. |
| #428 / REI-72/76/153 | Draft, mergeable, base atual, HEAD `c835dd4d6c6b9954ce4911d8282834392c19dd23` | CI PHP Syntax run 44041980394 SUCCESS; faltam REI-73, autorização por objeto e evidência de sessão/DataJud real. |
| #429 / REI-77 | Draft, mergeable, base atual | Smoke autenticado sob POST atrasado/polling, teclado/tecnologia assistiva e revisão formal. |
| #431 / matriz REI-73 | Draft, mergeable, base atual | Documento parcial; o inventário live precisa ser continuado até os gates de auditoria/autorização da REI-73. |
| #432 / REI-149 | Draft, mergeable, base atual | DDL/schema autoritativo da tabela não confirmado; runner isolado não comprova engine/índices do ambiente publicado. |
| #433 / REI-67 | Draft, mergeable, base antiga `bca62b99` | REI-73 e validação operacional pendentes; sincronizar base e revalidar checks antes de qualquer liberação. |
| #412 / REI-88 | Draft, **não mergeable**, base antiga `bca62b99` | Sincronizar base; dependências REI-69/73 e autorização explícita para alterar sigilo continuam pendentes. |
| #427 / REI-125 | Draft, **não mergeable**, base antiga `bca62b99` | Sincronizar base; mapear alvos DEV/PROD e medir CLI/PHP-FPM nos hosts corretos. |

**Ordem recomendada para desbloquear:** primeiro #410/REI-93, pois o guard impede os testes positivos de autenticação e origem usados pelas demais; em paralelo, completar o aceite da #435/REI-73 com a evidência permitida. Depois tratar bloqueios de host/schema/migration que são independentes (#413, #415, #432, #427), repetir testes em bases integradas e solicitar/recolher as revisões obrigatórias. Smoke autenticado das #425/#429 só é executável quando o Chrome DevTools MCP estiver conectado. Atualizar a base das #412/#427 é trabalho mecânico necessário, mas não remove seus gates de aceite.

**Limite desta contagem:** `mergeable` mede compatibilidade/conflicto informado pelo GitHub, não aprovação, checks obrigatórios, fim de draft ou aceite funcional. Nenhuma PR foi mesclada neste snapshot. O pedido para habilitar/reiniciar o Chrome DevTools MCP foi recebido, porém a ferramenta de conexão/controle não está disponível nesta sessão; não houve smoke autenticado.

### Apêndice E — resultado após resolver os conflitos mecânicos (2026-11-17)

As PRs #412 e #427 foram atualizadas com merge normal de `main` `3576675af7e98cf14a5c1592c8d51f4f255033b6`. Os conflitos eram somente em `VERSION`, `CHANGELOG.md` e `docs/90-releases/_draft_minor.md`; preservada a versão 1.87.0180 da main e reunidas as entradas de release de cada branch. Commits publicados sem force:

- #412 / REI-88: `9c23f6d1c8c4b05f4b1f5ef017169beb8b2d1e42`; PHP Syntax run 44043616750 está em fila.
- #427 / REI-125: `aea4fed164ead53b7e6a4810cbe7fa08455f529d`; PHP Syntax run 44043615457 está em fila. Composer não está instalado localmente; validação Composer deste SHA depende do CI.

No snapshot live após esses pushes permanecem **16 PRs abertas, 6 fora de draft e 10 em draft; todas as 16 reportadas mergeable pelo GitHub**. #412 e #427 deixaram de ser bloqueadas por conflito. #433 ainda usa a base anterior `bca62b99`, mas o GitHub reporta mergeable; sincronização preventiva pode ser feita depois dos bloqueios de aceite. A base atual das outras 15 PRs é `3576675af7e98cf14a5c1592c8d51f4f255033b6`.

A prioridade seguinte é REI-93 (#410), cuja contenção continua impedindo testes positivos até prova de isolamento do runner e investigação do evento; em paralelo, completar REI-73 (#435). Smoke autenticado das #425/#429 continua indisponível sem Chrome DevTools MCP. Após esses bloqueios, recolher gates específicos de host/schema/migration, repetir CI nos SHA integrados e obter revisões formais. Nenhuma PR foi mesclada; GitHub mergeable não satisfaz esses critérios.

### Apêndice F — checks, revisão e inspeção de runtime (2026-11-17)

- Os checks PHP Syntax observados nos HEADs live de todas as 16 PRs terminaram SUCCESS; #413 também tem check de interação SUCCESS. No HEAD integrado de #427, o job `php-lint` completou SUCCESS incluindo os passos Composer: validar manifesto/lock, `check-platform-reqs` e dry-run sem gravar dependências. No HEAD #412, o PHP Syntax também terminou SUCCESS. Resultados são checks de CI; não demonstram smoke autenticado nem aceite operacional.
- As seis PRs fora de draft (#406, #407, #411, #413, #435 e #437) não têm review APPROVED observada. #413 tem apenas reviews COMMENTED; branches protection da `main` não puderam ser lidas pela integração GitHub (HTTP 403), então a política obrigatória de aprovação permanece não confirmada.
- Inspeção Gateway read-only, previamente autorizada, nos dois registros disponíveis encontrou o mesmo hostname Linux subjacente; CLI PHP 8.4.22, binários FPM de 7.1 a 8.5, configurações consultadas para FPM 8.0–8.3 e unidades 8.0–8.3 ativas; PDO mysql/sqlite nessas configurações. Os diretórios exatos `~/domains/ios1vcrime.com.br` e `~/domains/dev.ios1vcrime.com.br` não foram encontrados sob esses usuários.
- Essa coincidência não comprova que algum registro Gateway corresponde ao `DEPLOY_HOST` dos workflows nem que algum pool FPM listado atende DEV/PROD. Assim, REI-125 permanece sem mapeamento de target/runtime. Nenhum dado de aplicação, arquivo de configuração sensível, deploy ou servidor foi alterado.

**Estado após reconciliação:** o conflito mecânico que impedia #412/#427 foi resolvido; nenhuma PR está bloqueada por conflito reportado no snapshot. As prioridades de trabalho permanecem REI-93 e REI-73, seguidas por validações autenticadas e host/schema específicos. Nenhuma revisão formal APPROVED foi observada nas PRs prontas e a regra de proteção não pôde ser confirmada.

### Apêndice G — reconciliação live depois da PR #435 (2026-11-17, 08:52 UTC)

Esta seção substitui os totais e estados dos Apêndices D–F. Fonte: consulta live ao GitHub/Linear; o código e as branches não foram inferidos a partir dos snapshots anteriores.

- `main` está em `9954d124050c6b5774b5f247675053ba30d6aeb7`, merge da PR #435. A #435 não está mais aberta.
- O autor fechou a antiga #415 às 08:34 UTC e registrou que foi substituída pela draft #438, baseada na `main` atual. #415 não é mais candidata de merge; não reabrir nem usar sua branch antiga.
- Há 15 PRs abertas: 5 fora de draft (#406, #407, #411, #413, #437) e 10 em draft. Após a sincronização normal de #412 e #431, o GitHub reporta as 15 como mergeable; isso remove conflitos, mas não aprova nem satisfaz gates de aceite.
- HEADs sincronizados com `9954d124`: #410 `683949623f8fff96f0e9252728eb1aad5cd10319`; #412 `1434a4aff6d00f9e6451185ca455f92c4cd6e3a2`; #413 `fe666b14bfcce7e543f5fa64783c02149de14acf`; #427 `7411fa21c5134803dad1d7b08b54995529a6e576`; #428 `ba99bce7962c0d5dfa9e1c256cd1857225da1042`; #429 `dd0b986d16eef757b56fcfce5a702b05ca8cddb8`; #432 `98b9fe911b970c7e41aef807149abb2290e66b93`; #437 `d48a198334c05f5c5f07de6d21192892ab4b9cee`; #438 `c4d4a4af082eec6ed750d1826a22111e6a383fe4`. #431 recebe esta reconciliação nesta branch.
- #406, #407, #411 e #425 continuam com base registrada em `3576675af7e98cf14a5c1592c8d51f4f255033b6`; #433 usa `bca62b990c2893937e723e23de50651d67736cd7`. Todos seguem mergeable sem conflito segundo GitHub, mas seus checks não validam necessariamente o HEAD da `main` atual.
- CI exato confirmado: #410 PHP Syntax `44047196688` SUCCESS; #412 PHP Syntax `44050246074` SUCCESS; #413 PHP Syntax `44047896436` e interação `44047896426` SUCCESS; #427 PHP Syntax `44049658424` SUCCESS; #428 `44049794224` SUCCESS; #429 `44049905303` SUCCESS; #432 `44049472831` SUCCESS; #437 `44049179989` SUCCESS; #438 `44047953205` SUCCESS. #431 PHP Syntax `44050794768` estava em andamento às 08:53 UTC. Os checks representam apenas seus workflows, não aprovação ou validação autenticada.
- As cinco PRs fora de draft não têm review `APPROVED` observada. #413 tem apenas reviews `COMMENTED`; nas outras quatro não há review formal. A leitura de branch protection/rulesets continua indisponível (403); aprovação obrigatória não pode ser confirmada.

#### Bloqueios que permanecem

- #410 / REI-93: PR segue draft. Não reativar o runner positivo até prova independente de daemon/imagens, isolamento MySQL/SMTP/rede e limpeza. A investigação de 15/11 continua inconclusiva; logs Security exigem elevação e faltam dados para correlação.
- #438 / REI-69: decisão confirmada é setor responsável explícito, sem backfill; processo nulo/inativo nega mutação. Isso satisfaz fail-closed, mas não cria writer para atribuir processos novos. Continuam REI-73, medição de volume/espaço e janela da migration `ALGORITHM=COPY, LOCK=SHARED`, executada no deploy de produção; manter draft.
- #413 / REI-78: testes MySQL/InnoDB isolados, SQLite, interação e CI passaram no HEAD atualizado; faltam revisão formal, migration planejada para o host/DB correto e smoke autenticado.
- #406, #407, #411 e #437: continuam fora de draft, mas sem aprovação formal observável. #407/#411 também dependem de REI-73; #406 requer smoke autenticado; #437 requer schema MySQL autoritativo e smoke dashboard.
- #425/#429: smoke autenticado continua pendente. A opção autorizada de habilitar/reiniciar Chrome DevTools foi solicitada, mas nenhum MCP de navegador aparece nas ferramentas desta sessão; não houve login nem dado real.
- #427: CI valida PHP/dependências, mas o host de `DEPLOY_HOST` e o pool FPM do site continuam não associados aos registros Gateway observados.
- #428: faltam autorização por objeto e evidência autenticada/upstream DataJud; #432 requer schema autoritativo no ambiente; #412 é uma fixture de teste e não satisfaz, sozinha, autorização para alterar sigilo; #433 permanece draft.

Nenhuma PR foi mesclada ou implantada neste ciclo de reconciliação. A PR #435 já estava mesclada antes; não a contabilizar como merge deste trabalho. `mergeable=true` significa apenas ausência de conflito reportado pelo GitHub.

### Reconciliação live da REI-70 — 2026-11-17

O resumo acima e os detalhes anteriores da REI-70 são snapshots históricos, não o estado atual. A PR #407 está agora na base `9954d124050c6b5774b5f247675053ba30d6aeb7`, HEAD `18be5602d41117f2438df9405f39d3c2711d5682`, aberta, fora de draft e reportada como mergeable. O CI PHP Syntax do SHA exato passou no run [44055062796](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44055062796). A revisão Codex desse mesmo SHA não encontrou problemas maiores. Os testes de autorização, SQLite/HTTP e MySQL/InnoDB descritos no corpo da PR foram executados sobre o código sincronizado antes do commit final metadata-only; não são alegados como reexecução byte-a-byte no HEAD atual.

REI-70 segue bloqueada pela REI-73. Não foi observada aprovação formal; falta smoke autenticado em ambiente autorizado. Não houve merge nem deploy. Mergeability e CI sintático não demonstram aceite integral.

### Reconciliação live após main `e06a3481` — 2026-11-17 10:07 UTC

Esta seção substitui os contadores e o estado de branches anteriores. PR #413 foi integrada por merge à main `e06a3481aeacee841f4df94d5b5409a874f76357`. Consulta live após a sincronização da #438 encontrou 14 PRs abertas: 3 fora de draft e 11 em draft. GitHub reporta #438 mergeable, #428 unknown e as outras 12 conflitantes com main; mergeability informa somente compatibilidade mecânica.

| PR | Estado live observado | Evidência ou gate atual |
|---|---|---|
| #438 / REI-69 | Draft, mergeable; HEAD `5101d770d37d5f14f0abbb971b4fc66ff64d2172`, base e06a3481 | Fluxo ADMIN auditado implementado; lint/SQLite/JS/MySQL descartável e CI PHP Syntax run [44061238564](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44061238564) passaram no HEAD. Branch sincronizada com main. REI-73 e planejamento operacional da migration `ALGORITHM=COPY, LOCK=SHARED` continuam bloqueando merge/deploy. Sem navegador, autenticação real ou DDL real. |
| #431 / REI-73 | Draft, conflitante; HEAD `ecc2b914`, base antiga 9954d124 | Este branch de matriz está sendo sincronizado com e06a3481; cobertura integral ainda não demonstrada. |
| #413 / REI-78 | Integrada antes deste snapshot | Não aparece mais na fila de PRs abertas; a aceitação e o deploy devem ser reconciliados pela issue e SHA publicados, sem inferir a partir da integração apenas. |
| #407 / REI-70, #411 / REI-68, #406 / REI-130 | Fora de draft, conflitantes | Checks anteriores não validam automaticamente novos merges de main; reviews formais e/ou smoke autenticado permanecem gates conforme cada issue. |
| #410 / REI-93, #412 / REI-88, #425 / REI-161, #427 / REI-125, #429 / REI-77, #432 / REI-149, #433 / REI-67, #437 / REI-167 | Draft, conflitantes | Sincronizar bases e revalidar individualmente após resolver aceites/requisitos de sandbox, autorização, schema, ambiente ou smoke pertinentes. |
| #428 / REI-72/76/153 | Draft, mergeability desconhecida; base aponta para branch da REI-69 | Reconciliar dependência e base; não tratar UNKNOWN como mergeable. |

O fluxo de atribuição explícita da REI-69 está no candidato #438, não em main; portanto os bloqueios nativos da REI-73 continuam corretos. Não atualizar a issue bloqueadora apenas porque os testes sintéticos e CI passaram: ainda há várias superfícies de API, autorização por objeto, dados reais protegidos e fluxos autenticados sem evidência suficiente. Nenhuma PR foi mesclada ou implantada durante esta reconciliação.

### Correção live posterior — 2026-11-17 10:10 UTC

A sincronização desta PR foi concluída após o snapshot de 10:07. Naquele momento, o HEAD era `143f95be3b0116b1cfd569ae56b9dfee21d70f73`, base `e06a3481aeacee841f4df94d5b5409a874f76357`; o CI PHP Syntax SUCCESS foi o run [44062029787](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44062029787). Depois, a matriz foi atualizada no HEAD `636ae99e2a6a6a2bbe51051da382d83cb682e951`, com CI PHP Syntax SUCCESS no [run 44062629470](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44062629470). Em ambas as consultas a PR estava draft e mergeable. A #438 também está mergeable em `5101d770`; #413 está integrada em main.

Nova consulta confirma 14 PRs abertas: 3 fora de draft, 11 draft, 2 clean/mergeable (#431 e #438) e 12 não mergeable/dirty, incluindo #428 (base aponta à branch da #438). O snapshot 10:07 acima foi preservado como histórico e não descreve o estado posterior. A auditoria da REI-73 segue incompleta e sem aprovação formal.

### Reconciliação live após main `269d5880` — 2026-11-19 21:28 UTC

Consulta feita contra `main` `269d5880ea6d9243cd3755ce169dfae8e8ad2dd4`. O estado abaixo é snapshot desta consulta; mergeability e checks de sintaxe não equivalem a aceite integral nem aprovação.

| Item | Estado observado | Evidência atual e limite |
|---|---|---|
| REI-73 | Em revisão; sem dependências de entrada; bloqueia REI-66/67/68/69/70/72/88 | A matriz e o inventário continuam parciais; faltam cobertura completa por rota/operação/perfil, autorização por objeto e validações autenticadas/UI/infraestrutura. |
| #447 / REI-90/100/104/152/156 | Aberta, draft, mergeable; HEAD `8c3a81dd8dabc1ee6d86b34f3edca6cda7272c00`; base `269d5880` | Dois checks PHP Syntax no HEAD passaram. Runners locais relatados na PR cobrem MySQL/InnoDB descartável para REI-90/100/104/152 e fluxo HTTP/Node para REI-156; gates externos de REI-152 e REI-156 permanecem. Cobertura de REI-133 também foi acrescentada, embora não faça parte do título original da PR. |
| #438 / REI-69 | Aberta, draft, mergeable; HEAD `684d45771476650800a12889f3fa6d942dc2b7c9`; base `269d5880` | Dois checks PHP Syntax passaram. A PR relata testes MySQL/SQLite sintéticos; permanecem REI-73 e o planejamento/medição da migration `ALGORITHM=COPY, LOCK=SHARED` no ambiente apropriado. |
| REI-93 / REI-133 | REI-93 em andamento e relacionada como bloqueadora de REI-133, que está em revisão | A recusa fail-closed e a suíte MySQL/Mailpit descartável passaram na branch #447. A ocorrência histórica que motivou REI-93 segue sem confirmação de conexão/escrita; testes atuais não esclarecem o evento anterior. |
| #413 / REI-78 e #435 | Mescladas em 17/11/2026 | O merge é histórico e não satisfaz automaticamente gates ou aceites remanescentes nas issues. |

Esta reconciliação atualiza estado e evidência, mas não completa o inventário nem a auditoria funcional da REI-73. Nenhum banco real, dado judicial, browser autenticado ou ambiente de produção foi usado nesta etapa; não houve deploy.

### Continuação da auditoria — REI-99 — 2026-11-19 22:01 UTC

A REI-99 registrou que os handlers de download de atas exigiam apenas ID de sessão e perfil, sem validar token, expiração ou conta ativa. A PR #448 adiciona `Security::isAuthenticated()` a `download.php` e `download-interno.php` antes da consulta de perfil/serviço, preservando os controles existentes de perfil, inscrição e sigilo. HEAD `2304eb2a8178ae3976d693fd0ddb4b2b6ac60306`; draft, mergeable e CI PHP Syntax passou no run [44624419678](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44624419678).

A fixture sintética usa a implementação real de `config/security.php` contra SQLite temporário e cobre token válido, expirado, divergente/revogado e conta inativa; perfil, `AccessEvaluator` e serviço são simulados. Isso verifica a consulta de autenticação e o bloqueio nos handlers, mas não paridade de MySQL, browser ou política real de download. A chave `estudaata` segue fora da correção: a matriz a registra como não autorizada para estudante, e a intenção de tornar o fluxo público ainda não foi confirmada. REI-99 continua em andamento até revisão/aceite; sem merge ou deploy.

Este achado não remove o bloqueio amplo da REI-73 nem conclui a revisão completa de rotas, perfis e infraestrutura.

### Reconciliação live após main `39d52725` — 2026-11-20 00:30 UTC

Esta atualização registra somente fatos conferidos nos estados live do Linear/GitHub, descrições atuais das PRs/issues e verificações locais identificadas abaixo. Relatos de testes nas descrições são atribuídos aos respectivos artefatos; não significam que todos foram reexecutados nesta atualização. A PR #431 foi sincronizada com `origin/main` `39d52725` por merge normal; os únicos conflitos foram metadados de release. A matriz continua parcial e não representa auditoria integral.

| Item | Estado live e evidência registrada | Pendências e limites |
|---|---|---|
| REI-127 / #450 | Linear `Concluído`; #450 `MERGED` em `07734d6b`. `python -B tests/cache/toast-conditional.test.py` passou nesta atualização (`ETag precedence, weak/list/wildcard and date fallback`); CI php-lint 2/2 PASS. A implementação `fe3a4503` é ancestral do SHA `269d5880`, cujo deploy PROD passou no run [44109657380](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44109657380). | O deploy posterior de `07734d6b` falhou por indisponibilidade SSH; esse merge acrescentou a regressão/cobertura e não substitui nem invalida o deploy anterior da implementação. Não inferir que o SHA `07734d6b` foi implantado. |
| REI-171 / #451 | #451 `MERGED`; `main` em `39d52725`. A descrição registra runner de autenticação isolado e CI php-lint PASS. | Deploy PROD do SHA `39d52725` falhou no passo Setup SSH/keyscan (run [44642767086](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44642767086)); as etapas de sync e post-deploy foram ignoradas. Linear permanece `Em andamento`; produção e smoke pós-deploy não confirmados. |
| REI-67 / #433 | Aberta, draft, mergeable; base `39d52725`, HEAD `bc7ce8ab`. Teste sintético `tests/api-security/api-error-redaction.test.py` PASS nesta execução; lint local em 157 arquivos PHP PASS. CI php-lint 2/2 PASS (runs [44643885118](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643885118) e [44643889325](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643889325)). | Revisão formal, aceite integrado e dependência desta auditoria continuam pendentes; teste sintético/lint não comprovam todas as rotas autenticadas ou produção. |
| REI-89 / #441 | Aberta, draft, mergeable; base `39d52725`, HEAD `d87b177a`. A issue/PR registra testes SQLite em memória e MySQL 8.4.7/InnoDB com falhas injetadas nas cinco escritas, rollback/savepoints e fluxos de sucesso; CI php-lint 2/2 PASS (runs [44643913998](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643913998) e [44643917400](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643917400)). | A execução desses runners foi reportada pela issue/PR e não repetida nesta atualização. Permanecem revisão formal, confirmação de schema/engine no DEV e smoke no DEV. |
| REI-99 / #448 | Aberta, draft, mergeable; base `39d52725`, HEAD `e1410407`. A issue registra fixture HTTP local com SQLite e classes reais de sessão/ACL/serviço: expirado/ocioso/revogado/inativo (401), estudante e perfil interno válidos (200), CONSULTOR (403), sem inscrição ou processo sigiloso (400 sem PDF); CI php-lint 2/2 PASS (runs [44643560974](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643560974) e [44643567580](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44643567580)). | Fixture sintética não prova sessão em MySQL/browser/produção. Revisão formal e gates de ambiente permanecem. |
| REI-100 / #447 | Aberta, draft, conflitante; base antiga `269d5880`, HEAD `06221f51`. A issue/PR registra runner MySQL descartável para recuperação parcial/idempotência e testes SQLite/Node; php-lint PASS no run [44644560127](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44644560127). | Não sincronizada com `main` `39d52725`; conflito e validação após sincronização pendentes. Os testes citados são reportados pela issue/PR, não foram reexecutados nesta atualização. |
| REI-169 / #445 | Aberta, draft, mergeable; base `39d52725`, HEAD `c8643a6d`. A issue/PR registra seis cenários específicos PASS (duas filas, ambas ordens de antiguidade, empate e ausência); CI php-lint 2/2 PASS (runs [44644564381](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44644564381) e [44644574107](https://github.com/jorgewreis/website-tjba-ios1vcrime-2026/actions/runs/44644574107)). | Smoke pós-deploy autenticado ainda não ocorreu; revisão formal e confirmação em ambiente publicado pendentes. Testes locais não foram reexecutados nesta atualização. |

Esta reconciliação não amplia o escopo dos itens nem encerra a REI-73. Continuam sem demonstração o inventário integral por rota/operação/perfil, autorização por objeto, fluxos autenticados/UI e infraestrutura operacional para as áreas não cobertas. Estados `mergeable` e checks de sintaxe não substituem revisão, deploy ou aceite. Nenhuma auditoria integral é declarada.
