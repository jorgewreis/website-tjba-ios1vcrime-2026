# DOCUMENTAÇÃO TÉCNICA DE INTEGRAÇÕES EXTERNAS E APIS

**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Visão geral

Este documento estabelece a visão técnica, os princípios de projeto e as diretrizes de implementação para o uso de integrações externas e APIs no sistema web voltado à gestão de rotinas internas, contemplando Cartório, Gabinete, Audiências e participação acadêmica. O propósito é padronizar como o sistema se comunica com serviços de terceiros e com sua própria API interna, garantindo segurança, rastreabilidade, previsibilidade e aderência às normas institucionais e legais.

As integrações descritas abrangem tanto serviços externos (como API do CNJ, serviço de e-mail via SMTP, serviço de mensagens via WhatsApp e serviço de logs em nuvem) quanto o consumo interno da própria API do sistema, utilizada pelo frontend e por clientes autorizados. A partir deste documento devem ser derivados artefatos complementares, como contratos de API, esquemas de payload, mapeamento de códigos de erro, estratégias de versionamento, planos de teste e planos de implantação específicos para cada integração.

---

## 2. Princípios e diretrizes para integrações

As integrações externas devem seguir princípios técnicos e organizacionais alinhados à arquitetura em camadas do sistema, às exigências de segurança institucional, à LGPD e às normas de qualidade de software adotadas no projeto. Esses princípios orientam decisões de design de API, encapsulamento de dependências, tratamento de erros e observabilidade.

Do ponto de vista arquitetural, toda integração com serviços externos é responsabilidade da camada de infraestrutura, sendo consumida por serviços e casos de uso da camada de aplicação. Controllers e views não devem realizar chamadas diretas a APIs externas, o que favorece o isolamento, facilita substituição de provedores, aumenta a testabilidade e reduz acoplamento entre o domínio interno e tecnologias específicas de terceiros.

### 2.1 Princípios de segurança e conformidade

As integrações devem minimizar a exposição de dados, reduzir a superfície de ataque e garantir rastreabilidade das operações. Nenhuma chamada a serviço externo pode ser implementada sem autenticação adequada, sem tratamento de erros consistente e sem logs mínimos de auditoria, especialmente quando houver dados pessoais, processuais ou informações classificadas como sensíveis.

Em conformidade com a LGPD, toda manipulação de dados pessoais em integrações deve observar os princípios de finalidade, adequação, necessidade e segurança. Devem ser privilegiados identificadores opacos ou chaves técnicas em detrimento de dados diretamente identificáveis. O compartilhamento de dados sensíveis com APIs externas exige análise prévia de impacto e validação institucional, evitando o trânsito de informações além do estritamente necessário para o cumprimento da finalidade declarada.

### 2.2 Princípios de arquitetura e isolamento

Cada integração deve ser encapsulada em componentes específicos na camada de infraestrutura (clients, services ou adapters), expondo interfaces orientadas ao domínio para a camada de aplicação. Esses componentes são responsáveis por traduzir protocolos, formatos de payload, códigos de erro e particularidades do provedor para modelos internos estáveis e coerentes com o vocabulário do sistema.

Bibliotecas ou SDKs de terceiros utilizados para comunicação com serviços externos devem ser confinados à camada de infraestrutura, sem vazamento para as camadas de domínio e aplicação. Em cenários de troca de provedor (por exemplo, alteração do serviço de envio de mensagens), as mudanças devem concentrar-se nesses adapters e nas configurações de ambiente, preservando o restante do código e a estabilidade dos casos de uso.

### 2.3 Princípios de performance e resiliência

As integrações devem considerar latência, limites de uso (rate limits), disponibilidade do serviço externo e impacto na experiência do usuário interno e externo. Sempre que possível, operações que dependem de APIs externas não devem bloquear fluxos críticos de navegação, a menos que a resposta seja essencial para a conclusão da ação iniciada pelo usuário.

A resiliência deve ser endereçada por meio de timeouts adequados, políticas de retry com backoff exponencial, tratamento explícito de falhas e utilização de mecanismos de fallback quando aplicável. Falhas em serviços externos não podem comprometer a integridade dos dados internos; erros devem ser tratados com mensagens adequadas, registro detalhado em logs e, em evoluções futuras, com filas de reprocessamento para operações que permitam tentativa posterior.

---

## 3. Cenários de integração previstos na arquitetura

A arquitetura do sistema contempla explicitamente alguns pontos de integração com serviços externos, documentados na camada de infraestrutura. Os cenários principais são: API institucional do Conselho Nacional de Justiça, serviço de e-mail via SMTP, serviço de mensagens (WhatsApp) e serviço de observabilidade em nuvem para centralização de logs.

