# REI-73 — rotas avulsas e exports internos

Base6b47f8249fd6653585dd025cd6d3427e2baa141d,25/11/2026 UTC. Somente inspeção estática Git/Linear e este relatório novo. Não houve teste, endpoint, banco, serviço externo, credencial, produto, PR ou Linear alterado. Instruções locais/jev-router previamente consultadas. Seis arquivos lidos integralmente: cinco handlers (um tombstone) e um helper; não seis APIs independentes.

## Matriz

| Arquivo | Operação/identidade/método | Objeto, efeito e resposta | Consumidor |
|---|---|---|---|
| public/api/alterar_senha.php | Security::requireAuthentication11; POST13; token CSRF25; nenhuma exigência de perfil administrativo | usuário exclusivamente Security::getUserId47, nunca ID do payload; senha atual/nova/confirmada; Usuario::alterarSenha56.422 validações,500 genérico. requireAuthentication pode redirecionar ao login antes do contrato JSON; não presumir401 para toda sessão inválida. | public/views/public/alterar-senha-form.php:126 |
| public/api/cadastro_estudantes.php | cadastro público, POST12, sem guard de sessão/perfil/CSRF/origem explícito | valida campos/CPF/senha/aceite, mutex por CPF109, TX118, nunca altera existente121-126; cria usuário/perfil ESTUDANTE e tenta setor; resposta pública indistinguível para existente/nova/conflito1062. Várias validações retornam HTTP200 com success=false. | public/views/public/cadastro-form.php:253 |
| public/api/email_hint.php | tombstone, sem autenticação necessária para a resposta atual | sempre404 JSON fixo, sem include/consulta de conta. Não enumera e-mail no código atual. | nenhum consumidor literal ativo localizado na busca dirigida; não autoriza remover |
| public/api/internal-list-export/_common.php | helper de funções, não chama bootstrap automaticamente | internal_list_export_bootstrap16 exige Security18, ID sessão22, page GET27, perfis efetivos33 e AccessEvaluator35; retorna PDO/user/page. Inclusão direta define funções e carrega includes que podem inicializar sessão/configuração; não executa a função de bootstrap nem exporta por si. | csv.php/pdf.php |
| public/api/internal-list-export/csv.php | bootstrap comum8; page específica, sem whitelist explícita de método/origem | dispatch whitelist9; filtros GET; CSV com no-store, proteção textual de células; InvalidArgument400/403 por mensagem, Throwable500 genérico. | app/views/internal/_list_export_buttons.php:33; listas Cartas/Editais e outras |
| public/api/internal-list-export/pdf.php | mesmo controle comum e dispatch; não é somente ADMIN | PDF11; InvalidArgument400/403, RuntimeException500 com getMessage17, outros500 genéricos. Dompdf via helper remoto=false. | _list_export_buttons.php:34 e CEJUSC lista40 |

## Senha: escopo real e evidência existente

Security::requireAuthentication chama isAuthenticated e renova atividade; não é ApiAccessGuard nominal, mas possui controle equivalente de identidade. CSRF específico deve ser testado como tal, não substituído por expectativa de Origin. Usuario.php:229-282 confirma senha atual, política e diferença/histórico opcional; UPDATE por id sem transação abrangente/compare-and-swap de hash, sem invalidar tokens de recuperação no método lido. Não confundir troca autenticada com reset público.

REI155 Em andamento: comentário e4fde6be (24/11 23:43Z) registra reprodução anterior com Usuario e validateResetToken reais, SQLite em memória/stub de conexão: token pendente continuou válido após troca. Não exercitou resetPassword, MySQL/schema/triggers nem concorrência. A posse do token continua pré-condição; não alegar exposição. Matriz versionada docs/90-releases/rei-73-matriz-bloqueios-2026-11-16.md:521-533 já registra HTTP sintético da senha e sonda; reutilizar tests/auth/rei73-password-http-isolated.test.py, sem rerun nesta rodada. Não considerar teste de UI/guard prova de persistência atômica.

## Cadastro: preservação implementada e limites

REI66 Em revisão. Comentário e8790f74 (16/11) relata tests/api-security/student-existing-account.test.py e admin-student-cpf-lock.test.py PASS, commit funcionald00eaf5e ancestral e deploy086db6b histórico. Document root real não comprovado naquela revisão. Main atual121-126 recusa alterar CPF existente, inclusive conta sem perfil; portanto descrição histórica de tomada de conta não é comportamento atual deste handler.

