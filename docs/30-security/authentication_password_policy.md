# Política de Autenticação e Senha

## 1. Objetivo
Este documento descreve o modelo oficial de autenticação atualmente adotado pelo sistema e as regras mínimas relacionadas a senha, sessão e proteção do fluxo autenticado.

## 2. Modelo oficial de autenticação
O sistema utiliza **sessão PHP com validação server-side** como fluxo principal de autenticação.

O processo ocorre da seguinte forma:

1. o usuário envia CPF e senha para `public/login.php`;
2. o backend valida as credenciais;
3. em caso de sucesso, a aplicação regenera o identificador de sessão;
4. o backend grava em `$_SESSION` os dados essenciais do usuário autenticado;
5. o backend gera um token aleatório;
6. esse token é persistido no banco de dados com expiração;
7. a cada acesso autenticado, o sistema valida a sessão PHP e confirma no banco se o token ainda é válido.

Componentes principais do fluxo:

- `config/security.php`
- `public/login.php`
- `public/home.php`
- `public/admin/api/checkToken.php`
- `public/admin/api/getPerfis.php`
- `app/models/Usuario.php`

## 3. Sessão e estado autenticado
O estado autenticado é controlado pelo backend por meio de sessão PHP.

Diretrizes:

- o navegador não é a fonte de verdade da autenticação;
- `$_SESSION` é o estado principal da navegação autenticada;
- o token persistido no banco funciona como segunda validação de consistência e expiração;
- o logout deve invalidar a sessão local e limpar o token persistido do usuário;
- preferências de interface armazenadas em `sessionStorage` não equivalem a autenticação.

## 4. Requisitos atuais de senha
No estado atual do sistema, a política funcional mínima adotada pelo código é:

- hashing com `password_hash` usando `PASSWORD_ARGON2ID`;
- verificação com `password_verify`;
- exigência mínima de comprimento conforme o fluxo implementado em cada tela;
- transmissão de credenciais somente por requisição HTTP ao backend;
- armazenamento de senha apenas em formato hash.

Observação importante:

- existem divergências entre fluxos de criação, alteração e recuperação de senha no sistema atual;
- este documento descreve o modelo de autenticação oficial, mas a uniformização completa da política de senha deve ser tratada em revisão específica.

## 5. Controle de tentativas e sessão
O login atual aplica contenção de tentativas mal-sucedidas com base na sessão do usuário anônimo antes da autenticação.

Estado atual documentado:

- limite de 5 tentativas dentro de uma janela de 15 minutos no fluxo de login principal;
- o bloqueio atual não é um bloqueio persistente por conta;
- a checagem é feita no backend antes da criação da sessão autenticada.

Essa política reflete o comportamento atual do código e pode ser revista separadamente em endurecimento de segurança.

## 6. Recuperação de senha
O fluxo de recuperação de senha deve ser documentado de acordo com a implementação efetiva do sistema.

No estado atual:

- a recuperação não depende do modelo oficial de sessão autenticada;
- a autenticação principal da Área Restrita continua sendo sessão PHP com token persistido em banco;
- qualquer refatoração futura do reset de senha deve preservar a coerência com esse modelo.

## 7. Regras de armazenamento e transporte
- credenciais não devem ser armazenadas em texto puro;
- senhas não devem ser registradas em logs;
- tokens persistidos e dados de sessão não devem ser expostos ao frontend como mecanismo primário de autenticação;
- a aplicação deve operar preferencialmente sob HTTPS nos ambientes implantados.

## 8. Diretriz canônica
Até nova decisão arquitetural formal, o sistema **não adota JWT em `sessionStorage` como modelo principal de autenticação**.

O modelo oficial vigente é:

- **sessão PHP**
- **token aleatório persistido no banco**
- **validação server-side em páginas e endpoints internos**