Cada integração possui papel específico no ecossistema da aplicação e deve respeitar as restrições de custo, de segurança e de governança estabelecidas. Este documento descreve esses cenários em alto nível; detalhes como endpoints, modelos de payload, códigos de status e contratos específicos devem ser detalhados em documentos de API dedicados.

### 3.1 Integração com API do CNJ

A integração com API do CNJ é classificada como integração institucional de alta criticidade, pois lida com informações diretamente vinculadas à atividade jurisdicional. Seu uso pode incluir consultas, atualização de informações e outras operações que venham a ser autorizadas em requisitos específicos ou normativas próprias do órgão.

Tecnicamente, a comunicação é estabelecida via HTTP sobre HTTPS, com endpoints, mecanismos de autenticação, escopos de acesso e limites de uso definidos pelo provedor. Toda requisição à API do CNJ deve ser registrada em log de forma mascarada, contendo identificadores internos da operação, usuário responsável (quando houver), tipo de ação, status da resposta e timestamps de envio e retorno. Dados sensíveis não devem ser registrados em log em claro, devendo ser substituídos por identificadores ou versões mascaradas.

### 3.2 Integração com serviço de e-mail (SMTP)

A integração via SMTP é utilizada para envio de comunicações operacionais, notificações internas e mensagens que complementam fluxos do sistema, como alertas de prazos, confirmações de inscrição acadêmica, avisos administrativos e confirmação de determinadas ações internas. Não deve ser tratada como canal para envio de conteúdo sensível sem mecanismos adicionais de proteção.

Em ambientes de desenvolvimento e homologação, recomenda-se fortemente o uso de destinatários de teste ou ferramentas de captura de e-mails (mailtrap, dev inbox etc.), a fim de evitar disparo acidental para destinatários reais. Os parâmetros de conexão (host, porta, usuário, senha, segurança TLS/SSL) devem ser fornecidos via variáveis de ambiente específicas por contexto (dev, homolog, prod). Erros de envio precisam ser registrados, incluindo motivo retornado pelo servidor SMTP e contexto da operação que disparou a mensagem, possibilitando reprocessamento quando necessário.

### 3.3 Integração com serviço de mensagens (WhatsApp)

A integração com serviço de mensagens via WhatsApp tem como finalidade o envio de notificações e alertas complementares a usuários internos ou externos, funcionando como canal paralelo às notificações internas do sistema e ao e-mail. Em termos de governança, há preferência por uso da API oficial do provedor, de forma a garantir aderência às políticas da plataforma, previsibilidade de comportamento e suporte técnico.

No ambiente de homologação, deve ser adotado um sandbox ou número específico para testes, isolando o fluxo de mensagens de usuários reais. Os fluxos de negócio que fizerem uso de WhatsApp devem ser desenhados de forma que falhas na integração ou indisponibilidade do serviço não interrompam o processo principal; nesse caso, a mensagem pode ser tratada como opcional ou substituída por outro canal de comunicação. Informações sobre mensagens enviadas (horário, tipo, status de envio, destinatário mascarado) devem ser registradas para controle e auditoria.

### 3.4 Observabilidade e integração com serviços de logs

No estado atual do projeto, a observabilidade oficial é híbrida e prioriza mecanismos já disponíveis no ambiente, como `error_log`, logs do servidor web e registros operacionais gerados pela própria aplicação. Serviços externos de centralização em nuvem podem ser adotados futuramente, mas não devem ser descritos como dependência obrigatória da implementação atual.

Independentemente do destino final do log, devem existir padrões mínimos de formatação, níveis de severidade (info, warning, error, critical) e campos obrigatórios, como contexto da operação, IDs internos de entidades relevantes, perfil do usuário que disparou a ação e ambiente (dev, homolog, prod). Antes do envio ou persistência, os dados sensíveis devem ser mascarados ou excluídos do payload.

---

## 4. API interna do sistema

Além das integrações com serviços externos, a solução expõe uma API interna consumida principalmente pelo frontend e por páginas autenticadas do próprio sistema. Essa API acompanha o modelo oficial de autenticação da aplicação e deve ser documentada, testada e mantida com o mesmo rigor aplicado às integrações externas.

A API interna é responsável por disponibilizar, em formato programático, funcionalidades como gestão de audiências, subtarefas das rotinas existentes, notificações, painéis de produtividade, perfis e configurações. A forma como esses recursos são expostos deve considerar tanto a estabilidade a longo prazo quanto a capacidade de evolução controlada.

