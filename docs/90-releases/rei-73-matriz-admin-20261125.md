# REI-73 — inventário estático public/api/admin

Base fixa6b47f8249fd6653585dd025cd6d3427e2baa141d, confirmada no clone Temp/rei73-comarca-ui-20261125;25/11/2026 UTC. Skill jev-router e instruções CodeReviewer/copilot consultadas nesta continuidade. Somente leitura de código/Linear e criação deste relatório. Nenhum endpoint, PHP, teste, banco, Docker ou UI executado; produto e áreas concorrentes preservados.

## Contagem e controles

Git ls-files confirma5 arquivos: QUATRO rotas operacionais de feriados e UM helper que só define função. Não contar o helper como quinta operação de negócio desprotegida: acesso direto à definição não invoca a função.

Nenhuma dessas rotas usa ApiAccessGuard nominal. A autenticação é explícita Security::isAuthenticated. Create/update/list chamam requireAdminOuDiretoraJson; delete aplica controle próprio. Não transportar autorização de PageAccessRegistry para esses gates: usam interseção literal com perfis efetivos de UsuarioPerfisAutorizacao, incluindo supressão operacional ADMIN.

| Arquivo | Operação/método | Perfis/objeto/efeitos | Consumidor |
|---|---|---|---|
| _auth_admin_diretora_json.php:10–37 | Helper de inclusão, sem operação própria | Define função; quando chamada: JSON, sessão, Security401, conexão, usuarioEhAdminOuDiretora403; retorna PDO. Não limita método/origem por si só. | feriados/create,list,update:5–6 |
| feriados/create.php | POST confiável (helper8), JSON/form;201 | Security+ADMIN OU DIRETORA literais; FeriadosService50–65 normaliza tipo/regra/datas/ativo, gera ID, insere feriado e recalcula editais impactados se ativo na mesma unidade transacional. | app/views/internal/feriados-create.php:141 |
| feriados/update.php | POST confiável8; JSON/form ID22 | Mesmos perfis; S70–92 valida ID FER+26hex, normaliza payload, lock de registro, inexistente400, update e recálculo pelos intervalos antigo/novo ativos; responde success+quantidade. | app/views/internal/feriados-edit.php:178 |
| feriados/delete.php | POST confiável35; ID query ou form44, JSON fallback47 | Security17; DeleteAuthorization25–28 exige ADMIN OU CHEFIA literais. ID query não libera GET. S97–112 lock, existência e remoção confirmada, exclui fisicamente e recalcula pelo intervalo antigo se ativo. | app/views/internal/feriados-edit.php:215 |
| feriados/list.php | Parâmetros GET, sem whitelist de método/origem | Security+ADMIN OU DIRETORA literais; filtros tipo_feriado/q/ativo,15por página; S32–35 delega fetchListPaginated. Sem limpeza no caminho de serviço lido. | Nenhuma referência literal /api/admin/feriados/list.php encontrada em app/public; não inferir desuso nem autorizar remoção. |

## Perfis: diferença concreta

AdminDiretoraHelper.php:24–27 intersecta ['ADMIN','DIRETORA']; DeleteAuthorization::perfisAdminOuChefia retorna ['ADMIN','CHEFIA'] e usuarioPodeExcluir intersecta sem ProfileNormalizer. Assim, na ausência de outro perfil admitido:

| Perfil efetivo | create/update/list | delete |
|---|---|---|
| ADMIN | admite | admite |
| DIRETORA | admite | recusa |
| CHEFIA | recusa | admite |
| MAGISTRADA | recusa | recusa |
| SERVIDOR/ESTAGIARIO/CONSULTOR/ESTUDANTE | recusa | recusa |

Tabela é dedução estática, não testes de sessão. Perfis múltiplos admitem se qualquer literal permitido permanecer após supressão. O nome do helper e equivalências da política de páginas não normalizam automaticamente esses gates. Diferença demanda reconciliação com política, não é escalada comprovada nem autorização para alterar regras.

## Efeitos e riscos proporcionais

- S116–155 withCalendarTransaction recusa transação externa, adquire GET_LOCK MySQL 'fokus-law-feriados-recalc' antes begin, executa mutação+recálculo, verifica commit, rollback se ativo e RELEASE_LOCK em finally. S237–244 usa EditaisService na mesma conexão. Não repetir hipótese histórica de ausência de transação (REI135).
- Lock nomeado não é prefixado por banco; bases no mesmo servidor podem disputar esse nome. É possibilidade operacional observável no código, não contenção reproduzida ou motivo automático de alteração.
- RELEASE_LOCK no finally não tem catch para exceção de query; uma falha nesse ponto pode produzir erro após commit confirmado. Reproduzir em fixture isolada antes de classificar impacto de retry; nenhum teste feito aqui.
- Update/delete localizam registro sob lock e rejeitam inexistente; não presumir sucesso para ID ausente. Create/update validam datas reais, ordem início/fim, tipos e regras enumerados; descrição limitada em bytes. Ativo usa coerção booleana de PHP, inclusive texto 'false' não vazio vira ativo; confirmar contrato dos consumidores antes de chamar defeito.
- Helper autentica/conecta antes de gate origem nas mutações; conexões e consulta de perfis são anteriores ao try local. Recusa de origem impede mutação de negócio, mas não implica ausência de consultas/efeitos da camada de sessão. Não foi inspecionada toda infraestrutura de sessão.
- create/update retornam500 genérico; list53 e delete70 concatenam mensagem da exceção, correlacionar REI67. Não provocada falha nem observada resposta em produção.
- FeriadosRepository ainda contém purgeExpiradosUnicosOuMoveis15–25, mas presença do método não comprova chamada por esta listagem. O caminho admin/list não o chama no serviço lido. REI114 é manutenção/consulta de editais, não remoção explícita autorizada admin/delete.

## Linear e limites

Busca feriados com limit100 retornou hasNextPage=false; consulta direta REI114,122,135. Sem histórico integral de comentários/checks, sem escrita Linear.

- REI122 Concluído: seleção de editais atingidos por alteração/remoção. Serviço atual usa intervalos ativos antigo/novo e EditaisService704–731 deduplica e delega seleção. Não foi revisada integralmente a query seletora nem executado cálculo; não certificar aceite de seleção/jurídico apenas pela transação.
- REI135 Concluído: atomicidade mutação+recálculo; mecanismos atuais descritos acima, não hipótese antiga como atual. Testes passados não reexecutados.
- REI114 Em revisão: preservação de feriados históricos em consultas de editais; não afirmar expurgo nessa rota admin/list sem cadeia.
- REI67 Em andamento conforme busca: exposição de erros técnicos em list/delete correlata; não duplicar issue.

Testes apenas inventariados: tests/api-security/feriados-trusted-post.test.py, feriados-perfis-isolado.test.py, feriados-ativos-isolado.test.py; tests/feriados/rei122-135-atomicidade.test.php e run-mysql-isolated.ps1; tests/editais/preservar-feriados-historicos.test.php. Nenhum lido integralmente nem executado; zero alegação PASS.

Próximo recorte útil: reconciliação de perfis literais com política vigente e fixture HTTP cobrindo ADMIN efetivo/suprimido, CHEFIA, DIRETORA, MAGISTRADA, externo, sessão revogada, método/origem, inexistente e zero mutação recusada. Testes existentes devem ser inspecionados quanto a isolamento antes de qualquer uso. Para efeitos transacionais revisar evidência122/135 já existente antes de nova execução. Matriz não fecha REI73 nem certifica MySQL/UI/produção.
