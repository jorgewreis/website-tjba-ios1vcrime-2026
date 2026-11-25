# REI-73 — CEJUSC e complemento de inventário Ofícios Cartório

Base fixa: main remota 6b47f8249fd6653585dd025cd6d3427e2baa141d. Data25/11/2026 UTC. Somente leitura de Git/Linear e gravação deste relatório. Sem execução de testes, HTTP, banco, Docker ou alteração de produto/PR/Linear. Skill jev-router e instruções locais previamente consultadas. Nenhum resultado histórico abaixo significa execução nesta rodada.

## Escopo e prevenção de duplicação

Inventário git ls-tree confirmou4 handlers CEJUSC e5 Ofícios. CEJUSC: handlers lidos integralmente e serviço integral, trechos do repositório/consumidor. Ofícios já possui auditoria de transições e UI sintética registrada em REI90; apenas complemento estático de rotas e reconciliação das evidências, sem repetir investigação executável. Matriz versionada docs/90-releases/rei-73-matriz-bloqueios-2026-11-16.md:142,162,225 já inventariava famílias e guards.

## Permissões comuns

Cartório: PageAccessRegistry.php:43-53 define cejusc/cejusccreate/cejuscedit e oficioscartorioedit em area_a CARTORIO. AccessEvaluator/ProfileNormalizer permitem SERVIDOR-CARTORIO, ESTAGIARIO-CARTORIO e elevados ADMIN/CHEFIA com aliases DIRETORA/MAGISTRADA; perfis vêm de UsuarioPerfisAutorizacao, incluindo supressão operacional ADMIN. Misto interno/externo não é automaticamente externo. Não confundir perfil de página com alocação individual.

CEJUSC não usa ApiAccessGuard nominal: possui controle equivalente explícito Security::isAuthenticated + AccessEvaluator/perfis efetivos. Três mutações acrescentam bootstrapRequireTrustedPostRequest, que exige POST e origem completa confiável. Falha de autenticação nas mutações retorna403, não401; list retorna401. List lê GET sem whitelist de método. Todos5 Ofícios usam ApiAccessGuard oficioscartorioedit/write=true: POST/origem confiável e autenticação/perfil. Fallback ID em GET não permite método GET passar.

## Matriz CEJUSC — 4/4

Prefixo public/api/cartorio-cejusc/. S=app/services/CejuscRegistrosService.php; R=app/repositories/CejuscRegistrosRepository.php.

| Rota | Controle e operação | Objeto e persistência | Consumidor |
|---|---|---|---|
| create.php:12-17 | POST confiável manual, cejusccreate; 201,409 duplicata,400 validação,500 genérico | Corpo ApiJsonResponse; ator sessão. S50-75 valida CNJ/data/campos, consulta duplicata antes TX, permite force_duplicate, TX engloba processo/parte/vínculo/prioridade e registro. | public/assets/js/shared/cartorio-cejusc.js:110; confirmação409 e segundo envio force_duplicate:115-124 |
| update.php:12-16 | POST confiável manual, cejuscedit; devolve success conforme bool | ID corpo removido do payload; ator sessão. S78-108 valida, TX e findForUpdate; ausente retornafalse com rollback; valida parte/vínculo e atualiza. Não garante prioridade CEJUSC no processo novo. | mesmo JS:147-149 verifica success; erro recupera botão |
| delete.php:12-19 | POST confiável manual, cejuscedit + perfisIncluemServidorOuChefia | ID corpo; S111-117 só valida não vazio e R117-121 remove registro por ID. Não remove pessoa/processo/vínculo. | mesmo JS:160 e app/views/internal/cartorio-cejusc-list.php:160 |
| list.php:10-21 | Security + cejusc; leitura de GET sem restrição explícita de método/origem | q/mes_envio/page_number;15por página; S32-35 usa filtros normalizados comuns para list/count. | Nenhum consumidor literal localizado na busca dirigida; não inferir desuso, views podem chamar serviço diretamente. |

### Detalhes relevantes de CEJUSC

- Exclusão NÃO equivale a gestão/admin: DeleteAuthorization.php:57-78 normaliza perfis, aceita CHEFIA ou perfil contendo SERVIDOR, rejeita ESTAGIARIO, e não inclui ADMIN sozinho. É interseção com cejuscedit. ADMIN puro passa página mas falha segundo gate; ADMIN+SERVIDOR pode passar. Isto é comportamento estático, não defeito classificado sem política.
- S146-177 ensureParteVinculada: parte_id explícito deve existir E já ter vínculo com o processo; nome global é adotado. Sem ID explícito pode criar pessoa/vínculo. Não reproduzir a hipótese de ID arbitrário da Pauta como se valesse aqui.
- S58-63 verifica duplicidade antes TX pelo nome informado; nome pode depois ser substituído pelo cadastro global. Concorrência ou nome divergente podem contornar a advertência, hipótese sem execução. A UI permite duplicata confirmada explicitamente; não declarar unicidade obrigatória nem transformar force_duplicate em bypass indevido por si só.
- REI123 cobre prioridade: create chama ensureParteVinculada(data,true), update false; processo antigo/novo e múltiplas fontes exigem contrato de reconciliação. Delete também só remove registro: ciclo de vida da prioridade deve ser conciliado na mesma issue antes de nova tarefa.
- Conexão de negócio ocorre dentro de try, mas checagem de perfis anterior pode consultar persistência; não afirmar que toda falha de infraestrutura chega ao catch JSON.

