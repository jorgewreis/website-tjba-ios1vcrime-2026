# REI-73 — matriz estática de sala-audiencias-pauta

## Base e escopo

25/11/2026 UTC. Base fixa 6b47f8249fd6653585dd025cd6d3427e2baa141d, conferida por git rev-parse no clone exclusivo Temp/rei73-comarca-ui-20261125. Inventário git ls-tree: 17 PHP. Não usar main local antiga d2d05a8e como atualização. Lidos os 17 handlers, trechos centrais do SalaAudienciasPautaService e repositories/helpers citados; consumidores procurados com git grep rastreado. Não houve execução de testes, PHP, HTTP, banco, Docker, e-mail, alterações de produto, Linear ou PR. Único arquivo criado nesta rodada: este relatório.

Skill jev-router confirmada no catálogo e lida, com orientações CodeReviewer e copilot do projeto. Auditoria delegada ao agente de qualidade; sem novos agentes necessários neste recorte. Não alegar revisão exaustiva de cada helper transitivo.

## Legenda e política

P: guard write=true exige autenticação, POST e Origin/Referer confiável (host, esquema, porta), depois política da página. R: write=false, parâmetros de query; não há rejeição explícita de outros métodos nesses handlers. ID de sessão é ator; não é autorização por objeto.

A: PageAccessRegistry area_a AUDIENCIAS: composto SERVIDOR/ESTAGIARIO-AUDIENCIAS ou elevados aceitos pelo AccessEvaluator; perfis efetivos via UsuarioPerfisAutorizacao (supressão operacional ADMIN), normalização e classificação externo/misto se aplicam. Não significa qualquer usuário interno. Chaves salapauta, salapautacreate, salapautaedit, salapautaparteedit e salapainel usam A.

14 handlers usam ApiAccessGuard; resumo e status-bulk têm autenticação/autorização manuais; tipos-list não possui controle explícito identificado. São 12 P via guard, 2 R via guard, 1 POST confiável manual, 1 leitura manual e 1 catálogo sem controle explícito. Contagem não equivale a segurança comprovada.

S = app/services/SalaAudienciasPautaService.php. Caminho das rotas: public/api/sala-audiencias-pauta/.

