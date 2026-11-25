# REI-73 — matriz estática sala-audiencias-ata

## Base, procedimento e limite

25/11/2026 UTC. SHA fixo 6b47f8249fd6653585dd025cd6d3427e2baa141d, confirmado por git rev-parse no clone Temp/rei73-comarca-ui-20261125. Inventário rastreado completo desta família:4 PHP. Lidos integralmente os quatro handlers e trechos de SalaAudienciasAtaService60–465, helper sigilo, controles de página/perfil e repository de inscrição; consumidores buscados por git grep. Skill jev-router e instruções CodeReviewer/copilot já consultadas nesta continuidade. Agente qualidade delegado pelo coordenador. Não foram executados testes, PHP, HTTP, navegador, banco, Docker ou limpeza. Apenas este relatório temporário criado; produto/.qa preexistente preservados.

S = app/services/SalaAudienciasAtaService.php. Prefixo rotas public/api/sala-audiencias-ata/.

## Inventário

| Handler | Operação / método | Sessão e perfis observados | Objeto e controles | Consumidor |
|---|---|---|---|---|
| upload.php | POST multipart; método405 antes guard | ApiAccessGuard salaata write=true linha18: Security, POST confiável Origin/Referer, perfis efetivos. salaata é area_a AUDIENCIAS: SERVIDOR/ESTAGIARIO-AUDIENCIAS ou elevados admitidos pelo avaliador; não qualquer interno. | POST pauta, FILES arquivo, ator sessão. S119–138 pauta ativa, helper sigilo, status realizada/finalizada. Limite2MB, origem arquivo de upload, MIME PDF aceito ou assinatura PDF como alternativa. Substituição nova antes de commit/remoção antiga. S140 executa limpeza global antes de validar arquivo. | public/assets/js/modules/audiencias/atas-list.js:109 |
| inscrever.php | POST JSON/form;405 antes guard | Guard estudapainel write=true linha23; depois exige perfil cadastral externo com ESTUDANTE (linha38). ADMIN admitido pela política de página ainda não satisfaz segundo gate; perfil misto interno também não. Ator sessão, nunca corpo. | Corpo pauta; S260–301 confirma pauta ativa, elegibilidade atual, inexistência inscrição, designada/agendada e data hoje, COUNT<5 então INSERT. Sem transação/lock abrangente observados. | public/assets/js/modules/audiencias/atas-estudantes.js:48 |
| download.php | GET restrito; attachment PDF | Linhas22–42: ID sessão, perfis efetivos em estudaata, depois estudante externo cadastral. NÃO chama Security::isAuthenticated nem ApiAccessGuard. A chave estudaata não consta no registro/allowlist atual: bloqueio funcional antes do serviço (A1). | Query pauta; S338–383: limpeza global antes de inscrição, verifica inscrição+pauta ativa+sigilo/menor/prioridades atuais, validade e arquivo. Esses controles do serviço não provam fluxo HTTP permitido. | app/views/internal/sala-audiencias-atas-estudantes.php:180 |
| download-interno.php | GET restrito; inline PDF | Linhas19–27: ID sessão + salaata pelos perfis efetivos; sem Security::isAuthenticated ou guard explícito. Mesmo conjunto AUDIENCIAS/elevados do upload. | Query pauta; S389–429: limpeza global antes de buscar pauta, exige pauta ativa, helper sigilo, ata, validade (se expires_at preenchido), arquivo existente. Não exige inscrição, menor ou prioridades gerais; não inferir que deveria copiar política externa. | Nenhuma referência literal download-interno.php encontrada em app/public; não prova ausência de consumidor indireto e não autoriza remoção. |

Autorização de perfil usa UsuarioPerfisAutorizacao e sua supressão operacional ADMIN; AccessEvaluator normaliza compostos/elevados e distingue externo/misto. Presença de ID de sessão não equivale a autoridade de sessão válida. Nenhuma matriz de perfis foi executada aqui.

## Achados e reconciliação atual

A1 — Download estudante recusado pela chave não registrada. download.php30 chama canAccessPage('estudaata'); AccessEvaluator.php58 permite estudantes externos apenas estudapauta/estudapainel (mais outras exceções que não incluem estudaata); PageAccessRegistry não mapeia estudaata e regra desconhecida nega antes de bypass elevado. Logo o caminho legítimo estudante não alcança o serviço nesta base. Correlacionar REI99/PR448 citada na descrição e matriz REI83; não presumir candidata publicada. Não foi executado HTTP nesta rodada.

