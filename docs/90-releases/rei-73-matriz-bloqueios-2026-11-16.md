# REI-73 — matriz incremental de bloqueios e evidências

**Snapshot:** 2026-11-16 (America/Bahia)  
**Base inspecionada:** `origin/main` em `bca62b990c2893937e723e23de50651d67736cd7` (inclui merge de #430)
**Worktree desta documentação:** `codex/rei73-blocker-matrix-20261117`  
**Escopo:** oito dependências declaradas de REI-73: REI-66 a REI-72 e REI-88. Esta matriz é uma reconciliação incremental; não certifica a auditoria completa.

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
| REI-67 | Sem branch/PR atribuível seguro | `tests/api-security/internal-errors.test.py` cobre três endpoints simulados | Inventário/correção integral e testes reais/autenticados ausentes; candidato no checkout sujo não é atribuível |
| REI-68 | PR #411, HEAD `64a82197e040b3db77264c273b2ab238081206f6` | Teste endpoint 7 cenários; guard POST 18 cenários; lint reportados | UI autenticada, check no SHA e revisão independente pendentes |
| REI-69 | PR #415, HEAD `74a418bb36a0bc643766d956312f0210a023b185` | Run `43985871543` (sintaxe PHP); testes MySQL/SQLite sintéticos reportados | Migração não aplicada; impacto/volume e janela autorizada pendentes; browser/sessão real ausentes |
| REI-70 | PR #407, HEAD `76a6e75f21b4c1415e72634c2e14366ee5adb77e` | Bateria sintética extensa reportada, inclusive MySQL descartável | Sem workflow/check formal ou review; sessão/browser/proxy não demonstrados |
| REI-71 | Sem PR aberto identificado | Testes sintéticos reportados em `b8cee7c…`; ancestral do SHA publicado `f5e657fc…`, run `43839505824` | Sessões e perfis autenticados reais não cobertos; issue concluída não encerra auditoria global |
| REI-72 | PR #428 draft, HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, base `bca62b99` | Após merge normal: testes reais SQLite e HTTP sintético afetados passaram; checks exatos `44001675569` e `44001683252` passaram | Concorrência MySQL, browser/sessão e política de autorização por registro seguem pendentes |
| REI-88 | PR #412 HEAD `dae7bf98b1764042a7ca53004d38ff5e5549ba2a`; #416 HEAD `a21ddecf7030890dad62bd4df7a9b857a9afabed` | Testes MySQL sintéticos reportados; #416 run `43940823400` lint | Autorização de mudança de sigilo, transações de todos os callers e suíte MySQL no CI pendentes; #413 compartilha fixtures/atomicidade |

## Critérios e evidências por issue

### REI-66 — cadastro de estudante e conta existente

**Aceite acompanhado:** cadastro público não altera conta existente sem fluxo autorizado/prova; preserva dados/perfis; serializa verificação e atribuição concorrente; testes sintéticos para conta sem perfil, estudante, interno, nova conta e concorrência sem dados pessoais reais.

- **Validado no SHA exato (parcial, evidência reportada):** `tests/api-security/student-existing-account.test.py` e `tests/api-security/admin-student-cpf-lock.test.py` passaram em `cfb38fbf…`; lint também reportado. Correção `d00eaf5e…` está na ancestralidade de `main`; deploy reportado no run `43783469473` para `086db6b…`.
- **Somente inspeção estática/não verificado:** configuração efetiva do document root e coexistência do endpoint legado `api/users.php` com outros writers não foram comprovadas no runtime.
- **Limitação:** testes não reproduzidos nesta matriz; deploy não prova a versão/configuração de todos os servidores nem cobertura de cada writer.
- **Próximo gate:** comprovar os caminhos de escrita e a versão servida na configuração publicada.

### REI-67 — respostas de erro interno nas APIs

**Aceite acompanhado:** inventariar/corrigir respostas internas em APIs públicas e autenticadas preservando contratos legítimos de validação; expor mensagem genérica ao cliente e diagnóstico restrito/sanitizado sem PII/credenciais; testar exceções nos dois contextos.

- **Somente inspeção estática:** revisão arquitetural encontrou vazamentos por `getMessage()`, logs indiretos e operações de aquisição/`require` anteriores ao handler. Isto não constitui enumeração completa.
- **Cobertura sintética limitada:** `tests/api-security/internal-errors.test.py` cobre três endpoints e simula autenticação/persistência; não exercita guards reais nem todas as rotas.
- **Não verificado:** não há branch/PR limpo atribuível. Alterações amplas no checkout principal sujo não podem ser atribuídas à issue nem servem de prova.
- **Próximo gate prioritário de REI-73:** abrir candidato limpo na base atual, inventariar handlers/rotas executáveis e cobrir vazamentos, preservando respostas de validação públicas.

### REI-68 — upload de ata e proteção de escrita

**Aceite acompanhado:** proteção de sessão/operação compatível com multipart e perfil atual; POST válido; rejeitar token/origem ausente ou inválido e sessão inválida sem mutar banco/arquivo; fluxo legítimo de upload via UI em browser isolado.

- **Candidato:** PR #411, base `b8cee7c…`, HEAD `64a82197e040b3db77264c273b2ab238081206f6`, draft. Comentários registram ajuste para `ApiAccessGuard::requireAccess(['salaata'], true)`, teste endpoint com 7 cenários, guard POST com 18 e lint.
- **Validado no SHA exato:** não foi encontrado workflow/check associado ao HEAD; resultados acima são relatos do candidato, não validação independente desta revisão.
- **Bloqueado por ambiente/não verificado:** upload autenticado via browser pendente; suites protegidas dependem de sandbox autorizado. Revisão independente final pendente.
- **Próximo gate:** executar UI autenticada em fixture isolada e obter check/revisão sobre o HEAD final.

### REI-69 — partes do processo e autorização por unidade responsável

**Aceite acompanhado:** inventariar endpoints/consumidores; exigir sessão, permissão de operação e autorização do registro para leitura/escrita; proteger método/origem em mutações; rejeições sem escrita e permitir caso autorizado; revisar endpoints relacionados.

- **Candidato:** PR #415, base `3517a74…`, HEAD `74a418bb36a0bc643766d956312f0210a023b185`, draft. Política decidida: permissão da operação mais unidade responsável, via `processos.id_setor_responsavel → setores.id_setor`; leitura sigilosa não concede escrita. Ausência de setor legado falha fechada; sem backfill automático.
- **Validado no SHA exato (parcial):** run `43985871543` concluiu sucesso de sintaxe PHP. PR/comentário reporta runner sintético SQLite e MySQL 8.4/InnoDB, positivos/negativos de leitura/mutação e migração idempotente/rollback; não reproduzido aqui.
- **Somente inspeção estática/bloqueado por ambiente:** migração aditiva nullable `CHAR(30)` e FK usa `ALGORITHM=COPY, LOCK=SHARED`; não foi executada em DEV/PROD. Medição de volume/espaço e janela de mudança necessárias. Sessão/browser real não demonstrados.
- **Próximo gate:** revisar migração contra schema real e preparar medição/plano antes de aplicar; manter legado sem responsável negado até atribuição autorizada.

### REI-70 — instituições públicas e política CONSULTOR

**Aceite acompanhado:** inventário de endpoints; sessão/operação/registro e proteção de escrita; pedidos anônimos/não autorizados sem mutação; CONSULTOR externo limitado à instituição/setor/vínculos ativos, preservando concessões internas de perfil misto.

- **Candidato:** PR #407, base `b8cee7c…`, HEAD `76a6e75f21b4c1415e72634c2e14366ee5adb77e`, aberto e não draft.
- **Evidência reportada:** policy8, guard52, anônimo12; SQLite com 40 recusas sem escrita e 8 escritas autorizadas; HTTP sintético; MySQL 8.4 descartável com 20 leituras, 40 recusas, 8 escritas e limpeza. Review independente reportado para 12 endpoints.
- **Não verificado:** nenhum workflow/check ou review formal encontrado para o SHA. Fixture não tinha constraints/FKs; não demonstra schema real, sessão de produção, proxy ou autorização de leitura em ambiente real.
- **Próximo gate:** checks reproduzíveis no CI contra schema fiel, fluxo autenticado isolado autorizado e review formal.

### REI-71 — monitoramento e consultas internas

**Aceite acompanhado:** endpoints/consumidores; autorização `all_internal` para Cartório/Gabinete/Audiências; método/origem confiáveis em escritas; testes negativos/positivos sem escrita em rejeições.

- **Validado no SHA exato (escopo sintético reportado):** `tests/monitoramento/guard-simulated.test.php` (90 casos), `authorized-dispatch.test.php` (5 endpoints), `json-dispatch.test.py`, `consulta-sigilo.test.php` e `consulta-reus-sem-escrita.test.php` passaram, reportadamente, na base `b8cee7c…`, descendente do SHA publicado `f5e657fc…` (run `43839505824`). Smoke anônimo 401 também reportado.
- **Bloqueado por ambiente/não verificado:** identidades/serviços sintéticos; login/sessão real e infraestrutura não comprovados. 401 anônimo não valida autorização positiva por perfil.
- **Limitação:** Linear está em Concluído, mas isso fecha apenas a issue individual conforme sua evidência registrada, não REI-73.
- **Próximo gate:** anexar fluxos autenticados isolados ou registrar indisponibilidade e limites no inventário global.

### REI-72 — vínculo de assuntos e sincronização DataJud

**Aceite acompanhado:** sessão válida, permissão por operação/registro, método + origem confiável/CSRF; revisar APIs relacionadas; rejeições sem mutação; validar proveniência dos assuntos do cliente.

- **Candidato atual:** PR #428 permanece draft. HEAD `e83aced08b63ae6b4d7b51d4d67552225922217a`, com merge normal da base atual `bca62b990c2893937e723e23de50651d67736cd7`; o conflito/defasagem com a base foi resolvido. #405 (`e15b65b…`) permanece sobreposto/antigo; #409 (`fdc1032…`) pertence à frente REI-76.
- **Validado no SHA exato (reproduzido após merge):** `php tests/datajud/mapper-sync-real.test.php`, `php tests/datajud/sync-atomico.test.php`, `php tests/datajud/sync-rollback-repositorios.test.php`, `php tests/processos/assunto-sync-payload.test.php`, `php tests/processos/assunto-sync-service-validation.test.php`, `python -B tests/processos/datajud-sync-objeto-http.test.py` e `php tests/guias/conclusao-atomicidade.test.php` passaram. O mapper, serviço e repositórios foram exercitados em SQLite; os dois endpoints foram exercitados via harness HTTP sintético.
- **CI no SHA exato:** runs `44001675569` e `44001683252` passaram para `e83aced08b63ae6b4d7b51d4d67552225922217a`.
- **Limitações não resolvidas:** a fixture HTTP usa dublês e não testa sessão autenticada; a suíte separada cobre mapper/serviços/repositórios reais em SQLite. Concorrência MySQL, browser/sessão real e DataJud vivo continuam sem evidência. A issue ainda requer resolver/documentar autorização por registro para link/unlink/read/sync. Sem merge ou deploy do PR.
- **Próximo gate:** fechar a política de escopo do registro e validar concorrência MySQL e sessão isolada. REI-73 continua bloqueada/incompleta até os gates individuais e a cobertura global serem demonstrados.
### REI-88 — preservação de sigilo e atomicidade em `ensureProcess`

**Aceite acompanhado:** não apagar sigilo sem mudança explicitamente autorizada; auditar callers; atomicidade de processo/classe/pauta; testes MySQL sintéticos para processo sigiloso, rejeições/falhas, processo novo e mudança autorizada.

- **Evidência na base:** mudança `ensureProcess`/transação de pauta presente na ancestralidade da base atual `bca62b99…` (`97da36…`, conforme PR #416); deploy reportado no run `43884547610`. Testes SQLite e cinco casos MySQL isolados reportados por #416.
- **Candidatos:** #412 (Carta, HEAD `dae7bf9…`) e #416 (Pauta, HEAD `a21ddec…`); #416 run `43940823400` passou lint. Ambos drafts. PR #413 (REI-78, HEAD `c17d051…`) compartilha fixtures/atomicidade; reconciliar para evitar versões divergentes.
- **Somente inspeção estática/lacunas:** callers relevantes: `SalaAudienciasPautaService`, `ReuManager`, `OficiosCartorioService`, `OficiosGabineteService` e callers de Guias. Falta comprovar ownership transacional de cada caminho, propagação/captura de exceções e rollback de efeitos anteriores. Testes Carta/Pauta não provam todos os callers.
- **Não verificado:** autorização para alteração explícita de sigilo e por processo não demonstrada em teste formal. PRs sem suíte MySQL ligada ao CI; sem merge/deploy dos PRs de teste.
- **Dependência:** REI-69 fornece vínculo processo→setor responsável. REI-88 não deve tratar leitura de sigiloso como autorização de escrita; alteração explícita deve seguir permissão de operação e acesso ao processo.
- **Próximo gate:** fechar matriz caller→writes→transação→propagação e testar os caminhos que escrevem antes da validação ou fora da transação.

## Reconciliação e limites de REI-73

1. Relações nativas de bloqueio em Linear: REI-66, REI-67, REI-68, REI-69, REI-70, REI-71, REI-72 e REI-88. Relações adicionais não foram promovidas a bloqueio sem evidência. PR #430 foi mesclado por Jorge; `origin/main` agora aponta para `bca62b990c2893937e723e23de50651d67736cd7`. Esta branch integrou essa base por merge normal. O HEAD atual do PR #428 ainda não é ancestral de `origin/main`.
2. PRs não formam correspondência um-para-um. REI-66 e REI-71 aparecem integradas/deployadas segundo evidência registrada; REI-67 carece de candidato atribuível; REI-72 tem candidatos sobrepostos; REI-88 compartilha trabalho com REI-78.
3. Documentos ligados à auditoria (inventário textual de APIs de 15/11 e auditoria WebApp de 15/11) declaram limites heurísticos/estáticos, usam baseline anterior e não cobrem integralmente rotas atuais, dependências, fronteiras de dados, sessões autenticadas, UI renderizada ou infraestrutura. Contagem textual não equivale a matriz de autorização validada.
4. Esta tarefa não executou testes, smoke, deploy, migration nem acesso a dados reais. Evidências acima vêm de comentários, PRs e runs indicados; revalidar se SHA mudou.
5. **REI-73 continua incompleta.** Faltam inventário executável de rotas/operações e controles, revisão de fluxos críticos/recuperação/concorrência, UI e autenticação isolada, reconciliação integral com issues e limitações por seção. Não mover REI-73 para concluída com esta matriz.

**Próxima ação recomendada:** começar por REI-67 em branch limpa na base atual `bca62b99…`: é lacuna transversal e não tem candidato atribuível. Em seguida, fechar gates de ambiente/review de #411, #415 e #407 e reconciliar #428/#412/#416/#413 antes de integrar trabalho compartilhado.