| Rota | Operação, método, chave/perfil | Objeto/serviço e limites | Consumidor localizado |
|---|---|---|---|
| create.php | Criar pauta; P salapautacreate/A, guard12 | Payload, ator sessão; S172–258: valida data/grade, slot; lock anual antes da transação, classe/processo dentro dela, ensureProcess(...null,null), impede pauta bloqueante mesmo processo. 201; lock503 Retry-After5. | modules/audiencias/pauta-create.js:300 |
| update.php | Editar pauta; P salapautaedit/A, guard12 | ID corpo; S260–312 exige pauta ativa; status do payload não repete invariantes de setStatusPainel. Pós-processamento separado. REI131. | pauta-edit.js:355 |
| cancel.php | Cancelar; P salapautaedit/A, guard12 | ID corpo, ator sessão; S391–394 setStatus; repository120 filtra ID/ativo e retorna rowCount. Sem gate adicional gestor/autor observado. | pauta-edit.js:596; view lista547 |
| delete.php | Desativar pauta; P salapautaedit/A, guard12 | ID corpo; S396–403 softDelete, depois apaga alocados se ok; sem transação abrangente ou gate gestor adicional observado. Não transplantar regra de exclusão de outro módulo. | pauta-edit.js:611 |
| alocar.php | Adicionar alocado; P salapautaedit/A, guard12 | ID pauta + usuario_id do corpo; ator sessão apenas conferido no handler35. S95–108 confirma pauta ativa, alvo não vazio, COUNT limite5 e insert separados. Não comprova elegibilidade do alvo ou limite concorrente. | pauta-edit.js:930 |
| desalocar.php | Remover alocado; P salapautaedit/A, guard13 | alocado_id e tipo do corpo; handler44–50 exige ADMIN/CHEFIA/DIRETORA/MAGISTRADA apenas se tipo rule/all; S111–120 ignora tipo e apaga pelo ID. Achado A1. | view edit503 e lista464, widget por data-endpoint |
| occupied-slots.php | Disponibilidade; R salapautaedit/A, guard12 | Query data/except_id; S892+; retorna available, não horários ocupados apesar do nome. Sem reserva de slot nem prova de concorrência. | pauta-create.js:220; pauta-edit.js:837 |
| partes-create.php | Criar parte de audiência/vínculos; P salapautaparteedit/A, guard12 | id_pauta; S406+ confirma pauta/processo, transação de parte/vínculo; aceita IDs globais/vínculo do payload. Achados A2/A3. | pauta-edit.js:465 |
| partes-update.php | Atualizar parte/vínculo; P salapautaparteedit/A, guard12 | id_parte; S543+ exige parte existente, aceita processo_parte_id do corpo e atualiza vínculo sem conferir pertencimento na cadeia lida. A2/A3. | pauta-parte-edit.js:184 |
| partes-delete.php | Desativar parte; P salapautaparteedit/A, guard12 | id_parte; S1013–1030 resolve parte, softDelete, depois duração/prioridades; sem transação abrangente observada. | pauta-edit.js:522; pauta-parte-edit.js:140 |
| prioridades-add.php | Prioridade do processo via pauta; P salapautaedit/A, guard15 | id_pauta/prioridade; S1113–1129 exige pauta ativa/processo, catálogo; delega ProcessoPrioridadesService. Deprecated, não significa desativado. | literal deste endpoint não encontrado no código de produto pesquisado; JS atual usa /api/processos |
| prioridades-remove.php | Remover prioridade via pauta; P salapautaedit/A, guard15 | S1132–1148 exige pauta/processo, catálogo e quantidade menores, delega regras de remoção. Deprecated. | literal não encontrado |
| prioridades-list.php | Estado de prioridades via pauta; R salapautaedit/A, guard16 | query id_pauta; S1084–1107 confirma pauta/processo, quantidade menores e projeção do serviço de prioridades. Sem regra setorial adicional inferida, REI75. | literal não encontrado |
| set-status-painel.php | Transição rápida; P salapainel/A, guard14 + checagem salapauta no handler43–47 | ID/ação corpo; S323–384 exige pauta ativa, hoje, ausência de outra em andamento e estado compatível; leitura e update separados. REI131. POST405 local vem depois do guard (não garantir405 para método inválido). | pauta-list.js:97 usa URL relativa api/... |
| resumo.php | Exportar TXT/PDF; auth manual Security + salapautaedit/A; query, sem whitelist explícita de método | ID/formato; SELECT pauta ativa;404 inexistente,409 fora designada/redesignada normalizadas; builder; PDF remote=false. Catch técnico500 genérico. Não prova conteúdo exportado nem controle externo. | view pauta-edit378 data-resumo-base |
| status-bulk.php | Consulta de status; POST405 explícito, Security + trustedPOST manual, estudapainel efetivo + estudante externo em perfis cadastrais | IDs filtrados/deduplicados, SELECT ativo; rejeita interno/misto na segunda checagem; bloqueadas retornam somente id_pauta/indisponivel via helper. Sem limite numérico de IDs observado. Não efetua inscrição. REI161/106. | views painel-dia415 e painel-estudantes1052 |
| tipos-list.php | Catálogo; sem autenticação/perfil/método explícitos no handler7–13 | listTiposAtivos em S45–47; não inferir confidencialidade do catálogo nem exposição externa comprovada. A4. | literal não encontrado no código de produto pesquisado |

## Achados estáticos prioritários

A1 — Desalocação confia na classificação fornecida pelo cliente. `desalocar.php:28–29,44–56`, S111–120 e `SalaAudienciasPautaAlocadosRepository.php:139–151`: usuário admitido em salapautaedit, sem perfil gestor, evita o gate enviando tipo=specific ou omitindo tipo para ID de alocação rule/all; serviço não compara tipo persistido e repository DELETE filtra só ID. É desvio demonstrável na lógica de controle, não reprodução HTTP/SQL. Condições: conhecer ID existente e alcançar rota com sessão autorizada. Próxima prova: fixture de handler+repository sintético preservando tipo real, exigir zero delete quando classificação adulterada. Busca Linear alocação/desalocar não encontrou issue específica; hipótese semelhante ocorre em Guias e ambas devem ser conciliadas antes de criar issue.

A2 — Identidade do vínculo não é reconciliada com audiência/pessoa. S439,491–496 (create) e568,587–591 (update) recebem processo_parte_id e chamam update; `ProcessosPartesRepository.php:477–516` atualiza por ID, sem processo/parte no WHERE. No caminho com pessoa global válida, sua existência é conferida, mas isso não garante que vínculo pertença à pessoa/processo. Pode alterar tipo/polo de vínculo diferente e registrar associação inconsistente. Reconciliar REI69 (aceite inclui outros endpoints relacionados), sem presumir que correção da família /processos-partes proteja estes chamadores. Nenhuma exploração/DB executados.

