# Especificação de Requisitos de Auditoria e Rastreabilidade

## 1. Introdução

Este documento define os requisitos para o subsistema de auditoria (logging) do WebApp | 1vCrimeIlheus. O objetivo é garantir que todas as ações críticas realizadas no sistema sejam registradas de forma imutável, permitindo a rastreabilidade de eventos para fins de segurança, conformidade legal e investigação forense.

## 2. Escopo de Auditoria

O sistema deve registrar obrigatoriamente os seguintes tipos de eventos:

### 2.1. Eventos de Segurança e Acesso

- **Autenticação:** Tentativas de login (sucesso e falha), logout e bloqueio de conta.
- **Gestão de Sessão:** Renovação de tokens e expiração de sessão.
- **Gestão de Usuários:** Criação, alteração de perfil, bloqueio/desbloqueio e redefinição de senha de usuários.

### 2.2. Eventos de Negócio (CRUD)

- **Criação:** Inclusão de novos processos, tarefas ou documentos.
- **Edição:** Alteração de dados em registros existentes (deve registrar o estado anterior e o novo).
- **Exclusão:** Remoção física ou lógica de qualquer registro (exclusivo para perfil Chefia).
- **Movimentação:** Mudança de fase ou status de um processo.
- **Alocação:** Atribuição ou realocação de tarefas entre servidores.

## 3. Estrutura do Registro de Log

Cada entrada de log deve conter, no mínimo, os seguintes metadados para garantir a completude da informação ("Quem, O Que, Onde, Quando e Como"):

- **Timestamp:** Data e hora exata do evento (UTC ou Horário de Brasília, padronizado).
- **Actor (Quem):** ID do usuário (`id_usuario`) e Perfil que realizou a ação.
- **Action (O Que):** Código ou descrição da ação (ex: `PROCESS_DELETE`, `USER_LOGIN_FAIL`).
- **Target (Alvo):** ID do recurso afetado (ex: `id_processo`, `id_tarefa`).
- **Source (Onde):** Endereço IP de origem da requisição e User-Agent do navegador.
- **Changes (Como):** Para edições, um objeto JSON contendo o "Antes" (`old_value`) e o "Depois" (`new_value`) dos campos alterados.

## 4. Armazenamento e Proteção

### 4.1. Persistência

- **Banco de Dados:** Os logs devem ser armazenados em uma tabela dedicada (ex: `auditoria_logs`) no MySQL.
- **Performance:** A gravação de logs deve ser assíncrona ou otimizada para não impactar o tempo de resposta das transações do usuário.

### 4.2. Imutabilidade e Segurança

- **Read-Only:** A tabela de logs não deve permitir operações de `UPDATE` ou `DELETE` pela aplicação principal. Apenas inserções (`INSERT`) são permitidas.
- **Acesso Restrito:** Apenas usuários com perfil **Admin** podem visualizar os relatórios de auditoria. Nenhuma Chefia ou Servidor deve ter acesso a esses registros.

## 5. Retenção e Expurgo

- **Período de Retenção:** Os logs de auditoria devem ser mantidos acessíveis online por um período mínimo de **5 anos**, conforme requisitos legais para sistemas judiciários.
- **Arquivamento:** Após o período de retenção online, os dados podem ser movidos para armazenamento frio (Cold Storage/S3 Glacier) para fins históricos, liberando espaço no banco de dados principal.

## 6. Requisitos de Interface

- **Painel de Auditoria:** O módulo Admin deve possuir uma interface de busca avançada de logs, permitindo filtros por:
  - Intervalo de datas.
  - Usuário específico.
  - Tipo de ação (ex: "Ver todas as exclusões feitas pela Chefia X").
  - Endereço IP.