### 4.1 Estrutura atual de endpoints e diretriz de evolução

No estado atual do projeto, a API interna **não utiliza prefixo de versionamento em URL** como `/api/v1/...`.

A superfície oficial vigente é:

- `public/api/` como base dos endpoints internos do sistema;
- `public/api/admin/` para grupos administrativos que já seguem essa superfície;
- `public/admin/api/` apenas para endpoints auxiliares legados ainda consumidos pelo shell autenticado, como verificação de token e leitura de perfis.

Além dessas áreas, existe uma pasta `api/` na raiz do repositório contendo scripts antigos (`users.php`, `guia.php`, `documents.php`, `notifications.php`, `guia_pdf.php`). Essa árvore **não é canônica**, não deve ser tratada como contrato oficial e não deve receber novas evoluções sem refatoração planejada.

Os endpoints oficiais atuais estão organizados por domínio funcional, com grupos como:

- cartório (`cartorio-cartas-expedidas`, `cartorio-cartas-recebidas`, `cartorio-guias`, `cartorio-oficios`, `cartorio-editais`);
- gabinete (`gabinete-reus-presos`, `gabinete-oficios`, `gabinete-monitoramento-eletronico`);
- audiências (`sala-audiencias-pauta`, `sala-audiencias-ata`);
- entidades auxiliares (`pessoas`, `instituicoes-publicas`, `processos`, `processos-partes`, `consulta-processos`, `datajud`);
- administração (`public/api/admin/feriados`).

Como diretriz de evolução, novas mudanças incompatíveis **podem** futuramente adotar versionamento explícito, mas isso ainda não faz parte da implementação atual. Até que exista uma decisão formal de migração, a documentação deve refletir o contrato real sem inventar uma versão em URL que o sistema ainda não expõe.

### 4.2 Autenticação da API interna

O modelo oficial atual da API interna acompanha o restante da aplicação:

- autenticação baseada em sessão PHP para navegação e chamadas autenticadas do frontend;
- validação server-side do usuário autenticado;
- confirmação do token persistido no banco de dados quando o fluxo exigir revalidação da sessão;
- timeout de inatividade de 60 minutos no contexto autenticado;
- cookies de sessão configurados no bootstrap central com flags de segurança;
- proteção CSRF nos fluxos que alteram estado;
- rate limit no login e em rotas sensíveis quando aplicável.

Não há, neste momento, um contrato oficial de API interna baseado em JWT como mecanismo principal de autenticação. Qualquer uso legado ou experimental de JWT deve ser tratado como não canônico até decisão arquitetural formal.

### 4.3 Padronização de respostas e tratamento de erros

Respostas da API interna devem seguir um padrão estruturado e previsível, tanto em casos de sucesso quanto de erro. Em respostas bem-sucedidas, é recomendável retornar um objeto contendo dados, metadados (por exemplo, paginação, totais) e, quando aplicável, links para ações relacionadas ou recursos correlatos.

Em situações de erro, devem ser utilizados códigos HTTP adequados (4xx para falhas de cliente, 5xx para falhas de servidor), acompanhados de um código interno de erro e de uma mensagem técnica objetiva, adequada ao público desenvolvedor. Detalhes internos da infraestrutura e stack traces não podem ser expostos em ambiente de produção; em vez disso, devem ser registrados em log e substituídos na resposta por uma mensagem genérica, preservando a segurança sem comprometer a capacidade de suporte.

---

## 5. Segurança em integrações e APIs

A segurança é eixo transversal no desenho das integrações externas e da API interna, considerando o contexto institucional, a presença de dados pessoais e processuais e a necessidade de rastreabilidade adequada. Os mecanismos de segurança incluem autenticação forte, autorização granular, criptografia em trânsito, proteção de credenciais, mascaramento de dados e políticas rígidas de acesso administrativo.

Além dos controles técnicos, devem existir regras de governança sobre quem pode configurar integrações, gerar chaves, acessar logs e alterar parâmetros sensíveis do sistema. Perfis privilegiados devem ser minimamente necessários e suas ações precisam ser registradas de forma detalhada para permitir rastreabilidade, investigação de incidentes e responsabilização quando necessário.

### 5.1 Autorização por perfil e escopo

O controle de acesso a endpoints da API interna e a funcionalidades que disparam integrações externas deve respeitar a matriz de perfis do sistema (como perfis de chefia, servidores, gabinete e perfis acadêmicos). A autorização deve ser aplicada tanto na camada de apresentação (guards, policies) quanto na camada de aplicação e domínio, garantindo que decisões críticas de permissão não dependam apenas da interface.

