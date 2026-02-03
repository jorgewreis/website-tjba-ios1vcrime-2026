# Especificação de Controle de Acesso e Permissões por Setor (RBAC)

## 1. Visão Geral

Este documento define a política de controle de acesso baseada em papéis (RBAC - Role-Based Access Control) e as restrições de visibilidade de dados por setor para o sistema. O objetivo é garantir a integridade, confidencialidade e disponibilidade das informações, assegurando que cada usuário tenha acesso estritamente necessário para o desempenho de suas funções (Princípio do Menor Privilégio).

A arquitetura de segurança do sistema deve validar as permissões em duas camadas:

1.  **Nível de Aplicação (Frontend):** Ocultação de elementos de interface (botões, menus, links) não autorizados.
2.  **Nível de API/Servidor (Backend):** Validação rigorosa de sessão e perfil antes de processar qualquer requisição de leitura ou escrita.

## 2. Definição de Setores e Escopo de Dados

O sistema opera sob uma lógica de isolamento de dados por setor. Embora existam perfis hierárquicos, a visibilidade dos dados deve respeitar a lotação do usuário.

### 2.1. Setores Mapeados

- **Cartório:** Responsável pela tramitação processual, expedição de documentos e cumprimento de ordens judiciais.
- **Gabinete:** Responsável pela análise jurídica, minutas de decisões e sentenças.
- **Audiências:** Setor dedicado à realização e registro de audiências.
- **Externo:** Ambiente público ou restrito para interação com a sociedade e partes interessadas.

### 2.2. Regra de Isolamento (Tenancy por Setor)

- **Princípio da Não-Transversalidade:** Um usuário lotado em um setor (ex: Cartório) não deve ter acesso de visualização ou edição a dados restritos de outro setor (ex: Gabinete), salvo se possuir um perfil administrativo global.
- **Escopo de Visualização:** Usuários dos perfis **Chefia** e **Servidor** visualizam todos os processos dentro do seu respectivo setor de lotação.

---

## 3. Matriz de Perfis e Permissões

Abaixo estão detalhadas as capacidades operacionais de cada perfil dentro do sistema.

### 3.1. Perfil: ADMIN (Administrador do Sistema)

Perfil técnico com acesso irrestrito a todas as funcionalidades do sistema, focado na manutenção e gestão da infraestrutura lógica.

- **Escopo:** Global (Todos os setores).
- **Permissões:**
  - Gerenciamento total de usuários (criar, editar, inativar, desbloquear).
  - Configuração de parâmetros do sistema.
  - Acesso a logs de auditoria e segurança.
  - Não atua no fluxo processual de negócio (não movimenta processos), mas possui permissão técnica para tal em casos de suporte.

### 3.2. Perfil: CHEFIA

Perfil de gestão operacional dentro de um setor específico. Possui a autoridade máxima sobre os dados e a equipe daquele setor.

- **Escopo:** Setorial (Restrito ao setor de lotação).
- **Permissões de Leitura:** Visualização completa de todos os processos e tarefas do setor.
- **Permissões de Escrita:**
  - **Inclusão:** Criar novos processos, tarefas e movimentações.
  - **Edição:** Alterar dados de processos e tarefas existentes.
  - **Exclusão:** Permissão exclusiva para excluir movimentações, processos ou tarefas errôneas.
  - **Gestão de Pessoas:** Alocar usuários em tarefas e **realocar** usuários (alterar a responsabilidade de uma tarefa de um servidor para outro).

### 3.3. Perfil: SERVIDOR

Perfil operacional padrão para execução das atividades diárias do setor.

- **Escopo:** Setorial (Restrito ao setor de lotação).
- **Permissões de Leitura:** Visualização completa de todos os processos e tarefas do setor.
- **Permissões de Escrita:**
  - **Inclusão:** Criar novos processos, tarefas e movimentações.
  - **Edição:** Editar informações de tarefas e processos aos quais está em andamento pelo usuário.
  - **Alocação:** Pode alocar um usuário (ou a si mesmo) a uma tarefa disponível.
