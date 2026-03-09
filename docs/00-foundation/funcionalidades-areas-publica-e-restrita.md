 # SGC - IOS1VCRIME

## DOCUMENTAÇÃO INICIAL

### FUNCIONALIDADES DAS ÁREAS PÚBLICA E RESTRITA

**Projeto:** Dev WebApp – Sistema de Gestão do Cartório da Primeira Vara Crime de Ilhéus  
**Documento desenvolvido por:** Reis Developer  
**Direitos:** Todos os direitos reservados a Jorge Wanderley Reis de Menezes  

---

## 1. Introdução

Este documento descreve, de forma técnico-descritiva, as funcionalidades do sistema, organizadas pela separação arquitetural e institucional entre **Área Pública (Externa)** e **Área Restrita (Interna)**. O objetivo é estabelecer um referencial funcional único e consistente, capaz de sustentar a criação dos demais artefatos do projeto, como requisitos funcionais e não funcionais, backlog técnico, regras de permissão, documentação de integração, desenho de banco de dados, testes e normas internas.

A separação de áreas não é apenas uma divisão de interface, mas uma regra de governança e segurança. A Área Pública concentra conteúdos informativos e interações externas permitidas, enquanto a Área Restrita concentra a execução e gestão operacional do Cartório e do Gabinete, com autenticação, perfis, controle de permissões, logs e trilhas de auditoria.

Este sistema é o canal oficial digital da Primeira Vara Crime. Embora projetado para operar com foco na Primeira Vara Crime, existe a possibilidade futura de outras varas consultarem dados específicos, estritamente limitados a funcionalidades de consulta autorizadas, sem acesso a conteúdos sigilosos ou fluxos operacionais internos.

---

## 2. Visão geral do sistema

O sistema é estruturado em módulos e submódulos, com fluxo híbrido entre público e restrito. Atividades externas podem iniciar processos (ex.: inscrição de estudantes), enquanto validação, acompanhamento, registro e encerramento ocorrem no ambiente interno, sob controle de perfis autorizados.

### 2.1 Organização por áreas, módulos e submódulos

- **Área Pública (Externa):**  
  Foco institucional e de atendimento ao público, com recursos informativos, consultas públicas permitidas e fluxos acadêmicos, sempre com regras de mascaramento e bloqueio para casos sigilosos.

- **Área Restrita (Interna):**  
  Núcleo operacional com módulos de Cartório e Gabinete, cadastro e acompanhamento de audiências, controle de tarefas e sub-tarefas, expedições, produtividade e administração do sistema.

### 2.2 Regras gerais e configurações globais do sistema

Regras transversais aplicáveis a todos os módulos:

- Segurança e perfis com autenticação obrigatória  
- Sigilo e LGPD com controle rigoroso de exposição  
- Feedback por toast em todas as ações  
- Pontuação e produtividade para toda ação relevante  
- Versionamento e governança de regras e parâmetros  

### 2.3 Configurações administráveis (admin/chefia)

- parâmetros de prazos  
- tipos e categorias operacionais  
- notificações  
- regras de sigilo e mascaramento  
- metas de produtividade  
- gestão de documentos públicos  
- regras por comarcas e tribunais  

Todas as alterações geram logs, histórico de versões e possibilidade de rollback.

---

## 3. Área Pública

### 3.1 Finalidade e limites do ambiente público

A Área Pública é o canal institucional e de serviço ao público externo. Não permite tramitação processual nem exposição de dados protegidos por sigilo ou LGPD.

### 3.2 Conteúdo institucional e informativo

Conteúdo esperado:

- apresentação oficial da Vara  
- atribuições gerais e orientações  
- manuais públicos e regras de conduta  
- comunicados e avisos com histórico  

### 3.3 Consultas públicas permitidas

Funcionalidades previstas:

- consulta de processos (dados públicos e resumidos)  
- estatísticas gerais agregadas  
- exibição de marcos institucionais  
- exibição de marcos institucionais  

Processos sigilosos devem ser bloqueados ou mascarados integralmente.

### 3.4 Audiências e participação acadêmica na área pública

Funcionalidades:

- listagem de audiências públicas  
- inscrição de estudantes  
- cancelamento de inscrição  
- exibição de quantidade de inscritos  
- painéis informativos ao vivo  
- download de atas públicas  
- acesso a manuais de conduta  