A2 — Sessão nos downloads. Os dois handlers verificam ID e perfis, mas não chamam Security::isAuthenticated; REI99 Em andamento descreve candidato e teste anterior na PR448, não prova inclusão na base6b47. Para interno, lacuna ainda é alcançável pela lógica estática, sujeita a bootstrap/infra externa; estudante primeiro encontra A1. Nenhuma exploração/sessão real foi utilizada.

A3 — Leitura com limpeza global permanece. S65 e111 nas listagens,346 e396 nos downloads chamam purgeAtasExpiradas. O estudante no serviço dispara limpeza antes da existência da própria inscrição; interno antes da existência da pauta. S140 também limpa durante upload antes de validar tamanho/origem/PDF. REI87 Em andamento continua pertinente na base lida; não chamar esses serviços em teste sem storage/DB explicitamente isolados. Esta revisão não os executou.

A4 — Retenção perde referência após falha de unlink. S438–457 usa @unlink448 sem conferir retorno, depois clearArquivoAposExpiracaoRetencao452. REI82 Em andamento continua pertinente. O código não demonstra recuperação de falha de disco nem coordenação com substituição. Concorrência e falha requerem prova isolada; não afirmar ocorrência em produção.

A5 — Vagas: S294–301 faz COUNT e INSERT separados, repository30–52 também. REI77 Em andamento: corrida de duas inscrições na última vaga permanece hipótese sustentada por código, não reprodução MySQL. Não garante limite nem duplicação sem examinar constraints reais. A descrição também registra polling recriando botão durante pedido; UI não foi reavaliada neste recorte.

A6 — Substituição: REI65 Concluído não deve ser reaberta com a falha histórica da descrição. Código atual move arquivo novo178, abre transação195, lê ata com lock196 (repository FOR UPDATE), persiste206, commit217 e só então remove antigo228. Falha no banco reverte transação se ativa e tenta remover novo222. Falha ao remover antigo é logada. Esta ordem preserva o antigo contra falha ordinária de move/persistência; leitura não certifica concorrência, crash, storage real ou testes de aceite. Retenção global anterior segue A3/A4 como frente distinta.

A7 — Revalidação de elegibilidade: REI163 Planejado possui descrição histórica em que download só consultava sigilo. Na base atual S356 chama assertPautaDisponivelParaEstudante304–333, que verifica sigilo, menores ativos e prioridades bloqueantes; logo já não corresponde ao comportamento antigo descrito. Isso vale para o serviço; A1 impede comprovar fluxo legítimo da rota. Reconciliar política/aceite e evidência existente antes de fechar163.

A8 — Escritas já protegidas: upload18 e inscrever23 chamam ApiAccessGuard write=true; REI68 Em revisão e REI80 Em andamento têm descrições históricas de ausência de origem. Não repetir essa ausência como fato atual. Aceite de UI/multipart/recusas e ausência de efeitos nas recusas não foi executado aqui. upload69 ainda concatena Throwable::getMessage, correlacionar REI67; inscrever e downloads têm catch técnico genérico.

## Evidências consultadas e pendências

Leituras diretas Linear das descrições e estados: REI65 Concluído,77 Em andamento,82 Em andamento,87 Em andamento,99 Em andamento,163 Planejado; complemento83 Concluído,68 Em revisão,80 Em andamento. Histórico completo de comentários e checks da PR448 não consultados; não apresentar estado da candidata como verificado agora. Nenhuma escrita Linear. Estados sujeitos a mudança.

Testes apenas inventariados por nomes: tests/api-security/ata-upload-trusted-post.test.py; tests/ata/ata-replacement.test.php e ata-replacement-concurrency.test.php; sandbox guards/runners; tests/pauta/estudante-inscricoes-privacidade.test.php e estudante-atas-render-sintetico.test.py. Não foram lidos integralmente nem executados e não recebem PASS neste relatório.

Próxima ação proposta: reconciliar branch/PR448 com base remota atual antes de tocar REI99; testar permissões e revalidação com PDF fictício/storage temporário e conexão sintética, sem rodar serviço sobre checkout com configuração real. Retenção87/82 e concorrência77 têm responsabilidades diferentes e precisam fixtures dedicadas. Relatório não fecha REI73 ou qualquer issue, não certifica autenticação em browser/MySQL/produção, nem ausência de falhas em todos helpers transitivos.
