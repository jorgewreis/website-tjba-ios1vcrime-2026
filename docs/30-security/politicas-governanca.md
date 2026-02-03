# Política de Governança e Fluxos de Aprovação Administrativa

## 1. Objetivo

Este documento estabelece a matriz de responsabilidades para a aprovação de novos cadastros e alterações de privilégios no sistema. O objetivo é garantir que apenas usuários devidamente validados tenham acesso aos recursos internos, mantendo a segurança da informação e a rastreabilidade das concessões de acesso.

## 2. Estados do Ciclo de Vida do Usuário

Para fins de aprovação, todo usuário no sistema deve transitar pelos seguintes estados:

1.  **Pré-Cadastro (Pendente):** O usuário preencheu o formulário, os dados foram validados sintaticamente (formato de CPF, e-mail), mas o acesso ao sistema permanece bloqueado.
2.  **Ativo (Aprovado):** O cadastro foi validado pela autoridade competente e o login está liberado.
3.  **Rejeitado:** O cadastro foi negado. O registro permanece no banco para histórico, mas o acesso é impedido.
4.  **Bloqueado:** Usuário anteriormente ativo que foi suspenso (ex: 3 tentativas de senha incorretas ou desligamento).

---

## 3. Matriz de Responsabilidade por Perfil

A tabela abaixo define qual autoridade possui a permissão para transicionar um usuário do estado **Pendente** para **Ativo**.

| Perfil Solicitante (Novo Cadastro) | Tipo de Aprovação | Autoridade Aprovadora Primária | Autoridade Aprovadora Secundária |
| :--- | :---: | :--- | :--- |
| **Estudante** | **Automática** | Sistema (Validação de Regras) | N/A |
| **Estagiário** | Manual | Chefia do Setor de Lotação | Admin |
| **Servidor** | Manual | Chefia do Setor de Lotação | Admin |
| **Consultor Externo** | Manual | Chefia do Cartório / Gabinete | Admin |
| **Chefia** | Manual | Admin | N/A |
| **Admin** | Manual | Admin (Pré-existente) | N/A |

---

## 4. Detalhamento dos Fluxos

### 4.1. Fluxo de Aprovação Automática (Perfil: Estudante)

Este fluxo visa reduzir a carga operacional para acessos de baixo risco e caráter público/educacional.

1.  **Solicitação:** O usuário preenche o formulário de auto-cadastro na interface pública.
2.  **Validação Sistêmica:** O backend verifica:
    -  Unicidade do CPF e E-mail.
    -  Força da senha (mínimo 8 caracteres, maiúsculas, minúsculas, números, especiais).
3.  **Ação:** Se as validações passarem, o status é definido imediatamente como `ATIVO`.
4.  **Pós-Condição:** O usuário é redirecionado para a *Home* e já pode visualizar o painel de audiências e baixar suas atas.

### 4.2. Fluxo de Aprovação Supervisionada (Perfis Internos e Consultores)

Este fluxo aplica-se a todos os perfis que terão acesso a dados sensíveis ou operações internas (Servidor, Estagiário, Consultor).

1.  **Solicitação:** O usuário (ou um Admin em nome dele) realiza o pré-cadastro.
2.  **Estado Inicial:** O registro é gravado no banco de dados com status `PENDENTE` (ou flag `ativo = 0`).
3.  **Notificação:** O sistema deve alertar a **Chefia** do setor correspondente (via painel de controle) que existem solicitações pendentes.
4.  **Análise:** A autoridade aprovadora (Chefia ou Admin) visualiza os dados do solicitante.
5.  **Decisão:**
    - **Aprovar:** A autoridade confirma a lotação e o perfil. O sistema altera o status para `ATIVO`.
    - **Rejeitar:** A autoridade nega o pedido. O sistema mantém o bloqueio ou exclui o pré-cadastro (dependendo da política de retenção).

### 4.3. Regras de Hierarquia na Aprovação

- **Soberania do Admin:** O perfil **Admin** possui "super-poderes" para aprovar, bloquear ou desbloquear qualquer usuário de qualquer setor a qualquer momento.
- **Isolamento de Chefia:** Uma **Chefia** só pode aprovar servidores e estagiários que estejam alocados no **seu próprio setor**. Uma Chefia do "Cartório" não pode aprovar um servidor do "Gabinete".
- **Escalação:** Caso um setor esteja sem Chefia ativa, a aprovação deve ser escalada para o **Admin**.

---

## 5. Auditoria de Aprovações

Para fins de *compliance* e segurança, o ato de aprovação manual deve ser registrado em log (tabela de auditoria), contendo:

- `id_usuario_aprovado`: Quem solicitou o acesso.
- `id_usuario_aprovador`: Quem liberou o acesso (Chefia ou Admin).
- `data_hora`: Timestamp da liberação.
- `ip_origem`: Endereço IP do aprovador.

> **Nota de Implementação:** Recomenda-se criar uma view no painel da Chefia chamada "Solicitações Pendentes", filtrando apenas usuários com `status = 'PENDENTE'` e `setor = [Setor da Chefia]`.