Para acessos autenticados à API interna, as permissões devem ser verificadas no backend com base no usuário autenticado, seus perfis e suas regras de acesso por página, setor ou funcionalidade. Em integrações com serviços externos que exijam credenciais, estas nunca devem ser expostas ao usuário final ou trafegadas para o frontend.

### 5.2 Criptografia em trânsito e proteção de credenciais

Toda comunicação que envolva dados sensíveis ou operações autenticadas deve utilizar HTTPS com certificados válidos e configurações atualizadas de protocolo e cifra. Conexões inseguras, especialmente em ambiente de produção, devem ser bloqueadas tanto para o frontend quanto para a comunicação com serviços externos.

Credenciais de integrações (tokens, chaves de API, senhas de SMTP, segredos de provedor de mensagens e parâmetros sensíveis em geral) devem ser armazenadas exclusivamente em variáveis de ambiente ou mecanismos equivalentes de gerenciamento de segredos, nunca em código-fonte, scripts de deploy ou repositórios compartilhados. O acesso a essas informações deve ser restrito, com trilha de auditoria para operações de alteração, rotação ou revogação de credenciais.

### 5.3 Mascaramento, anonimização e LGPD

A conformidade com a LGPD exige adoção sistemática de práticas de minimização, mascaramento e, quando pertinente, anonimização de dados. Isso vale tanto para integrações externas quanto para a API interna, logs e relatórios derivados. As integrações devem transportar somente os campos estritamente necessários para o propósito do fluxo, evitando envio redundante de dados identificáveis.

Nos mecanismos de logging, em especial os integrados a serviços em nuvem, dados como CPF, nomes, e-mails, números de telefone, detalhes de processos sigilosos e outras informações sensíveis devem ser mascarados ou substituídos por identificadores opacos. Em cenários de análise estatística ou pesquisa, a exportação de dados deve considerar técnicas de pseudonimização e controles de acesso adicionais, evitando reidentificação indevida de titulares.

---

## 6. Observabilidade e auditoria em integrações

A observabilidade das integrações externas e da API interna é requisito essencial para o suporte operacional, a identificação de incidentes e a análise de desempenho. Isso implica em registrar, de forma estruturada, eventos relevantes, métricas de uso e erros, permitindo visão clara sobre o comportamento dos componentes críticos e serviços dependentes.

Além da observabilidade técnica, as integrações devem contribuir para a trilha de auditoria institucional, registrando quem acionou determinadas operações, em que contexto e com qual resultado. Essa trilha é especialmente importante em operações que envolvam atos oficiais, comunicações para partes, tarefas de cartório e manipulação de dados sensíveis.

### 6.1 Padrões de logging para integrações

Logs relacionados a integrações devem conter, no mínimo, tipo de integração (e-mail, WhatsApp, CNJ, serviço de logs, API interna), recurso ou endpoint acessado, identificador interno da operação (por exemplo, ID de audiência, ID de tarefa, ID de documento), timestamps de início e fim da chamada, status da resposta e tempo de execução. Quando aplicável, deve ser registrado também o identificador do usuário interno que desencadeou a operação.

Os logs não devem armazenar payloads completos com dados em claro, principalmente quando contiverem informações pessoais ou processuais sigilosas. Em caso de erro, a mensagem retornada pelo provedor pode ser registrada desde que não contenha dados sensíveis ou credenciais; caso contrário, deve ser sanitizada. A padronização da estrutura de logs é fundamental para viabilizar consultas, dashboards e alertas automáticos.

### 6.2 Métricas, indicadores e monitoramento

Além de logs pontuais, devem ser definidos indicadores agregados para as integrações e para a API interna, como volume de requisições por período, taxa de sucesso e de erro, latência média, distribuição de códigos de status e número de mensagens enviadas por canal. Esses indicadores servem como base para monitoramento da saúde do sistema, detecção de anomalias e decisões sobre melhorias de capacidade ou otimização de chamadas.

O monitoramento deve diferenciar ambientes, permitindo analítica separada para desenvolvimento, homologação e produção. Quando possível, devem ser configurados alertas automáticos (por exemplo, via e-mail ou painel de observabilidade) para situações como elevação brusca de erros, lentidão excessiva em chamadas críticas ou indisponibilidade de serviços externos, facilitando resposta rápida da equipe técnica.

---

## 7. Ambientes, configuração e testes de integração

As integrações externas e a API interna se comportam de forma distinta em cada ambiente (desenvolvimento, homologação e produção), exigindo configurações próprias e políticas de testes adequadas. A padronização da configuração por ambiente reduz riscos de uso indevido de serviços de produção em cenários de teste e aumenta a previsibilidade no processo de deploy.