Hash calculado também para existentes117 reduz diferença óbvia, mas comentário do código115-116 rejeita garantia de tempo constante. Mutex GET_LOCK por CPF coordena escritores aderentes; não prova todos escritores nem índices efetivos. Cadastro ignora falha de INSERT IGNORE do catálogo perfis162-167 e alocação de setor178-193; vínculo ESTUDANTE principal169-175 não está no catch opcional. Política de tolerância de setor precisa ser avaliada antes de chamar cadastro incompleto de defeito. RELEASE_LOCK222-225 não confere valor retornado; risco de liberação deve ser demonstrado em cenário isolado, não extrapolado para produção. Sem rate limit explícito no handler; ausência de controles externos não foi comprovada. Rotas públicas de cadastro não devem receber perfil interno por analogia.

## Exports: autorização por página, whitelist e conteúdo

InternalListExportHandlers.php:34-74 tem whitelist de21 chaves, não include arbitrário por page. Abrange feriados/usuários, Cartório/Gabinete/Audiências, consultas de partes/processos/instituições/vínculos e estudapauta. _common35 avalia a mesma política da página: nome internal-list-export não significa exclusão automática de perfis externos autorizados a uma página específica. Perfis efetivos e aliases/supressão ADMIN são aplicados. Além da permissão de página, cada handler pode aplicar política de dados; não auditei integralmente os21 ramos nesta rodada.

Exemplo confirmado: instituicoesPublicas844-877 exige filtro nome e usa InstituicoesConsultaPolicy::onlyActiveForUser em860; consultor externo recebe filtro ativo. Misto segue política interna. Decisão de leitura interna de processo sigiloso sem gate setorial permanece aplicável às consultas autorizadas; não presumir mascaramento uniforme para todo export nem inventar setor responsável para leitura.

MAX_ROWS=5000 e CHUNK_200 existem em InternalListExportHandlers28-29; não declarar teto universal: CEJUSC chama listAll333, e fluxos variam por ramo. Recurso/memória/truncamento e comunicação de limite requerem revisão por ramo, fora da cobertura atual.

ListExportHelper.php16-51: nome de arquivo saneado, BOM, CSV ponto-e-vírgula, labels/textos passam csvText; somente valores int/float dispensam proteção. ListExportPresentation.php14-21 prefixa apóstrofo para controles ou início de fórmula (=,+,@,-), inclusive espaços iniciais. Isso é mecanismo observado, não prova de interoperabilidade em todos importadores. PDF59-85 escapa células/cabeçalhos com htmlspecialchars e isRemoteEnabled=false; não certifica ausência de todo recurso local nem conteúdo correto.

PDF endpoint devolve RuntimeException::getMessage diretamente17; hipótese de detalhe técnico deve ser conciliada REI67, sem alegar segredo vazado. CSV trata Throwable genericamente. Ambos podem consultar PDO antes de negar page não autorizado (_common32-36), porém não fazem dispatch de dados antes da negação; falha de conexão não demonstra bypass.

## Evidências históricas e conciliação

Além de66/155, reaproveitar recorte institucional da REI73/#482 e tests/instituicoes-publicas/export-endpoints-isolado.test.py. Artefato versionado não significa PASS nesta base e nesta rodada; o teste é de recorte de export, não todos21 ramos. Matriz versionada:560 confirma105 cenários de boundary após merge#482; não transportar esse número como contagem de exports. Testes export/csv-formula.test.php e csv-libreoffice.test.py também apenas inventariados. Sem tentativa de abrir LibreOffice, PDF ou browser.

Fontes Linear lidas: https://linear.app/reis-developer/issue/REI-66 e https://linear.app/reis-developer/issue/REI-155 (descrição e último comentário, histórico não paginado integralmente). Não reabri issue nem executei novamente trabalho de senha/export já coberto. A proteção de email_hint atual deve ser registrada como tombstone, não API de enumeração ativa.

## Próxima cobertura e conclusão

Lacunas: completar matriz por21 ramos de export/perfil/filtros/sigilo/limites; distinguir estudante e consultor/misto; conferir falha PDF sem detalhe técnico. Para senha, preservar evidência155 e executar futuramente concorrência/reset completo apenas em ambiente sintético autorizado. Para cadastro, engines/constraints e controles externos reais continuam não demonstrados por leitura; não classificar infraestrutura indisponível sem tentativa.

Cobertura estática dos seis arquivos completa, helpers transitivos parcial. Nenhuma validação executável nova ou fechamento REI73. Sem alteração de produto/Linear/PR/dados.
