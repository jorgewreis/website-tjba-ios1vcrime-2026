# SGC - IOS1VCRIME

## DOCUMENTAÇÃO INICIAL

### DOCUMENTO DE ESCOPO

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Introdução

Este documento tem como finalidade definir, de forma estruturada e tecnicamente fundamentada, o escopo do sistema web destinado à Primeira Vara Crime do Fórum de Ilhéus, tanto do ponto de vista de produto (o que o sistema será quando completo) quanto do ponto de vista de projeto (o que será executado em cada fase, iniciando pelo Projeto 2026.01).

O conteúdo aqui descrito se apoia diretamente nos documentos de **Objetivos e Público-Alvo**, **Funcionalidades das Áreas Pública e Restrita** e no **Documento de Arquitetura de Software (SAD)**, que estabelecem, respectivamente, a visão conceitual, o modelo funcional e as decisões técnicas centrais. Este documento complementa esses artefatos definindo limites, entregas, não-escopo, premissas e restrições, servindo como referência para planejamento, priorização e controle de mudanças.

---

## 2. Escopo de Produto (Visão do Sistema Completo)

O escopo de produto descreve o conjunto de capacidades e características que o sistema deverá possuir quando estiver plenamente implementado, independentemente das fases de execução. Ele representa a visão-alvo do webapp, tal como fundamentado nos documentos de objetivos, público-alvo e funcionalidades.

### 2.1 Objetivo geral e abrangência funcional

O sistema tem como objetivo geral prover uma plataforma institucional e operacional integrada para a Primeira Vara Crime, centralizando:

- a divulgação de informações públicas (canal institucional oficial, consultas permitidas, conteúdo informativo); e  
- o gerenciamento das rotinas administrativas e judiciais internas (Cartório, Gabinete, Audiências, Tarefas, Produtividade, Logs).

Essa abrangência cobre desde a experiência do cidadão e de usuários externos até o trabalho diário de servidores, chefias e estagiários, com foco em segurança, rastreabilidade, padronização de fluxos e geração de indicadores de produtividade.

### 2.2 Áreas do sistema

O produto final é organizado em duas grandes áreas:

- **Área Pública (Externa)**  
  - Canal institucional e de serviço ao público externo.  
  - Conteúdos informativos: apresentação da Vara, atribuições, manuais públicos, comunicados e avisos com histórico.  
  - Consultas públicas: processos com dados públicos e resumidos, estatísticas gerais agregadas, exibição de marcos institucionais.  
  - Audiências e participação acadêmica: listagem de audiências públicas, inscrição e cancelamento por estudantes, exibição de quantidade de inscritos, painéis informativos ao vivo, download de atas públicas e acesso a manuais de conduta.  
  - Regra central: nenhuma tramitação processual ou dado sigiloso é exposto na área pública; casos protegidos por sigilo ou LGPD são mascarados ou bloqueados integralmente.

- **Área Restrita (Interna)**  
  - Ambiente de trabalho oficial para usuários internos autenticados, com rastreabilidade compatível com o módulo e com a instrumentação operacional existente.  
  - Autenticação, gestão de sessão, perfis e permissões, acesso a manuais internos.  
  - Núcleo operacional, com módulos para Cartório, Gabinete, Audiências, Tarefas, Notificações, Produtividade, Logs e Configurações Gerais.  
  - Regras transversais: segurança reforçada, sigilo, LGPD, feedback padronizado, pontuação e produtividade, versionamento e governança de regras e parâmetros.

### 2.3 Principais módulos internos

O escopo de produto contempla, como visão-alvo, os seguintes módulos e submódulos internos (resumo):

- **Módulo m1 — Audiências**
  - Cadastro e gestão de audiências.
  - Cadastro de partes.
  - Geração de PDF de preparo.
  - Organização por status.
  - Vínculo com inscrições de estudantes.
  - Controle de presença.
  - Disponibilização de atas públicas.
  - Regra crítica: tarefas de oitiva/interrogatório são baixadas automaticamente ao concluir audiência.

- **Módulo m3 — Tarefas e Fluxos Internos**
  - Catálogo de tipos de tarefa.
  - Sub-tarefas configuráveis.
  - Regras de pontuação parametrizadas.
  - Exclusão controlada (hard/soft delete).

- **Módulo m4 — Cartório**  
  - Regras transversais:
    - Sub-tarefas obrigatórias.
    - Encerramento automático ou manual com justificativa.
    - Atribuição de usuários.
    - Pontuação obrigatória.
    - Feedback por toast.
  - Submódulo Cartas Recebidas: conferência inicial, expedição de mandado, certificação, PDF, devolução à origem, arquivamento (com regra especial vinculada à audiência).  
  - Submódulo Cartas Expedidas: expedir carta, gerar PDF, enviar, ofício, juntadas.  
  - Submódulo Editais: expedição, publicação, certificação, encerramento.  
  - Submódulo Guias (Execução Penal): cálculo de dias-multa, expedição, PDF, envio, juntadas, certificação.  
  - Submódulo Ofícios do Cartório: expedir, enviar (quando permitido), juntar comprovante.  
  - Submódulo CEJUSC: envio de decisão, juntada de comprovantes, encerramento de curso.

