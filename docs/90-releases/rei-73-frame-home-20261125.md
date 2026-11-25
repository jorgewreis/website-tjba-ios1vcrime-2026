# REI-73 — roteamento de páginas `home.php` / `frame.php`

Base remota observada `c920af21421ede2f112830f79148a0e6c6d049c9`. `public/frame.php`, controles `app/access`, trechos de `home.php`, `.htaccess` rastreado e documentos `docs/access/{README,matriz-cartorio,matriz-gabinete,matriz-audiencias,matriz-globais}.md` foram consultados estaticamente. Comparação das fontes centrais com `abc3e083` não mostra diff. As views, serviços e infraestrutura não foram lidos integralmente. Não foram executados testes, HTTP, browser, banco nem alterados produto ou Linear.

## Fluxo alcançável

1. `home.php:17` exige `Security::requireAuthentication`; carrega usuário, perfis e setores, usa `UsuarioPerfisAutorizacao` para perfis efetivos :87, constrói menu e iframe :570. A escolha cliente/menu é navegação; não substitui autorização do servidor. Home escolhe o dashboard com perfis cadastrais, enquanto frame usa perfis efetivos e reconcilia o destino; a diferença, inclusive no modo ADMIN suprimido, não demonstra bypass. `home.php:708` chama endpoint específico de modo ADMIN; supressão efetiva pode modificar perfis.
2. `frame.php:14-17` lê `page`, aceita string após trim ou usa dashboard e exige autenticação antes do roteamento. O mapa literal contém 72 entradas: 67 chaves funcionais para 65 views e 5 chaves/views de erro. Essas contagens foram extraídas do mapa fixo, não provam que arquivos estejam disponíveis no deploy ou que cada view seja segura.
3. `frame.php:130-178` exige token, chama `Usuario::validarToken`, deriva/valida ID e rejeita chaves fora do mapa com 404; `consultapjepublicoteste` ainda depende da flag de ambiente :181-187. Após obter perfis efetivos, redireciona dashboard interno/externo conforme classificação :189-197 e avalia `AccessEvaluator::canAccessPage` :200. A permissão negativa gera 403; arquivo mapeado inexistente gera 500; `require $resolved` ocorre após o gate. Erros 401/403/404/500/503 são renderizados por `respondWithError`, mas a autenticação inicial pode terminar/redirecionar antes desse bloco; não garantir um status universal para sessão inválida.
4. As chaves são mapeamento estático; `realpath` resolve arquivo escolhido pela tabela e não aceita caminho arbitrário fornecido diretamente em `page`. Não foi testada manipulação de arquivos/symlink na implantação. APIs e outros entrypoints não herdam esse gate só porque o consumidor é uma view.

## Política de página observada

`PageAccessRegistry` e `AccessEvaluator`: `area_a` aceita composto SERVIDOR/ESTAGIARIO da área correspondente, além de elevados; `all_internal` e `consultas_*` aceitam composto interno, elevados são autorizados antes do switch; DIRETORA/MAGISTRADA normalizadas como CHEFIA. `gestao` aceita elevados pelo bypass anterior, não pelo switch. Dashboard aceita também certos perfis internos simples por exceção. ADMIN efetivo pode ser suprimido no modo operacional; perfis mistos são classificados por `UserAccessHelper`, não como externos automaticamente. As decisões reais dependem dos perfis obtidos da sessão.

Externo exclusivamente ESTUDANTE tem `estudapauta`, `estudapainel`, `alterardados` e dashboard externo; CONSULTOR externo tem `consultapartes`, `instituicoespublicas`, `partesinstituicoeslist`, `alterardados` e dashboard externo. O caso de perfil misto estudante/consultor sem vínculo interno pode combinar exceções. `estudaata` não é chave do mapa/política, conforme matriz de Atas e REI-99; não inferir que a tela de download seja acessível por esse nome.

| Grupo | Chaves e regra | Limite |
|---|---|---|
| Cartório | CEJUSC, editais, guias, ofícios e cartas: `area_a CARTORIO` | Registro e ações por API exigem análise própria. |
| Gabinete | ofícios e réus presos: `area_a GABINETE`; monitoramento é `all_internal` | Escopo por registro não é estabelecido pela chave. |
| Audiências | pauta, ata e painéis internos: `area_a AUDIENCIAS`; estudantes têm chaves específicas | Ata e elegibilidade têm regras adicionais no serviço/endpoint. |
| Consultas | processos/edição processo são `consultas_processos`; partes/instituições/lista vínculos são `consultas_partes`; criação/edição de partes/instituições usa Cartório | CONSULTOR externo só nas exceções listadas; leitura de processo sigiloso para interno autorizado não implica permissão de escrita setorial. |
| Gestão e globais | usuários e create/edit feriados `gestao`; dashboard, notificações, configurações, documentos, skills e list feriados `all_internal` | Endpoints de feriados usam gates literais próprios; não presumir equivalência exata da política da página. |

