# REI-73 — matriz complementar de operações e limites

## Base, método e legenda

Base remota conferida por `git ls-remote origin refs/heads/main`: `822e36cff960f31846beec1d4a00cba1ba5aaaff`. Documento novo em worktree isolada; não substitui matrizes históricas, CSV ou suas linhas/hashes. Complementa [evidências incrementais](rei-73-evidencias-incrementais-20261125.md) e [reconciliação de suspeitas](rei-73-suspeitas-consolidadas-20261125.md).

R = reprodução limitada ao laboratório citado; E = caminho observado estaticamente; H = hipótese condicionada; N = contrato/cobertura não estabelecidos. Uma hipótese histórica pode receber R posterior sem apagar seu registro anterior. PASS/FAIL pertencem aos oráculos e SHAs citados, não à aplicação inteira. Infraestrutura indisponível pode ser registrada como limite justificado; não se exige acesso produtivo nem dados reais.

Cobertura efetiva desta preparação: leitura integral dos cinco PHP legados; leitura dirigida de includes, Security/Bootstrap, documentação e deploy; comparação Git dos escopos abaixo; inspeção dos JSON/lifecycle de Cartas Recebidas, Pauta/delete e CEJUSC; comentários recentes REI-73. Pessoas, Feriados, Ofícios Gabinete e limpeza externa são relatos vinculados, não reexecução ou revisão integral de seus artefatos por este documento. Não houve teste, Docker, banco, HTTP ou produção nesta consolidação.

## Operações com evidência posterior às matrizes históricas