- **Módulo m5 — Notificações**
  - Geração automática.
  - Filtros, prioridades.
  - Marcação de leitura.
  - Rastreabilidade.

- **Módulo m6 — Produtividade e Painéis**
  - Produtividade individual.
  - Metas.
  - Estatísticas agregadas.
  - Visão administrativa e individual.

- **Módulo m11 — Logs, Auditoria e Segurança**
  - Registro de autenticação, acessos negados, alterações de regras e eventos operacionais.
  - Estratégia híbrida de observabilidade, com mascaramento de dados sensíveis e possibilidade de relatórios administrativos conforme a instrumentação disponível.

- **Módulo m12 — Configurações Gerais do Sistema**
  - Prazos, metas, tipos, comarcas, notificações.
  - Governança por admin/chefia, com versionamento e histórico de alterações.

- **Módulo — Gabinete**
  - Submódulos de Réus Presos, Processos Prioritários, Ofícios do Gabinete e Monitoramento Eletrônico.

---

## 3. Escopo Técnico de Produto (Tecnologias e Arquitetura)

O escopo técnico é definido pelo Documento de Arquitetura de Software (SAD), que estabelece as escolhas tecnológicas e o padrão arquitetural que devem ser seguidos em todo o ciclo de vida do sistema.

### 3.1 Stack tecnológica principal

- Backend: PHP customizado.
- Frontend: HTML, CSS e JavaScript, sem framework obrigatório.
- Banco de dados: MySQL.
- Servidor web: Nginx (com PHP-FPM).
- Autenticação: sessão PHP com validação server-side, token persistido em hash no banco, timeout de inatividade e reset por link com expiração.
- E-mail: SMTP.
- Logs/observabilidade: `error_log`, logs do servidor e integrações externas opcionais quando adotadas.
- Storage de arquivos: disco local do servidor.
- Cache e filas: inicialmente não utilizados para reduzir complexidade e custo.
- Containers: Docker para desenvolvimento, sem exigência em produção.

### 3.2 Padrão arquitetural

- Arquitetura em camadas:
  - Apresentação (rotas, controllers, views, validação de entrada, guards/policies).
  - Aplicação (serviços, casos de uso, orquestração).
  - Domínio (regras de negócio, entidades, invariantes).
  - Infraestrutura (persistência, integrações, IO).
- Adoção de boas práticas de codificação:
  - PSR-12 e padrões internos do projeto.
  - Controllers finos, com regra de negócio concentrada em camadas de aplicação/domínio.
  - Persistência via PDO, repositórios e SQL parametrizado, evitando SQL concatenado manualmente.
  - Validação server-side consistente e sanitização centralizada.
  - Logs com padrão único, mascaramento de dados sensíveis e tratamento de erros padronizado.

### 3.3 Integrações externas previstas

- API do CNJ (futura): integração HTTP, com contratos a definir, para consulta/registro de dados institucionais.
- SMTP: envio de e-mails operacionais e notificações.
- WhatsApp (futuro): uso de API oficial para envio de mensagens e alertas.
- Serviços externos de observabilidade: opcionais e futuros, se houver necessidade operacional e adoção formal.

---

## 4. Escopo do Projeto 2026.01 (Fase Inicial)

O Projeto 2026.01 corresponde à fase inicial de implantação, com foco em **documentação formal** e **preparo de infraestrutura**, sem a exigência de disponibilizar o conjunto completo de funcionalidades em produção para uso rotineiro.

### 4.1 Objetivo da fase

- Consolidar os fundamentos conceituais, funcionais e arquiteturais do sistema em documentos oficiais.  
- Estabelecer a base de infraestrutura (servidor, CloudPanel, Cloudflare, DNS, HTTPS e logging operacional compatível com o ambiente) necessária para suportar as fases de desenvolvimento.  
- Garantir rastreabilidade e governança desde o início, evitando decisões ad hoc sem registro.

### 4.2 Entregas previstas na fase 2026.01

1. **Documentos conceituais e funcionais**
   - Documento de Objetivos e Público-Alvo do WebApp.
   - Documento de Funcionalidades das Áreas Pública e Restrita.
   - Documento de Escopo (este documento).

2. **Documentos técnicos**
   - Documento de Arquitetura de Software (SAD).
   - Documento de Integrações Externas e APIs.
   - Organização de estrutura de pastas de documentação (`/docs`) e definição de padrões de versionamento e releases.

