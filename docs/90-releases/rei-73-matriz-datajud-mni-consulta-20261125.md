# REI-73 — DataJud, MNI e Consulta Processos

Base6b47f8249fd6653585dd025cd6d3427e2baa141d; 25/11/2026 UTC. Inspeção estática e Linear somente leitura. Nenhum HTTP, consulta externa, teste, banco, credencial real, Docker, produto, PR ou Linear alterado. Apenas este relatório novo. Instruções jev-router/locais previamente consultadas; coordenação pela sessão principal.

## Escopo correto

São seis PHP: quatro handlers de negócio e dois arquivos de configuração retornados por require. Não contar config.php como endpoint JSON que imprime credenciais: ambos retornam arrays PHP, sem echo observado. Execução direta de configuração não foi testada, nem proteção do servidor contra exposição de fonte. Foram lidos integralmente os seis arquivos, DataJudService, ConsultaProcessosService e trechos de mappers/repositories/clientes; não toda integração transitiva MNI ou frontend de sincronização.

## Matriz

| Arquivo | Operação/autorização/método | Efeito, limites e objeto | Consumidor |
|---|---|---|---|
| public/api/datajud/config.php:7-52 | configuração; sem guard; sem resposta JSON explícita | Pode ler .env e carregar variáveis no processo PHP; retorna base_url/default_alias/chave/timeout. Somente fonte versionada lida, nunca .env. Não chama API externa. | search-processo.php:7 |
| public/api/datajud/search-processo.php:4-62 | consulta externa por numero JSON/POST; CORS*; sem sessão/perfil/whitelist de método/limitação por usuário explícitos | chave obrigatória9; CnjValidator25; DataJudService32; erros RuntimeException retornados35-43. Não há escrita SQL direta nesse caminho. | public/assets/js/modules/datajud/datajud-api.js:3-7; incluído por app/views/internal/_datajud_scripts.php:28 |
| public/api/mni/config.php:13-39 | configuração; sem guard; retorna array, não imprime | carrega MniCredentials local e template; retorna configured/missing_vars/version/host/timeouts/defaults. Não dispara consultar. Não afirmar leitura direta da rota revela credenciais. | referência em consultar-processo.php:37 está depois de exit e é inalcançável atualmente |
| public/api/mni/consultar-processo.php:22-30 | suspensão incondicional503,no-store para qualquer método/perfil | exit antes de Bootstrap/config/serviço; NÃO chama tribunal no caminho atual. Código posterior39-114 descreve sessão-ID, CNJ, consulta e truncamento200, mas é inalcançável. | public/assets/js/modules/mni/mni-api.js:36 opt-in; busca dirigida nas views não encontrou inclusão, sem prova de ausência de todo consumidor externo/dinâmico |
| public/api/consulta-processos/get.php:8-35 | ApiAccessGuard consultaprocessos,false; GET id; sem restrição explícita de método/origem |400 id ausente,404 desconhecido; ConsultaProcessosService47-61 busca registro/partes/classe/assuntos locais.500 genérico e log; conexão13 anterior ao try. | nenhuma referência literal ao endpoint encontrada na busca de produto; views podem chamar serviço diretamente |
| public/api/consulta-processos/list.php:8-51 | mesmo guard; query numero/numero_processo e parte_nome/nome |15por página, page>=1; serviço26-44 enriquece lista e count; ProcessosRepository184-245 usa filtro comum e count distinct.500 genérico/log, conexão anterior ao try. | nenhuma referência literal localizada; app/views/internal/consulta-processos-list.php existe e usa apresentação local |

## Perfis e sigilo local

PageAccessRegistry.php:103 e AccessEvaluator.php:15-45,94-95: consultaprocessos admite perfis compostos ESTAGIARIO/SERVIDOR de CARTORIO/GABINETE/AUDIENCIAS e elevados ADMIN/CHEFIA, com aliases DIRETORA/MAGISTRADA normalizados. Perfis efetivos aplicam supressão operacional ADMIN; externo puro não passa. Não equivale a qualquer usuário autenticado.

Decisão previamente recuperada para este projeto permite leitura interna autorizada de processos sigilosos sem gate de setor responsável. Get/list não filtram sigiloso nem recebem setor: isso é compatível com essa direção, não achado de vazamento por si. Não transportar política de estudante/consultor nem regra de mutação processo-parte para essas leituras. ConsultaProcessosService apenas chama operações de leitura nos trechos lidos; helper de apresentação não foi auditado transitivamente inteiro. Não alegar ausência de toda escrita apenas por nome do endpoint.

## DataJud: limites e semântica efetivos

