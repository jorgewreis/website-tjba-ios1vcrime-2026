# SGC - IOS1VCRIME

## DOCUMENTAÇÃO INICIAL

### DOCUMENTO DE ESCOPO DO PROJETO 2026.01

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Introdução

O presente documento tem como finalidade definir, de forma clara e tecnicamente estruturada, o escopo do **Projeto 2026.01**, primeira fase formal de implantação do webapp destinado à Primeira Vara Crime do Fórum de Ilhéus. Esta fase não tem como objetivo imediato a disponibilização integral do sistema em produção para uso cotidiano, mas sim a consolidação dos fundamentos documentais, arquiteturais e de infraestrutura necessários para as etapas subsequentes de desenvolvimento e implantação.

O documento de escopo estabelece os limites do trabalho a ser realizado neste ciclo inicial, definindo explicitamente o que será entregue, o que será preparado para o futuro e o que está fora do escopo desta fase. Ele se apoia diretamente nos documentos de **Objetivos e Público-Alvo**, **Funcionalidades das Áreas Pública e Restrita** e no **Documento de Arquitetura de Software**, que descrevem a visão de produto, os módulos planejados e as decisões tecnológicas de alto nível.

---

## 2. Visão geral do produto (escopo de produto)

Este webapp tem como objetivo final prover uma plataforma institucional e operacional integrada para a Primeira Vara Crime, centralizando, em um único ambiente, a divulgação de informações públicas e a gestão das rotinas internas de Cartório e Gabinete. O sistema será organizado em **Área Pública (Externa)** e **Área Restrita (Interna)**, com separação rigorosa de perfis, permissões e exposição de dados.

A visão de produto contempla, de forma ampla, os seguintes elementos:

- **Área Pública (Externa):**  
  - Conteúdo institucional, manuais públicos, avisos e comunicados.  
  - Consultas públicas permitidas (processos com dados públicos, estatísticas agregadas, marcos institucionais).  
  - Módulos de audiências e participação acadêmica, incluindo listagem de audiências públicas, inscrição e cancelamento por estudantes e painéis públicos em tempo real.

- **Área Restrita (Interna):**  
  - Autenticação, perfis, permissões, logs e auditoria.  
  - Módulo de Audiências (m1), com cadastro, gestão, partes, PDFs de preparo, controle de presença e vínculo com inscrições de estudantes.  
  - Módulo de Tarefas e Fluxos Internos (m3), com catálogo de tarefas, sub-tarefas parametrizáveis e regras de pontuação.  
  - Módulo de Cartório (m4) e seus submódulos (Cartas Recebidas, Cartas Expedidas, Editais, Guias, Ofícios, Medidas Protetivas, CEJUSC).  
  - Módulo de Notificações (m5), Módulo de Produtividade e Painéis (m6), Módulo de Logs, Auditoria e Segurança (m11), Configurações Gerais (m12) e módulo de Gabinete.

Essa visão de produto define **o que o sistema será quando estiver completo**, mas não significa que todos esses módulos serão implementados no ciclo 2026.01. A fase atual tem caráter preparatório, com foco em documentação estruturada e infraestrutura básica.

---

## 3. Escopo do Projeto 2026.01

O escopo do **Projeto 2026.01** está voltado à criação de uma base sólida para o desenvolvimento futuro do sistema, concentrando-se em atividades de **documentação oficial**, **padronização arquitetural** e **configuração da infraestrutura técnica** necessária para suportar as próximas fases.

Esta fase não tem como objetivo “entregar o sistema pronto”, mas sim:

- consolidar e registrar, em documentos formais, a visão de negócio, o modelo funcional, a arquitetura técnica e as diretrizes de integrações e segurança;  
- preparar o ambiente de hospedagem, DNS, proteção e observabilidade (servidor, CloudPanel, Cloudflare, CloudWatch e correlatos);  
- estabelecer padrões que serão seguidos em todos os ciclos futuros (versionamento, ambientes, logging, segurança e qualidade de código).

### 3.1 Entregas principais do Projeto 2026.01

As entregas principais previstas para esta fase incluem, no mínimo:

1. **Conjunto de documentos conceituais e funcionais:**
   - Documento de Objetivos e Público-Alvo, definindo claramente o propósito do webapp e os perfis atendidos (internos e externos).
   - Documento de Funcionalidades das Áreas Pública e Restrita, descrevendo módulos, submódulos e regras gerais de funcionamento.
   - Documento de Escopo (este documento), delimitando o que será tratado na fase 2026.01 e o que permanece para ciclos futuros.

2. **Documentos técnicos de arquitetura e integrações:**
   - Documento de Arquitetura de Software (SAD), com definição de tecnologias, camadas, padrões de desenvolvimento, ambientes e decisões técnicas estruturais.
   - Documento de Integrações Externas e APIs, abrangendo API interna, SMTP, CloudWatch, futura API do CNJ, futura integração com WhatsApp e diretrizes gerais de segurança e observabilidade para integrações.

3. **Organização e padronização de repositório e versionamento:**
   - Estrutura mínima de pastas (`/docs`, `/src`, `/bd` ou equivalente) alinhada às diretrizes de arquitetura.
   - Definição de estratégia de branches, versionamento (`VERSION`, `CHANGELOG.md`, `docs/releases/`) e convenções de commit.
   - Inclusão dos documentos de escopo, objetivos, funcionalidades, arquitetura e integrações no repositório oficial do projeto.