Regras obrigatórias:

- audiências sigilosas não permitem inscrição  
- mascaramento de dados sensíveis

---

## 4. Área Restrita

### 4.1 Finalidade e funcionamento do ambiente restrito

Ambiente de trabalho oficial para usuários internos autenticados, com rastreabilidade total das ações e auditoria obrigatória.

### 4.2 Autenticação, contas e funcionalidades de acesso

Inclui:

- login e recuperação de senha  
- gestão de sessão  
- permissões por perfil  
- acesso a manuais internos  

### 4.3 Módulos e submódulos internos

---

### 4.4 Módulo m1 — Audiências

Funcionalidades:

- cadastro e gestão de audiências  
- cadastro de partes  
- geração de PDF de preparo  
- organização por status  
- vínculo com inscrições de estudantes  
- controle de presença  
- disponibilização de atas públicas  

Regra crítica:

- tarefas de oitiva/interrogatório são baixadas automaticamente ao concluir audiência.

---

### 4.5 Módulo m3 — Tarefas e Fluxos Internos

- catálogo de tipos de tarefa  
- sub-tarefas configuráveis  
- regras de pontuação parametrizadas  
- exclusão controlada (hard/soft delete)

---

### 4.6 Módulo m4 — Cartório

#### 4.6.1 Regras transversais

- sub-tarefas obrigatórias  
- encerramento automático ou manual com justificativa  
- atribuição de usuários  
- pontuação obrigatória  
- feedback por toast

#### 4.6.2 Submódulo — Cartas Recebidas

Sub-tarefas padrão:

- conferência inicial  
- expedição de mandado  
- certificação  
- geração de PDF  
- devolução à origem  
- arquivamento

Regra especial para oitiva/interrogatório vinculada à audiência.

#### 4.6.3 Submódulo — Cartas Expedidas

Etapas:

- expedir carta  
- gerar PDF  
- enviar  
- ofício  
- juntadas

#### 4.6.4 Submódulo — Editais

- expedição  
- publicação  
- certificação  
- encerramento

#### 4.6.5 Submódulo — Guias (Execução Penal)

- cálculo de dias-multa  
- expedição  
- PDF  
- envio  
- juntadas  
- certificação

#### 4.6.6 Submódulo — Ofícios do Cartório

- expedir  
- enviar (quando permitido)  
- juntar comprovante  

#### 4.6.7 Submódulo — Medidas Protetivas

- cadastro inicial do processo, classe automática, réu e vítimas  
- vinculação de partes ao processo com papéis e polos distintos  
- cadastro de partes no PJe  
- expedição de mandados de intimação  
- integração automática com CEJUSC  
- registro e consulta de prioridades processuais por vítima

#### 4.6.8 Submódulo — CEJUSC

- envio de decisão  
- juntada de comprovantes  
- encerramento de curso

---

### 4.7 Módulo m5 — Notificações

- geração automática  
- filtros  
- prioridades  
- marcação de leitura  
- rastreabilidade

---

### 4.8 Módulo m6 — Produtividade e Painéis

- produtividade individual  
- metas  
- estatísticas agregadas  
- visão administrativa e individual

---

### 4.9 Módulo m11 — Logs, Auditoria e Segurança

- autenticação  
- acessos negados  
- alterações de regras  
- eventos operacionais

Logs são imutáveis e exportáveis.

---

### 4.10 Módulo m12 — Configurações Gerais do Sistema

- prazos  
- metas  
- tipos  
- comarcas  
- notificações

Governança por admin e chefia, com versionamento.

---

### 4.11 Módulo — Gabinete

Submódulos:

- Réus Presos  
- Processos Prioritários  
- Ofícios do Gabinete  
- Monitoramento Eletrônico

---

## 5. Observações finais

Este documento consolida o escopo funcional completo das Áreas Pública e Restrita, servindo como referência primária para requisitos, backlog, banco de dados e testes.

Diretrizes permanentes:

- separação rigorosa entre público e restrito  
- bloqueio total de informações sigilosas  
- rastreabilidade total  
- parametrização e versionamento  
- padronização por tarefas e sub-tarefas

**Fim do documento.**