Aliases de mesmo arquivo observados: `salapauta` e `salapainel` → `sala-audiencias-pauta-list.php`; `reuspresos` e `reuspresossubstituto` → `gabinete-reus-presos-list.php`. Cada chave ainda é avaliada independentemente. Não são aliases de URLs arbitrárias nem evidência de paridade funcional integral.

## Entradas diretas e fronteiras externas

As views selecionadas estão em `app/views/internal`, fora de `public` no layout esperado. O `.htaccess` da raiz rastreado contém proteção específica para `.env`, `.git`, manifestos e `bd/`, mas não uma regra geral para `/app/`; sua eficácia depende de DocumentRoot, servidor e aplicação de regras. Não há prova aqui de exposição direta ou bloqueio em produção. A REI-140/documentação de deploy deve ser conciliada antes de executar qualquer sondagem; não presumir configuração do servidor. APIs, páginas públicas (`login.php`, `recupera_senha.php`, etc.) e scripts são superfícies independentes; sua presença fora de frame não demonstra falha sem examinar gate.

Em `frame.php`, obtenção de PDO :124 e validação de token precedem o bloco de erro normalizado sem `try` geral: erro de conexão/consulta pode escapar do fluxo `respondWithError`; não se observou resposta HTTP efetiva e exibição depende de configuração. `home.php` também faz consultas de perfis/setores; não foram examinados todos os catches nem logs. A autorização de página não prova estado do registro ou atomicidade de suas ações.

## Testes e evidências já existentes

- `tests/auth/http-auth-flow.test.php:47,64` referencia acesso a home e negação após logout; REI-124 registra CI isolado no merge histórico `cdf4ecf`, run 44728860169. Não foi reexecutado e o escopo exato do runner/servidor deve ser conferido antes de ampliar conclusão.
- `tests/instituicoes-publicas/access-policy.test.php` testa `AccessEvaluator`; outros `guard-simulated`, `http-boundary-audit` e `tests/processos-partes/access-guard.test.php` focam APIs. A matriz de bloqueios REI-73 :558 registra resultados sintéticos e limites; não substituem teste HTTP da navegação completa.
- `tests/ui/rei73-oficios-browser` usa fragmento de view e respostas interceptadas; não valida frame/login real. Não foi executado nesta leitura.

## Próximas evidências

Inventariar o contrato de todas as chaves/aliases e views diretamente alcançáveis; exercitar em ambiente sintético sessões anônima/expirada/revogada, perfis internos por área, externo/misto, ADMIN suprimido, IDs desconhecidos, chaves inválidas, flag PJe e resposta de erro com dependências controladas. Verificar correspondência menu/frame sem pressupor que a interface seja autorização. Conferir servidor/DocumentRoot somente em contexto autorizado; não aplicar correções ou inferir exposição com base em `.htaccess` isolado. Este relatório não demonstra cobertura integral de páginas, UI autenticada, infraestrutura ou proteção por objeto.

## Verificação HTTP sintética posterior

A coordenação executou fixture isolada na base c920af21: 20 verificações PASS (19 respostas e redirect), com frame, Security, Usuario e avaliadores reais; configuração, conexão e cinco views foram substituídas. SQLite em arquivo e sessões fabricadas, sem dados reais. Inclui internos, externos, mistos, token inválido/expirado, conta inativa, chaves inválidas e ADMIN com/sem flag de supressão. ADMIN cumulativo sem flag representa sessão fabricada e não demonstra o estado inicial do login. Revisão independente conferiu fontes e isolamento; resultado registrado em C:/Users/jorge/AppData/Local/Temp/rei73-frame-evidence-20261125/result.json e script com SHA256 9f272a1651e6b8ff5842f9bcb30cdc4ef4bc177eca6e32b636e27a166de171ba. Não cobre home, login real, browser, views reais, MySQL, proxy ou ausência de escrita nas recusas.
