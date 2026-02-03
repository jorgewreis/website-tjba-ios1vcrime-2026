# SGC - IOS1VCRIME  

## DOCUMENTAÇÃO INICIAL  

### PLANO DE GERENCIAMENTO DO CRONOGRAMA

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Identificação e Contexto do Cronograma

O presente Plano de Gerenciamento do Cronograma refere-se ao projeto **SGC – Sistema de Gerenciamento do Cartório da Primeira Vara Criminal de Ilhéus**, iniciativa voltada ao desenvolvimento de um sistema web institucional e operacional destinado a apoiar, organizar e otimizar as rotinas administrativas, processuais e de controle interno do Cartório e setores correlatos da Primeira Vara Criminal.

Este documento estabelece as diretrizes formais para o planejamento, acompanhamento, controle e atualização do cronograma do projeto, assegurando previsibilidade, rastreabilidade das entregas e alinhamento contínuo entre escopo, prazo, qualidade e segurança.

O projeto teve início formal em **01 de fevereiro de 2026**, data a partir da qual passaram a ser consideradas válidas todas as atividades de planejamento, definição de requisitos, estruturação técnica e organização do trabalho. A data-alvo para disponibilização do sistema em ambiente de produção (**Go-Live**) foi definida como **01 de maio de 2026**, estabelecendo um horizonte temporal total de aproximadamente três meses para execução completa do ciclo de desenvolvimento, testes, validação e implantação.

A responsabilidade formal pelo planejamento, execução e controle do cronograma é atribuída a **Jorge Reis**, atuando como Desenvolvedor Fullstack e responsável técnico pelo projeto, concentrando as funções de gestão do tempo, coordenação das atividades técnicas, validação das entregas e ajuste contínuo do planejamento.

O cronograma do projeto foi concebido para operar sob uma abordagem iterativa e incremental, estruturada em **sprints semanais**, com ciclos de trabalho claramente delimitados e revisão sistemática ao final de cada semana, sempre aos domingos.

Este plano não substitui as ferramentas operacionais de acompanhamento — como o **GitHub Projects** —, mas atua como referência normativa e documental, definindo como o cronograma é estruturado, mantido e governado ao longo do projeto.

---

## 2. Objetivo do Gerenciamento do Cronograma

O objetivo do Gerenciamento do Cronograma neste projeto é estabelecer uma estrutura temporal clara, técnica e executável que permita o desenvolvimento contínuo do SGC – Sistema de Gerenciamento do Cartório, desde as etapas iniciais de documentação e preparação de infraestrutura até a consolidação das funcionalidades essenciais em ambiente de produção.

O cronograma foi organizado para refletir uma ordem lógica de dependência técnica, priorizando documentação mínima obrigatória, configuração de serviços, servidores, DNS, certificados, repositórios e ambientes, antes do desenvolvimento funcional visível.

O gerenciamento do cronograma também tem como finalidade garantir que as funcionalidades sejam entregues em estado funcional e seguro desde as primeiras sprints, priorizando a existência de um núcleo operacional estável com autenticação, controle de usuários, segregação de áreas e segurança ativa.

---

## 3. Estrutura Geral do Roadmap do Cronograma

O roadmap do cronograma foi estruturado com duas camadas complementares:

- **Visão mensal simplificada**, voltada ao entendimento macro da evolução do projeto;
- **Descrição semanal detalhada**, orientando diretamente a execução técnica.

O primeiro mês concentra-se na consolidação das bases estruturais.  
O segundo mês dedica-se à construção funcional do sistema.  
O terceiro mês foca em validação, ajustes, testes integrados e implantação definitiva.

---

## 4. Regras Gerais das Sprints Semanais

O desenvolvimento será conduzido por meio de **sprints semanais**, com duração fixa de uma semana, iniciando-se na segunda-feira e encerrando-se no domingo, reservado para revisão técnica.

Regras fundamentais:

- Nenhuma funcionalidade é iniciada sem base estrutural estabilizada;
- Cada sprint deve resultar em entregas técnicas funcionais;
- Segurança é requisito transversal obrigatório desde a primeira sprint;
- Alterações são permitidas desde que não comprometam milestones;
- Atividades não concluídas devem ser replanejadas explicitamente.

---

## 5. Cronograma Semanal Detalhado

### Semana 1 — 01/02/2026 a 07/02/2026

**Objetivo técnico:**  
Criar a fundação do projeto.

**Atividades previstas:**

- Criação do repositório
- Documentação base
- Definição de versionamento
- Estrutura de pastas
- Planejamento de segurança

**Entregáveis:**

- Repositório estruturado
- Documentação mínima criada
- Base técnica pronta

**Critério de conclusão:**  
Projeto tecnicamente pronto para iniciar desenvolvimento.

---

### Semana 2 — 08/02/2026 a 14/02/2026

**Objetivo técnico:**  
Implantação da infraestrutura de produção.

**Atividades:**

- VPS Hostinger
- Domínio, DNS e SSL
- PHP, banco de dados
- Segurança de base

**Critério de conclusão:**  
Ambiente de produção funcional e seguro.

---

### Semana 3 — 15/02/2026 a 21/02/2026

**Objetivo técnico:**  
Implementação do core técnico (MVC).

**Atividades:**

- Bootstrap da aplicação
- Roteamento central
- Middlewares
- Tratamento de erros

---

### Semana 4 — 22/02/2026 a 28/02/2026

**Objetivo técnico:**  
Autenticação, sessão e usuários.

**Atividades:**

- Login/logout
- Expiração de sessão
- Gestão mínima de usuários
- Logs administrativos

---

### Semana 5 — 01/03/2026 a 07/03/2026

**Objetivo técnico:**  
Área pública inicial.

**Atividades:**

- Página institucional
- Consulta pública de audiências
- Aplicação de sigilo

---

### Semana 6 — 08/03/2026 a 14/03/2026

**Objetivo técnico:**  
Inscrição de estudantes e sala de espera (TV).

---

### Semana 7 — 15/03/2026 a 21/03/2026

**Objetivo técnico:**  
Dashboard interno e módulo de audiências.

---

### Semana 8 — 22/03/2026 a 28/03/2026

**Objetivo técnico:**  
Atas de audiência e refinamento do painel.

---

### Semana 9 — 29/03/2026 a 04/04/2026

**Objetivo técnico:**  
Módulos administrativos do cartório.

---

### Semana 10 — 05/04/2026 a 11/04/2026

**Objetivo técnico:**  
Produtividade e automações.

---

### Semana 11 — 12/04/2026 a 18/04/2026

**Objetivo técnico:**  
Testes integrados, performance e usabilidade.

---

### Semana 12 — 19/04/2026 a 25/04/2026

**Objetivo técnico:**  
Implantação gradual, uso assistido e Go-Live.

---

**Fim do documento.**