3. **Infraestrutura e ambientes**
   - Configuração do servidor de hospedagem (Hostinger + CloudPanel) com Nginx, PHP, MySQL e ambiente pronto para receber o código do sistema.
   - Ajustes mínimos no Cloudflare (DNS, proxy, HTTPS) para o domínio/subdomínios associados ao sistema.
   - Configuração inicial de logs de aplicação e operação em mecanismo compatível com o ambiente vigente, com padrão mínimo de severidade e contexto.
   - Documentação dos ambientes (Dev, Homolog, Prod), com URLs, parâmetros de configuração e características (como uso de banco único, flags de ambiente e mitigação de riscos).

4. **Governança inicial de código e versionamento**
   - Definição da estratégia de branches (`main`, `develop`, features).
   - Definição de convenções de commit e arquivos de controle (`COMMITS.md`, `VERSION`, `CHANGELOG.md`, `docs/releases/`).
   - Inclusão de README técnico orientando o setup mínimo para desenvolvimento local.

---

## 5. Itens Fora do Escopo da Fase 2026.01

Para manter o foco na fundação do projeto, são explicitamente considerados **fora do escopo desta fase**:

- Implementação completa e pronta para uso dos módulos funcionais descritos em **Funcionalidades das Áreas Pública e Restrita**, incluindo:
  - Módulo de Audiências (m1) em regime de produção.
  - Módulo de Tarefas (m3), Cartório (m4), Notificações (m5), Produtividade (m6), Logs/Auditoria (m11), Configurações (m12) e Gabinete em uso operacional pleno.
- Disponibilização oficial do sistema para o público externo em ambiente de produção.
- Desenvolvimento de aplicativo mobile nativo ou híbrido.
- Integração em produção com API do CNJ (a fase 2026.01 limita-se ao desenho e documentação da integração).
- Integração em produção com API de WhatsApp em cenários reais de notificação.
- Construção de painéis avançados de BI ou data warehouse analítico.
- Migração massiva de dados legados para o sistema.
- Treinamento formal estruturado para todos os perfis de usuários finais (além de orientações derivadas da documentação).

Esses itens permanecem dentro do **escopo de produto**, mas serão tratados em fases subsequentes, com planejamento e escopos específicos.

---

## 6. Premissas e Restrições

### 6.1 Premissas

- Os documentos de Objetivos, Público-Alvo, Funcionalidades e Arquitetura são aceitos como referência principal para decisões de escopo.
- A infraestrutura mínima (servidor, CloudPanel, Cloudflare, AWS) estará disponível para configuração dentro do período planejado da fase 2026.01.
- Ajustes relevantes de escopo nesta fase serão incorporados aos documentos por meio de versão atualizada, mantendo rastreabilidade.

### 6.2 Restrições

- **Custos de infraestrutura:** serviços de terceiros devem priorizar uso de faixas gratuitas e planos de baixo custo, conforme documentado no SAD.
- **Complexidade técnica:** cache e filas permanecem desativados nesta fase, para reduzir complexidade operacional e riscos em um ambiente ainda em consolidação.
- **Banco de dados único entre ambientes:** o uso de um único banco exige mitigação de riscos (roles restritas, flags de ambiente, bloqueio de rotas perigosas fora de produção), conforme já indicado no SAD.

---

## 7. Relação com Outros Documentos

O Documento de Escopo se relaciona diretamente com:

- **Objetivos e Público-Alvo:** define o “porquê” e “para quem” o sistema é construído, base conceitual do escopo de produto.
- **Funcionalidades das Áreas Pública e Restrita:** define o “o quê” o sistema deve fazer em termos de módulos, submódulos e regras.
- **Documento de Arquitetura de Software (SAD):** define o “como” técnico, tecnologias, camadas e integrações.
- **Documento de Integrações Externas e APIs:** especializa a parte de integrações do escopo técnico (internas e externas).

Todos esses artefatos devem ser mantidos coerentes entre si; alterações relevantes em um deles podem demandar revisões neste documento de escopo.

---

## 8. Conclusão

O escopo aqui descrito formaliza, de forma integrada, a visão de produto e os limites da fase inicial do projeto, oferecendo uma base clara para planejamento, execução e evolução do webapp da Primeira Vara Crime. Ao separar explicitamente o que pertence à visão completa do sistema e o que será tratado no Projeto 2026.01, reduz-se o risco de expansão não controlada de demandas e aumenta-se a previsibilidade das próximas etapas.

Este documento deve ser utilizado como referência obrigatória para o gerenciamento de escopo, definição de cronogramas, priorização de funcionalidades e avaliação de impactos de mudanças. Fases futuras do projeto poderão ser descritas em suplementos ou versões posteriores deste documento, mantendo a rastreabilidade das decisões e a aderência aos objetivos institucionais e técnicos estabelecidos.

**Fim do documento.**