- **Restrições:**
  - **Não pode excluir** registros (processos, movimentações ou tarefas).
  - **Não pode realocar** usuários (não pode retirar uma tarefa de outro servidor para passar a terceiro).

### 3.4. Perfil: ESTAGIÁRIO

Perfil de apoio operacional, com acesso similar ao do Servidor, mas com restrições de segurança em funcionalidades críticas.

- **Escopo:** Setorial (Interno da Vara).
- **Permissões:**
  - Acesso a movimentações processuais.
  - Inclusão e edição de dados básicos.
  - Autoalocação quando realiza tarefas.
- **Restrições Específicas:**
  - Bloqueio de acesso a links sensíveis (ex: relatórios gerenciais, configurações de setor).
  - Não possui permissão de exclusão.  
  - Não possui permissão de realocação de usuários.

### 3.5. Perfil: ESTUDANTE

Perfil externo voltado para aprendizado e acompanhamento público.

- **Escopo:** Externo (Limitado).
- **Permissões:**
  - **Visualização em Tempo Real:** Acesso ao painel de audiências em andamento (somente visualização).
  - **Auto-cadastro:** Permissão para se cadastrar no sistema para fins de assistir audiências com possibilidade de vinculação à audiências públicas.
  - **Downloads:** Baixar atas de audiências das quais participou efetivamente.
- **Restrições:** Não visualiza processos internos, dados de partes ou movimentações do cartório/gabinete.

### 3.6. Perfil: CONSULTOR EXTERNO

Perfil para consulta pontual de informações públicas ou autorizadas.

- **Escopo:** Externo.
- **Permissões:**
  - Estritamente limitado a **consultas de partes**.
- **Restrições:** Acesso somente leitura (Read-Only). Não realiza nenhuma operação de escrita, edição ou download de documentos restritos.

---

## 4. Tabela Resumo de Capacidades (CRUD)

| Funcionalidade / Perfil | Admin | Chefia | Servidor | Estagiário | Estudante | Consultor Ext. |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: |
| **Visualizar Processos** | Global | Setor | Setor | Setor | Não | Não |
| **Consultar Partes** | Sim | Sim | Sim | Sim | Não | Sim |
| **Incluir Processos** | Sim | Sim | Sim | Sim | Não | Não |
| **Editar Tarefas** | Sim | Sim | Sim | Próprias | Não | Não |
| **Excluir Dados** | Sim | **Sim** | Não | Não | Não | Não |
| **Alocar Usuário** | Sim | Sim | **Sim** | Próprio | Não | Não |
| **Realocar Usuário** | Sim | **Sim** | Não | Não | Não | Não |
| **Painel Audiências** | Sim | Sim | Sim | Sim | **Sim** | Não |
| **Baixar Atas Próprias** | N/A | N/A | N/A | N/A | **Sim** | Não |

---

## 5. Regras de Implementação Técnica

### 5.1. Validação de Rotas (Middleware)

Todas as rotas da API e do Frontend devem ser protegidas por um middleware de autorização que verifique:

1.  Se o token de sessão é válido.
2.  Se o `id_perfil` do usuário possui permissão para o recurso solicitado.
3.  Se o `id_setor` do usuário corresponde ao setor do dado solicitado (para perfis não-globais).

### 5.2. Interface Adaptativa

A interface do usuário (UI) deve ser renderizada condicionalmente:

- Botões de "Excluir" devem ser removidos do DOM para perfis **Servidor** e **Estagiário**.
- Menus de administração devem ser invisíveis para todos exceto **Admin**.
- O painel de "Realocação" deve estar desabilitado ou oculto para o perfil **Servidor**.

### 5.3. Auditoria de Ações

Ações críticas, especialmente as de **Exclusão** (realizadas pela Chefia) e **Edição** (realizadas por Servidores/Estagiários), devem gerar logs de auditoria contendo:

- ID do Usuário.
- Timestamp da ação.
- Tipo de ação (DELETE, UPDATE).
- Dados anteriores e dados novos (para updates).
