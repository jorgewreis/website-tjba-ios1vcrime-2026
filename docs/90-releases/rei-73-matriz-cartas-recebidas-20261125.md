# REI-73 — matriz de Cartas Recebidas

Base: main 6b47f8249fd6653585dd025cd6d3427e2baa141d. Os nove handlers e CartasRecebidasService são idênticos à cópia lida em 74085526 (git diff sem caminhos); leitura estática em 25/11/2026. Responsável funcional: Cartório; não atribui pessoa responsável. Inventário dos nove PHP sob public/api/cartorio-cartas-recebidas, não de todos os caminhos do módulo.

O guard chama Security::isAuthenticated e consulta perfis efetivos. As chaves cartasrecebidas, cartasrecebidascreate e cartasrecebidasedit usam regra area_a CARTORIO; perfis elevados recebem acesso conforme AccessEvaluator, e a classificação de perfil misto depende do helper. Mutação usa write=true, que exige POST confiável pela rotina do guard. Não se observou no guard parâmetro de objeto; isso não comprova falha sem política de acesso por registro e exame de serviços.

| Handler | Operação e objeto | Guard/chave e método | Consumidor localizado | Evidência/limite |
|---|---|---|---|---|
| list.php | lista, paginação e contadores por filtros | leitura, cartasrecebidas; não há restrição explícita de método no handler/guard | consumidor direto não localizado por busca literal; view lista usa serviço diretamente | listCartas/counts lidos como consultas; catch Throwable devolve mensagem interna (REI-67); sem sessão/SQL integrado nesta leitura |
| buscar-cidade.php | busca cidade/UF e resolve comarca-sede | leitura, cartasrecebidas; GET e fallback POST para cidade, sem gate explícito de método | view create e cartorio-cartas-recebidas-edit.js | valida UF e trata ambiguidade; navegador sintético de edição em REI-73, sem backend/comarca real |
| create.php | cria carta a partir do payload/CNJ | escrita, cartasrecebidascreate; POST confiável | cartorio-cartas-recebidas-create.js | service usa advisory lock e transação própria; não foi validado contrato de commit/retry aqui; Throwable inclui mensagem interna (REI-67) |
| update.php | altera carta por ID | escrita, cartasrecebidasedit; POST confiável | cartorio-cartas-recebidas-edit.js | usuário da sessão sobrepõe payload; sem prova de autorização por registro; Throwable inclui mensagem interna (REI-67) |
| cancel.php | cancela por ID | escrita, cartasrecebidasedit; POST confiável | cartorio-cartas-recebidas-edit.js e RegistroMetadataHelper | verificar atomicidade/corrida e efeitos em subtarefas; Throwable inclui mensagem interna (REI-67) |
| delete.php | exclui por ID | escrita, cartasrecebidasedit; POST confiável; adicionalmente DeleteAuthorization exige ADMIN/CHEFIA/DIRETORA/MAGISTRADA | cartorio-cartas-recebidas-edit.js | verificar associação e efeitos transacionais; Throwable inclui mensagem interna (REI-67); limpeza global histórica acompanhada na REI-107 |
| criar-primeira-subtarefa.php | cria etapa inicial por ID de carta | escrita, cartasrecebidasedit; POST confiável | consumidor literal não localizado | verificar repetição e concorrência; Throwable inclui mensagem interna (REI-67) |
| concluir-subtarefa.php | conclui etapa por ID e registra dashboard | escrita, cartasrecebidasedit; POST confiável | consumidor literal não localizado | falha parcial e perda de evento reproduzidas SQLite, REI-104; erro interno observado, REI-67; sem HTTP/MySQL |
| avancar.php | seleciona etapa em andamento ou primeira pendente elegível por ID da carta, conclui e registra dashboard | escrita, cartasrecebidasedit; POST confiável | cartas-recebidas/v2-list.js:27 | seleção e operação não compartilham transação no handler lido; hipótese de corrida/efeito parcial requer reprodução; erro genérico no catch Throwable |

## Evidências e fronteiras

- `PageAccessRegistry.php:36,61–63`, `AccessEvaluator.php:16–42,80–96`, `ApiAccessGuard.php:13–35`, `DeleteAuthorization.php:11–14,28–42`, handlers nomeados e `CartasRecebidasService.php:43–63,67–89,202,427`.
- Em `concluir-subtarefa`, teste em memória com gatilhos reproduziu pai/subtarefas parcialmente avançados e evento ausente na retentativa (REI-104); guard, bootstrap e conexão foram substituídos, não é teste de autorização HTTP.
- Edição de comarca teve nove cenários Chromium com respostas interceptadas e fragmentos reais; sem validação de backend, sessão ou persistência (evidência Temp/rei73-comarca-ui-20261125/.qa/evidence.json).
- Falta validar guarda e perfis por rota com sessão sintética, cenário de registro, exclusões/cancelamentos, concorrência MySQL, schema/infra e UI autenticada. Ausência de referência literal não prova ausência de consumidores indiretos. Esta matriz é cobertura parcial, não certificação.