- DataJudService.php:26-75 valida novamente CNJ, resolve alias e tenta até dois índices (resolvido e default quando distintos). POST cURL com APIKey do servidor e body Elasticsearch; size1,_source=true em104-118. Timeouts padrão configuráveis por tentativa 30s e conexão 10s em124-145, não quota global. Sem teto explícito de bytes de resposta no trecho. Nenhuma chamada foi realizada.
- Endpoint não permite URL arbitrária pelo payload; base/alias vêm de configuração/resolução. Não classificar SSRF baseado apenas em configuração administrável. Quotas, proxy/WAF e exposição real continuam desconhecidos.
- HTTP401/403 e>=500 geram erro;400/429 sem hits e corpo não JSON podem cair em fallback e resultado vazio em52-75. Hipótese/caminho estático já registrado REI108; não reprodução nesta rodada. Erro cURL pode chegar ao cliente via RuntimeException46-47 e handler43, relacionado REI67.
- DataJudResponseMapper.php:15-29 classifica nivelSigilo; ausência/null/vazio é não restrito. mapSourceToPayload36-64, quando restrito, remove polo_passivo e movimentos, mas mantém classe, assuntos, prioridades, órgão, datas e metadados. Portanto não dizer que oculta todo dado processual. Adequação desses campos depende do contrato aprovado; nenhum dado real foi consultado.
- mapEmptyPublicSearch73-94 retorna indisponivel_na_base_publica=true, nivel_sigilo=null e sigilo_restrito=false, com mensagem que admite sigilo OU indisponibilidade. Zero hits não prova sigilo nem inexistência; tampouco autoriza sobrescrever sigilo local. Preservar decisão de sigilo de processo existente nas mutações, reconciliando REI88.
- O cliente DataJud também declara URLs classe-read/classe-sync/assunto-sync (datajud-api.js11-27). Essas são operações distintas em /api/processos, fora destas seis rotas; o fato de search não escrever SQL não torna todo fluxo da UI somente leitura. Retentativas/timeouts no navegador não substituem rate limit do servidor.

## MNI: suspensão versus autorização entregue

Barreira503 precede toda carga de configuração/chamada do endpoint. REI81 permanece Em andamento: suspensão contém a superfície, não implementa autorização por processo e sigilo nem valida perfis. MniService.php49-73 possui cache local e chamada ao client com potencial retry e token cache; isso NÃO é alcançado por esta rota suspensa. Não foi feita auditoria global de outros chamadores MNI; nenhuma promessa de suspensão de toda integração por causa desse único endpoint.

Config.php acessado diretamente carrega metadados locais mesmo com consultar suspenso; não há consulta ao tribunal observada nesse arquivo. Sem concluir proteção global dos arquivos PHP ou efetividade de servidor/webroot.

## Reconciliação Linear e testes históricos

Consultadas descrições integrais e comentário mais recente de cada issue; histórico não paginado integralmente.

- [REI81](https://linear.app/reis-developer/issue/REI-81), Em andamento: comentário e32c2aa5 de16/11 registra deploya4960436, HTTP503/no-store e teste sintético da suspensão. Histórico, não execução atual. A base atual conserva exit22-30.
- [REI92](https://linear.app/reis-developer/issue/REI-92), A fazer: comentário ec02235a de24/11 23:27Z já confirma CORS*/ausência de guard/limite explícito em a506 e hipótese condicionada à infraestrutura. Esta matriz revalida fonte6b47, não cria achado novo. Decisão de publicidade e quotas continua pendente; não impor autenticação sem resolver consumidores/contrato.
- [REI108](https://linear.app/reis-developer/issue/REI-108), Em andamento: descrição registra hipótese400/429 e sincronização parcial; comentário12e84427 de20/11 relata HTTP integrado com MySQL descartável/DataJud fake, recusas/rollback/sucesso de candidata sem commit/CI remoto. Não atribuir comportamento dessa candidata ao search-processo da main: aqui400/429 ainda não são tratados como erro específico.
- [REI113](https://linear.app/reis-developer/issue/REI-113), A fazer, sem comentários retornados: comparação Advocacia/institucional requer ambos checkouts; esta rodada só leu institucional. Não alegar equivalência, isolamento por empresa ou superioridade do outro produto.
- REI67 para erros técnicos; REI88 para persistência de sigilo; REI76/153 para operações de sincronização distintas. Não alterar status nem criar duplicatas.

## Próxima cobertura sem rede real

1. Reusar e inspecionar isolamento de tests/mni/suspended-http.test.py, único teste MNI encontrado na busca nominal; não executado. Sentinela deve provar503 antes de config/cliente para múltiplos métodos, não só procurar texto exit.
2. DataJud fake: sucesso público/restrito, zero hits, corpo inválido,400/429/401/403/500 e timeout; contar chamadas/alias e distinguir indisponibilidade de ausência. Testar redaction por campo e não atualizar sigilo local por vazio.
3. Consulta local: HTTP sintético com guard real, perfis compostos três áreas/elevados/externos/revogados/mistos e ADMIN suprimido; processo sigiloso deve seguir política interna aprovada. Get404, paginação/filtros/count e erros com banco fake/descartável previamente isolado.
4. Restrições públicas/quotas de DataJud: confirmar contrato e controles externos por evidência autorizada; ausência de rate limit no handler não prova abuso/custo ou exposição pública final. Não consultar serviço real para demonstrar erro que stub cobre.

Conclusão: seis arquivos inventariados, quatro handlers diferenciados de dois includes. Sem teste novo ou serviço externo. MNI permanece contido; DataJud tem lacunas já conhecidas e condicionadas; leitura local protegida não deve receber gate setorial inventado. Auditoria integral e comparação entre produtos não concluídas.