4. **Configuração de infraestrutura básica:**
   - Configuração e validação do ambiente de hospedagem no servidor definido (Hostinger + CloudPanel), com Nginx, PHP, MySQL e estrutura base para o webapp.
   - Ajustes mínimos de DNS e proxy reverso por meio do Cloudflare, incluindo configuração de domínios/subdomínios e HTTPS.
   - Configuração inicial de logs e observabilidade com AWS CloudWatch, incluindo o pipeline básico para recepção de logs de aplicação.
   - Definição e registro, em documentação, das características dos ambientes Dev, Homolog e Prod, com suas respectivas URLs e parâmetros de configuração.

Essas entregas garantem que, ao término do Projeto 2026.01, o sistema ainda não esteja funcionalmente completo, mas já exista uma fundação técnica e documental sólida para desenvolvimento iterativo nas próximas fases.

---

## 4. Itens fora do escopo da fase 2026.01

Para manter o foco e evitar expansão indevida de trabalho, são explicitamente considerados **fora do escopo** deste ciclo inicial:

- Desenvolvimento completo dos módulos funcionais descritos em “Funcionalidades das Áreas Pública e Restrita” para uso em produção, incluindo:
  - Implementação completa do Módulo de Audiências (m1) com todas as telas, regras e relatórios.
  - Implementação completa do Módulo de Tarefas e Fluxos Internos (m3).
  - Implementação completa do Módulo de Cartório (m4) e seus submódulos.
  - Implementação completa dos módulos de Notificações (m5), Produtividade (m6), Logs/Auditoria (m11), Configurações (m12) e Gabinete.
- Disponibilização pública oficial do webapp para acesso externo em ambiente de produção com todas as funcionalidades ativas.
- Desenvolvimento de aplicativo mobile nativo ou híbrido específico para dispositivos móveis.
- Integrações automáticas em produção com:
  - API do CNJ em regime operacional (a fase 2026.01 se limita à **documentação** e ao desenho de como essa integração deverá ocorrer).
  - API de WhatsApp em cenários de envio massivo ou fluxos complexos de notificações.
- Mecanismos avançados de BI ou painéis analíticos externos (Power BI, Data Warehouse, etc.).
- Treinamento formal de usuários finais do Cartório e Gabinete, além de orientações indiretas que decorram dos documentos (manuais podem ser tratados em fases posteriores).
- Qualquer implantação que exija migração massiva de dados legados para o novo sistema.

A exclusão desses itens do escopo 2026.01 não significa que eles não serão executados, mas que serão objeto de planejamento e execução em **fases subsequentes**, baseadas na fundação estabelecida neste ciclo.

---

## 5. Premissas e restrições do Projeto 2026.01

### 5.1 Premissas

As seguintes premissas são adotadas como verdadeiras para este ciclo:

- Os documentos de **Objetivos e Público-Alvo**, **Funcionalidades das Áreas Pública e Restrita** e **Arquitetura de Software** servirão como referência principal para decisões de escopo e priorização.
- A infraestrutura base (servidor, painel de controle, DNS, Cloudflare, CloudWatch) estará disponível para configuração durante esta fase.
- Alterações significativas de escopo de produto serão tratadas como insumos para versões futuras dos documentos, não como reabertura ilimitada desta fase inicial.

### 5.2 Restrições

As principais restrições do Projeto 2026.01 são:

- **Restrição de custo:** as configurações de infraestrutura (Cloudflare, AWS CloudWatch, etc.) devem priorizar o uso de planos gratuitos ou de baixo custo, conforme definido no documento de arquitetura.
- **Restrição de tempo:** por se tratar de fase preparatória, o foco é documentação e infraestrutura básica, sem tentativa de desenvolvimento completo de módulos funcionais complexos.
- **Restrição tecnológica:** o stack descrito no Documento de Arquitetura deve ser seguido, com base em PHP customizado, MySQL, Nginx/PHP-FPM e frontend em HTML/CSS/JavaScript, salvo decisão formalmente registrada em documento de mudança arquitetural.

---

## 6. Relação com documentos correlatos

Este documento de escopo está diretamente vinculado e subordinado aos seguintes artefatos:

- **Documento de Objetivos e Público-Alvo:** define o porquê do sistema e quais grupos serão atendidos, orientando o escopo de produto.
- **Documento de Funcionalidades das Áreas Pública e Restrita:** descreve o “o quê” será implementado em termos de funcionalidades, servindo como visão-alvo para módulos e fluxos.
- **Documento de Arquitetura de Software (SAD):** define o “como” o sistema será construído do ponto de vista técnico.
- **Documento de Integrações Externas e APIs:** estabelece os princípios e cenários de integração, ainda que a implementação plena ocorra em fases seguintes.

O Projeto 2026.01 tem como meta principal **consolidar e integrar esses documentos**, além de garantir que a infraestrutura mínima esteja pronta para suportar os ciclos de desenvolvimento subsequentes.

---

## 7. Conclusão

O escopo aqui definido posiciona o **Projeto 2026.01** como uma fase de fundação, em que a ênfase recai sobre **documentação, arquitetura e infraestrutura**, e não sobre a entrega final do produto para uso operacional pleno. Essa abordagem reduz riscos, evita decisões apressadas de implementação e cria um ambiente controlado para evolução gradual do sistema.

A partir deste escopo, os próximos ciclos de projeto poderão ser planejados como fases incrementais de desenvolvimento funcional, sempre ancoradas na documentação produzida neste ciclo e nas configurações de infraestrutura estabelecidas. Alterações futuras de escopo deverão ser registradas em documentos complementares ou versões posteriores deste documento, preservando a rastreabilidade das decisões.

**Fim do documento.**