## Ofícios — complemento de matriz, sem repetir auditoria existente

Prefixo public/api/cartorio-oficios/. Guard de todos linha4: oficioscartorioedit, Cartório, POST confiável. S=app/services/OficiosCartorioService.php.

| Rota | Objeto/controle adicional | Consumidor | Correlação |
|---|---|---|---|
| create.php | JSON/POST; numero_processo formato/checksum23-40; criado_por da sessão42; S125+ TX138 para cadastro. | app/views/internal/cartorio-oficios-list.php:694 | REI88 para processos e REI67 para erro; não novo teste |
| concluir-subtarefa.php | subtarefa_id/subtarefaId28, ator sessão35; S255-295 resolve filho/pai, recusa pai cancelado/finalizado/filho concluído. Marca filho antes de validar ordem; não exige ordem1 concluída para ordem2. Endpoint38-41 lê status, chama serviço, registra evento, sem TX externa. | mesma view:782 | REI90; backend/concorrência não validados pela UI simulada |
| cancel.php | ID; S297-325 confirma pai, cancelada idempotente; helper rejeita finalizada; remove etapas antes update, sem TX no método. | view edit246 e list696 | REI89 |
| delete.php | DeleteAuthorization23 exige ADMIN/CHEFIA/DIRETORA/MAGISTRADA efetivos; S328-354 confirma maior sequencial anual, remove filhos e depois o pai, sem TX no método. | view edit247 | REI89; candidata não confundir com main |
| desalocar.php | alocado_id26/tipo27; gestor exigido apenas se cliente declarar rule/all47. S59-67 ignora tipo e ator além não vazio; repository deleteById111 usa ID. | view edit197/list519 | Mesmo caminho de classificação adulterada das matrizes Guias/Pauta; REI-321 concentra a correção transversal |

A desalocação tem caminho estático consistente com tipo=specific/omitido para alocação protegida. Após a leitura inicial, serviços e repositórios reais reproduziram a remoção de dados sintéticos rule/all com tipo specific em SQLite em memória; REI-321 registra seis cenários em três famílias, sem HTTP ou MySQL. Exige usuário admitido na página, origem confiável e ID existente. Não alegar acesso anônimo, estado real nem exploração. Não foi auditada integralmente a infraestrutura transitiva de Ofícios.

## Linear e evidência histórica

Fontes diretas: https://linear.app/reis-developer/issue/REI-89 , /issue/REI-90 , /issue/REI-123. Descrições integrais consultadas; último comentário de89/90, sem paginação integral. Busca CEJUSC no projeto retornou REI69/88/131/123/139, sem página seguinte; busca textual não prova ausência de outra issue.

- REI89 Em revisão: descrição registra candidata#441, SQLite e MySQL8.4.7 sintéticos PASS em19/11, revisão/DEV pendentes. Comentário a6e7c28e de22/11 é registro histórico de repositório ocupado. Não tratá-lo como restrição nova vigente nem evidência de integração. Main ainda contém métodos sem transação conforme linhas acima.
- REI90 Em andamento: comentário ef74e2d3 de25/11 00:11Z registra8 cenários Chromium159 com fragmentos e JS reais, respostas interceptadas e notificações adaptadas; sem login/backend/banco/concorrência. Evidência em Temp/rei73-oficios-ui-evidence-HDdyzd/evidence.json. Reaproveitar; não repetido. Não prova ordem no servidor nem atomicidade de evento.
- REI123 Em andamento: já trata prioridade CEJUSC quando processo muda; a flag false na edição permanece. Testes multirregistro/antigo/novo pertencem a esta frente.
- REI69 relacionada a chamadores indiretos de vínculo; CEJUSC possui verificação de pertencimento para parte_id explícito. Não inferir falha idêntica à Pauta.
- REI88/139 relacionadas a ensureProcess e prioridades; não assumir que contrato específico de Cartas foi implementado aqui nem reabrir correção histórica sem caso reproduzido.
- REI67 cobre respostas técnicas de Ofícios; CEJUSC tem mensagens500 genéricas locais.

## Verificações futuras e limites

Inventariados, não executados: tests/cejusc/autoria.test.php, http-fixture.php, http-flow.test.php, http-run-isolated.ps1, mysql-isolated.test.php, mysql-run-isolated.ps1. Conferir isolamento antes de qualquer uso. Arquivo de teste presente não demonstra PASS ou cobertura.

Próximo recorte útil: matriz HTTP CEJUSC de perfis (incluindo ADMIN puro e SERVIDOR/ESTAGIARIO), ausência de registro, parte de outro processo, falha de atualização e resposta409/force_duplicate; snapshots de processo/pessoa/vínculo/prioridade/registro e falhas pós-escrita. Para REI123 testar troca de processo e fontes múltiplas; para desalocação coordenar fixture comum entre famílias. Concorrência exige MySQL descartável autorizado e isolamento verificado. Não se afirma infraestrutura inacessível, porque não foi tentada.

Conclusão: CEJUSC4/4 mapeado e Ofícios5/5 reconciliado como complemento de inventário. Nenhuma execução nova, correção, publicação ou fechamento de issue. Guard nominal ausente em CEJUSC não significa autenticação ausente; UI histórica de Ofícios não valida backend.