| Operação / identidade e controle | Classe e prova | Encaminhamento e limite / próxima prova |
|---|---|---|
| `cartorio-cartas-recebidas/concluir-subtarefa.php`: Cartório sintético; anônimo401 e CONSULTOR403 sem escrita | R, fonte822e36cf, run2aebd55f373a4c30a9e52eb0bab75daf: baseline/replay com um evento; trigger INSERT do evento causou500 após avanço do pai/filhos; retry200 não reparou evento. [Registro](https://linear.app/reis-developer/issue/REI-73#comment-227bde20-1c91-424c-81f0-36454d8a9e9c) | REI-104; exposição SQLSTATE relacionada à REI-67. Sete tabelas, sessões/schema sintéticos. Trigger identificado pela exceção pública: diagnóstico da base fixa, não regressão apropriada após sanitização. Sem login/concorrência/produção; não valida PR447. |
| `sala-audiencias-pauta/delete.php`: SERVIDOR-AUDIENCIAS sintético; anônimo401/CONSULTOR403 | H→R, fonte822e36cf, run261dddf7b1ff4d918c286304a9b44fea: falha BEFORE DELETE alocados deixou pai inativo e duas alocações; retry200/success=false manteve estado parcial. Controles e registro OTHER preservados. [Registro](https://linear.app/reis-developer/issue/REI-73#comment-5385009a-ef11-49d8-965f-4704961995be) | [REI-330](https://linear.app/reis-developer/issue/REI-330), REI-67 para erro exposto. Seis tabelas, sem login/concorrência/UI/schema real. Retry normal também retorna success=false, porém mantém estado final correto; não confundir os dois casos. |
| `cartorio-cejusc/create.php`: SERVIDOR-CARTORIO sintético; anônimo/CONSULTOR403, Security+POST confiável+AccessEvaluator reais | H→R para confirmação sequencial, fonte822e36cf, run52f52a000f9e4b82a93cca9c7d08093b: nome igual sem força409; nome informado divergente com mesma pessoa vinculada201, persistindo nome canônico duplicado. Force explícito201. [Registro](https://linear.app/reis-developer/issue/REI-73#comment-f064745f-5afc-435b-a02a-1d9a66787a1d) | Reconciliar [REI-123](https://linear.app/reis-developer/issue/REI-123) e candidata local `rei123-cejusc-concurrency-20261120`; não presumir unicidade absoluta. 14 tabelas; criações alteraram também processos/prioridades. Sem concorrência/produção; candidata não testada nesta rodada. |
| `pessoas/update.php`: controle válido e cinco negações relatados; perfis exatos não revalidados neste documento | H→R conforme [registro](https://linear.app/reis-developer/issue/REI-73#comment-09a76b8e-7177-4ee3-a469-9eaaab7fb61b), fonte822e36cf: ID ausente200/success=true sem escrita | [REI-331](https://linear.app/reis-developer/issue/REI-331), distinta de REI149/85. Recuperar perfis/casos do artefato antes de alegar cobertura por papel; relato HTTP/MySQL sintético, não produção. |
| Feriados: liberação de lock após commit; perfil não revalidado neste documento | H→R condicional conforme [registro](https://linear.app/reis-developer/issue/REI-73#comment-c281e91f-eb5c-4de9-9342-0b72ff8d643c), fonte822e36cf: falha injetada retornou500 e retry criou segunda linha | [REI-332](https://linear.app/reis-developer/issue/REI-332). REI135 histórica na lixeira, relação formal recusada. Não confundir com aprovação de política DIRETORA/CHEFIA ou ocorrência natural de falha. |
| `gabinete-oficios/delete.php`: máximo sequencial/exclusão; perfil não revalidado neste documento | H→R conforme [registro](https://linear.app/reis-developer/issue/REI-73#comment-7a25309d-c2ce-4943-bc97-a342fd9b1446), fonte822e36cf: MAX2, criação/commit3 em outra conexão, exclusão2 aceita | [REI-333](https://linear.app/reis-developer/issue/REI-333), distinta da REI89 Cartório. Intercalação HTTP/MySQL determinística, não duas requisições HTTP simultâneas ou frequência em produção. |
| Limpeza de usuários externos versus atualização de acesso/token; operação CLI | R relatada em [registro](https://linear.app/reis-developer/issue/REI-73#comment-f1fe4a5e-6d74-478f-bebc-8a0f6445198a), fontea868a6a7: dois processos/conexões/barreiras; token confirmado→cleanup→acesso removeu conta e atualização retornou sem exceção | REI-137. Controle com atualização anterior preservou conta. Não prova handler HTTP, sessão criada, lock simultâneo, frequência natural ou cron efetivo. |

As provas anteriores de REI131/status, vínculos Pauta, atas REI87/99, login externo e polling/REI328 permanecem na [consolidação incremental](rei-73-evidencias-incrementais-20261125.md). Esta tabela não reclassifica essas provas como executadas na base atual.

## Cinco entradas legadas: operação e perfil condicionados ao bootstrap

Todos os arquivos `api/*.php` abaixo usam `__DIR__.'/../../config/config.php'` na linha4. Na disposição versionada `<repo>/api`, o caminho resolve no diretório pai do repositório, não em `<repo>/config`. Sem instalação externa compatível, a inclusão falha antes dos guards. Isso foi identificado estaticamente, sem execução. Os riscos posteriores são condicionais; não se afirma bypass explorável ou exposição produtiva. [Registro Linear](https://linear.app/reis-developer/issue/REI-73#comment-8dcaac11-a6c6-4c51-a969-00d0450cb7c0).

| Arquivo / operação | Guard e identidade expressos | E/H/N, encaminhamento e próxima prova |
|---|---|---|
| `api/documents.php`: upload multipart e metadados | Autenticação12/token20; POST40;10MB51; MIME56. Sem gate expresso de perfil/objeto/origem | E: extensão do nome69, move71 antes de INSERT81; catch84–87 absorve falha e sucesso89. H: arquivo sem metadados se bootstrap/serviço alcançáveis. REI73 inventário; conciliar política de upload/retirada antes de qualquer ativação. REI68 é temática de atas, não duplicata estabelecida. |
| `api/guia.php`: create e listagem | Autenticação13/token21; createPOST38; demais ações caem em listagem48–56. Sem gate expresso de perfil/objeto/origem | E/N: delega a Guias; cadastro/listagem legados não equivalem às APIs canônicas. REI160 relacionada à integridade do serviço, não prova deste chamador. Próximo passo documental: decidir desativação/contrato, sem corrigir includes automaticamente. |
| `api/guia_pdf.php`: consulta por ID e geração de PDF | Autenticação12/token20, ID31–39. Sem gate expresso de método/perfil/objeto | E: grava storage65–94, inclusive caminho GET; Dompdf remoto habilitado75; retorna URL/storage80/94. H: acesso/efeitos dependem de bootstrap, gerador, schema e servidor. Sem prova de download acessível. Definir política e superfície antes de teste sintético com gerador controlado. |
| `api/notifications.php`: list e mark_read | Autenticação11/token19; SQL48/55 limita target_user_id à sessão; mark_readPOST42 | E: sem chamada de origem/CSRF e sem checar linhas afetadas antes de success50. N: contrato de ação inexistente/consumidor. Não presumir ausência de controle por objeto: predicado de usuário está presente. |
| `api/users.php`: lista e criação | Autenticação11/token19; list35 precede gateADMIN43; createPOST49. Perfil único da sessão | E/H: se executável, listagem até200IDs/nomes/CPFs sem gateADMIN; escrita sem origem expresso. `Security::getUserProfile`439 é legado e não avaliador composto. Matriz de bloqueios já cita coexistência não verificada. REI73/329 para delimitar superfície; não declarar vazamento real. |

### Chamadores, configuração e exposição

Busca textual dos cinco nomes/URLs no Git encontrou documentação/inventário, não consumidor direto de aplicação nos padrões pesquisados. Não exclui construção dinâmica, consumidores externos ou arquivos não rastreados. `README.md:347` e `docs/10-architecture/documento-integracoes-api.md:90` classificam essa árvore como não canônica; `docs/canon/testing-instructions.md:18` exclui seu uso como smoke oficial.

`BootstrapHelper.php:25` define a função de origem; incluir o helper não a executa automaticamente. Não foram observadas chamadas a essa função nos cinco handlers. O fluxo compartilhado Security/token foi lido, mas não executado.

`deploy-prod.yml:163–184` sincroniza raiz sem excluir `api/`. `.htaccess:17` bloqueia bd, não api;21–23 resolve extensão PHP; isso não corrige includes. DocumentRoot em `<repo>/public` deixa o legado fora da raiz salvo aliases/rewrite externos; DocumentRoot em `<repo>` pode torná-lo endereçável, sujeito a regras e bootstrap. Virtual host, aliases e regras efetivas não foram consultados. Relacionar [REI-329](https://linear.app/reis-developer/issue/REI-329) sem confundir scripts destrutivos já reproduzidos com estas entradas.

Não corrigir os includes como tarefa rotineira: isso pode ativar superfície antiga. Escolher explicitamente retirada, bloqueio ou refatoração se necessário. Busca Linear por nomes foi ampla/paginada, não certifica inexistência de issue dedicada; nenhuma issue nova é proposta como duplicidade resolvida.

## Delta e lacunas de inventário

Comparações Git somente leitura até822e36cf: `public/api` desde6b47f824, árvores `public`, `api`, `scripts` e `tools` desde33baf099, e app/services desde63eb1da4 sem mudanças. Também config/database/migrations/.github/scripts desde6b47f824 sem delta. Igualdade de blobs preserva referências estáticas, não transmite PASS funcional.

Workflows desde6b47f824: auth-isolated-e2e adicionou navegador; auth-sandbox-refusal adicionou topologia do navegador; rei148-mysql adicionou prontidão; rei73-inventario é novo e verifica snapshot histórico63eb1da4. Não descobre automaticamente novas operações.

| Lacuna | Classe | Destino proporcional |
|---|---|---|
| Deploy, staging migration, cron e limpeza em `.github/workflows/deploy-prod.yml` e `.github/scripts` | N: responsável operacional, recuperação e evidência por operação não consolidados | Revisão própria de comandos e recuperação; distinguir workflow success de aplicação de todas migrations. Sem exigir acesso produtivo. |
| `database/migrations/run_processo_prioridades_unificacao_data.php` | E/N: conexão direta e loop legado; leitura parcial, fora do inventário77/270 | Conciliar REI97/329 e inventariar guard CLI, efeitos, rollback/retry antes de executar. Não afirmar vulnerabilidade HTTP comprovada. |
| `tjba/metas-cartorio.php` e `sala-audiencias-pauta/tipos-list.php` | N/H: política pública/quotas/controles externos não estabelecidos | Manter hipótese; reconciliar decisões e issue antes de novo item. Não chamar serviços externos para completar cobertura. |
| `tools/ai`, configuração e serviço PJe | N: identidade executora, dados/destinos e controles herdados não completamente mapeados | Inventário operacional dirigido; configurações indisponíveis registradas como limite. Preservar suspensão/flags existentes. |

## Artefatos e fronteiras da evidência

Raiz local `C:/Users/jorge/AppData/Local/Temp/`; artefatos não são anexos públicos nem garantia de retenção permanente.

| Laboratório / JSON | SHA256 |
|---|---|
| rei104-event-http-lab-20261125/evidence-2aebd55f373a4c30a9e52eb0bab75daf.json | efa681ddd3b435a7ebef540aa64b5b13fec78c4a6c436b19ac3ce3b9300aa63c |
| rei73-pauta-delete-http-lab-20261125/evidence-261dddf7b1ff4d918c286304a9b44fea.json | e608d7db584a9b68cf6303b871c3a4e0d029749ab90561459b207c1b4cf72865 |
| rei73-cejusc-name-http-lab-20261125/evidence-52f52a000f9e4b82a93cca9c7d08093b.json | 7450d08a3f34da907c3eaecdd5bdbeee4df3c89a9bf178cf04b1e5eb387906a2 |

Esses três lifecycle registram IDs, consumidores antes da sentinela/fixture, sentinela e cleanup; consultas posteriores relatadas no Linear não encontraram recursos. Não equivale a inspeção produtiva. Perfis compostos, métodos/origens adversos não exercitados nessa rodada, concorrência e UI não herdam aprovação dos controles amostrados.

REI73 continua em andamento. Registrar reprodução e tarefa acionável não exige corrigir cada defeito dentro da auditoria, mas inventários e deploys documentais tampouco encerram o aceite. Este documento é somente preparação para revisão independente, sem commit ou publicação pelo autor.