A3 — Parte/vínculo commitados antes de efeitos derivados. S528–535 e639–653 fazem commit antes da sincronização de duração/prioridades; falha posterior chega a catch com rollBack sem verificar transação. Risco: resposta de erro com parte persistida, prioridades/tempos parciais e exceção de rollback mascarando causa. deleteParte1013–1030 também separa mutação e efeitos. Verificar injeção sintética de falha pós-commit, repetição e retorno antes de propor correção; não alegar reprodução.

A4 — tipos-list.php não tem autenticação explícita; includes Conection e serviço não demonstram guard de identidade. Decide-se política do catálogo antes de classificá-lo como vazamento; resposta de Throwable concatena mensagem técnica (REI67). Ausência de consumidor literal não autoriza remover rota.

A5 — Erros técnicos em vários handlers concatenam getMessage (create40,update47,alocar51,desalocar63,cancel37,delete37,partes e prioridades/occupied/tipos). REI67 já existente. resumo/status-bulk têm catch técnico genérico. Conexão fora try em create/update/alocar/desalocar reduz alcance do catch local; não inferir conteúdo HTTP final sem examinar handler global.

A6 — Limites5 de alocação e slot de audiência são checagens separadas das gravações; lock anual de sequencial não prova exclusividade de slot porque checagem de slot antecede lock. O repositório deduplica alocado existente; a hipótese de concorrência persiste sem demonstração e requer constraints efetivos e teste MySQL isolado, não executado.

## Reconciliação de issues, sem gravação

Consulta Linear somente leitura: busca pauta com limit100 retornou hasNextPage=false; buscas alocação/desalocar também sem página seguinte. Leitura direta das descrições e estados REI69,75,88,131,136,161. Não foi lido/paginado histórico completo de comentários; estados podem mudar após coleta. Busca textual não é inventário completo do backlog.

- REI69 Em revisão: aceite manda verificar outros endpoints de processos/partes relacionados; A2 deve ser levado à mesma frente antes de criar issue.
- REI75 Concluído: decisão na descrição permite salapautaedit gerir prioridades de qualquer processo local; não inventar ACL setorial de leitura ou gestão de prioridade. Estes wrappers legados têm guard e existência; cobertura executada dos wrappers não foi verificada aqui.
- REI88 Em andamento: descrição já reconcilia merge416 e runner MySQL anterior; no código atual criação de pauta usa null para sigilo e ensureClass/ensureProcess dentro da transação. Não repetir falha histórica como fato atual da pauta. Issue permanece ampliada para outros consumidores; nenhuma validação de execução nesta rodada.
- REI136 Concluído: lock anual existe em S195–201 e handler retorna503 para lock ocupado; não reabrir alegação antiga de MAX+1 sem lock. Não certificar concorrência por leitura.
- REI131 Em andamento: divergência update260–312 versus setStatusPainel323–384 permanece visível; status do corpo passa sem mesmas checagens. Reconciliar também create status224. Não corrigir em paralelo.
- REI161 Em revisão: status-bulk já usa resposta mínima para bloqueadas via SalaAudienciasEstudantePrivacidade; não repetir descrição histórica de envio de flags sensíveis como implementação atual. Identidade estudante externo é conferida duas vezes com perfis efetivos/cadastrais.
- REI67 Em andamento (busca): sanitização técnica já tem issue; reutilizar.

## Próxima prova e limites

Prioridade sugerida: A1 e A2, em fixture HTTP sintética com guard real e persistência descartável cuja isolação seja revisada antes de executar; depois A3 com falha pós-commit. Nenhuma autorização de execução/alteração é inferida deste plano. Para12 P via guard e status-bulk exigir método/origem; para R/resumo/tipos não inventar expectativa de405/origem ausente no código.

Testes apenas inventariados: tests/pauta/actions.test.js, create-recovery.test.js, horarios-obsoletos.test.js, estudante-fronteira-http.test.py, estudante-privacidade.test.php, status-bulk-no-debug.test.py, tests/processos/pauta-transaction*.php e runner MySQL; não lidos integralmente nem executados nesta rodada. Não atribuir PASS, aprovação de UI/autenticação real, migrações, constraints efetivos, integridade PDF, produção, nem conclusão REI73. Produto e .qa preexistente preservados.