Do ponto de vista de qualidade, todas as integrações e endpoints relevantes devem ser cobertos por uma estratégia de testes que contemple desde testes unitários e de integração até testes de regressão e, quando necessário, testes de carga em rotas críticas. A consistência entre ambiente de homologação e produção é um fator chave para reduzir incidentes pós-implantação.

### 7.1 Configuração por ambiente

Cada integração deve possuir parâmetros de configuração específicos para desenvolvimento, homologação e produção, incluindo URLs base, chaves de API, credenciais e modos de operação (real, sandbox, mock). Em ambiente de desenvolvimento é aceitável usar simuladores ou mocks estáveis, enquanto em homologação recomenda-se aproximar-se ao máximo do comportamento real, ainda mantendo isolamento de dados e destinatários.

No ambiente de produção, alterações de configuração devem seguir processos formais de mudança, com registros de quem alterou, quando e por qual motivo, preferencialmente em janelas planejadas para evitar impacto em horário crítico. Em arquiteturas onde o banco de dados seja compartilhado entre ambientes, é fundamental garantir que integrações de teste não impactem processos ou destinatários reais, adotando flags de ambiente e validações adicionais em pontos sensíveis.

### 7.2 Estratégia de testes para integrações e API

A estratégia de testes deve contemplar cenários de sucesso, de falha e de comportamentos não ideais (como timeouts ou degradação de performance). Nos testes unitários, clients e adapters de integração podem ser exercitados com uso de mocks ou stubs, garantindo que a lógica de aplicação seja validada independentemente de serviços externos. Nos testes de integração, quando possível, devem ser utilizados ambientes de sandbox ou endpoints de teste fornecidos pelos provedores.

A API interna, por sua vez, deve ser testada funcionalmente (incluindo fluxos de negócio típicos, regras de validação, paginação e filtros) e do ponto de vista de segurança (autenticação, autorização, bloqueio de acessos indevidos, tratamento de inputs malformados). Mudanças em contratos de API, seja em integrações externas ou na API interna, devem ser acompanhadas pela atualização de casos de teste e documentação, reduzindo a probabilidade de regressões.

---

## 8. Diretrizes de evolução e governança das integrações

As integrações externas e a API interna são elementos dinâmicos, sujeitos à evolução de requisitos, mudanças de provedores, novas políticas de segurança e adequações decorrentes de normas institucionais ou legais. Por isso, é necessário estabelecer um modelo de governança que defina como alterações são propostas, avaliadas, aprovadas, implementadas e comunicadas.

A governança deve considerar tanto impactos técnicos quanto impactos operacionais, uma vez que ajustes em integrações frequentemente afetam rotinas de trabalho, comunicação com partes, geração de documentos e painéis gerenciais. Mudanças relevantes não podem ser tratadas como ajustes isolados de código; devem ser planejadas, testadas e implantadas de forma coordenada com as demais áreas envolvidas.

### 8.1 Processo para inclusão de novas integrações

A inclusão de novas integrações com serviços externos deve iniciar com análise de necessidade e aderência ao escopo institucional. Essa análise deve identificar claramente o problema a ser resolvido, os benefícios esperados, os dados envolvidos, os riscos de segurança e privacidade, além de estimar esforço técnico, custo recorrente e complexidade de manutenção.

Uma vez aprovada, a nova integração deve ser incorporada à arquitetura por meio de documentação específica (descrição do serviço, contratos de API, requisitos de autenticação, políticas de uso, estratégias de teste e plano de implantação). Quando a integração envolver dados pessoais ou sensíveis, recomenda-se elaboração de relatório de impacto em privacidade, alinhado a boas práticas de proteção de dados e exigências normativas.

### 8.2 Versionamento e descontinuação de integrações

Sempre que uma integração exigir mudança incompatível com a versão atual (por exemplo, alteração de contrato por parte do provedor ou migração para serviço alternativo), deve ser adotada estratégia de versionamento que permita a convivência temporária de versões antiga e nova, com período de transição bem definido. Para a API interna, isso significa que uma futura adoção de `/api/v1/...` ou namespace equivalente deve ser tratada como mudança arquitetural planejada, e não presumida como já existente.

A descontinuação de integrações antigas deve ser precedida de monitoramento que comprove ausência de uso ativo ou residual. Após a desativação, credenciais associadas devem ser revogadas, configurações removidas ou arquivadas com segurança, e a documentação técnica atualizada para refletir o estado real do ambiente. O histórico de decisões de descontinuação deve ser preservado, permitindo rastrear motivos, datas e impactos observados.

---

**Fim do documento.**
