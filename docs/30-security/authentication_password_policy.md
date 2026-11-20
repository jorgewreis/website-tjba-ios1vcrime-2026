# Política de Autenticação e Senha

## 1. Objetivo
Este documento descreve o modelo oficial de autenticação, sessão, senha e recuperação atualmente adotado pelo sistema.

## 2. Modelo oficial de autenticação
O sistema utiliza **sessão PHP com validação server-side** como fluxo principal de autenticação.

O processo oficial ocorre assim:

1. o usuário envia CPF e senha para `public/login.php`;
2. o backend valida as credenciais;
3. em caso de sucesso, a aplicação regenera o identificador de sessão;
4. o backend grava em `$_SESSION` os dados essenciais do usuário autenticado;
5. o backend gera um token aleatório;
6. esse token é **hasheado** antes de ser persistido em `usuarios.token_acesso`;
7. o hash do token é mantido no banco com prazo de expiração;
8. a cada acesso autenticado, o sistema valida a sessão PHP, verifica o timeout de inatividade e confirma no banco se o token ainda é válido.

Componentes principais do fluxo:

- `config/config.php`
- `config/security.php`
- `public/login.php`
- `public/home.php`
- `public/frame.php`
- `public/admin/api/checkToken.php`
- `public/admin/api/getPerfis.php`
- `app/models/Usuario.php`

## 3. Sessão e cookies
O estado autenticado é controlado pelo backend por meio de sessão PHP.

Diretrizes vigentes:

- o navegador não é a fonte de verdade da autenticação;
- `$_SESSION` é o estado principal da navegação autenticada;
- o token persistido no banco funciona como segunda validação de consistência e expiração;
- o logout invalida a sessão local e limpa o token persistido do usuário;
- preferências de interface armazenadas em `sessionStorage` não equivalem a autenticação.

Bootstrap e endurecimento de sessão:

- os parâmetros de sessão são configurados antes de `session_start()`;
- o cookie de sessão usa `HttpOnly`;
- o cookie usa `SameSite=Lax`;
- o atributo `Secure` é aplicado quando a requisição está em HTTPS;
- `session.use_strict_mode=1` e `session.use_only_cookies=1` fazem parte do bootstrap oficial;
- existe expiração absoluta baseada em `SESSION_LIFETIME` e timeout de inatividade de **60 minutos** baseado em `SESSION_IDLE_TIMEOUT`.

## 4. Controle de tentativas de login
O fluxo oficial de login aplica contenção de tentativas mal-sucedidas no backend, antes da criação da sessão autenticada.

Política atual:

- janela de observação de **15 minutos**;
- limite base de **5 tentativas** por janela;
- controle por **IP** e por **CPF**;
- bloqueio temporário **progressivo**, com aumento da duração a cada reincidência;
- persistência em backend por meio da tabela `login_rate_limits`; quando o armazenamento está indisponível, o limitador recusa a tentativa. Não há fallback em sessão nesse caminho.

Componentes principais:

- `public/login.php`
- `app/services/LoginRateLimiter.php`
- `database/migrations/20260401_login_rate_limits.sql`

## 5. Política oficial de senha
A política oficial de senha foi unificada.

Regras vigentes:

- hashing com `password_hash` usando `PASSWORD_ARGON2ID`;
- verificação com `password_verify`;
- mínimo de **10 caracteres**;
- exigência de ao menos **1 letra maiúscula**, **1 minúscula**, **1 número** e **1 símbolo**;
- armazenamento de senha apenas em formato hash;
- proibição de reutilização da senha atual;
- a alteração autenticada compara com a senha atual; a consulta ao histórico está desabilitada no código por `PASSWORD_HISTORY_ON_CHANGE_ENABLED=false`. Na redefinição por recuperação, o histórico é consultado se `PASSWORD_HISTORY_CHECK` estiver habilitado (padrão `1`); se a consulta falhar, o código não bloqueia com base no histórico.

Essa política deve ser respeitada pelos fluxos de:

- criação de usuários;
- cadastro de estudantes;
- alteração de senha;
- redefinição por recuperação.

## 6. Recuperação e redefinição de senha
O fluxo oficial de recuperação de senha não envia mais senha temporária por e-mail.

Fluxo vigente:

1. o usuário informa CPF e e-mail em `public/recupera_senha.php`;
2. o backend valida a combinação e emite um token aleatório;
3. o token é **hasheado** antes de ser persistido em `recuperacao_senha`;
4. o sistema tenta enviar ao usuário um **link de redefinição** com token de uso único; se a entrega falhar, tenta invalidar o token e retorna resposta genérica;
5. o link expira em **1 hora**;
6. a nova senha é definida em `public/redefinir_senha.php`;
7. ao concluir a redefinição, o token de recuperação é marcado como usado e a sessão anterior do usuário é invalidada.

Componentes principais:

- `public/recupera_senha.php`
- `public/envia_mail.php`
- `public/redefinir_senha.php`
- `app/services/PasswordResetService.php`

## 7. Regras de armazenamento e transporte
- credenciais não devem ser armazenadas em texto puro;
- senhas não devem ser registradas em logs;
- tokens de sessão e tokens de recuperação devem ser persistidos em formato hash;
- dados de sessão não devem ser expostos ao frontend como mecanismo primário de autenticação;
- o fluxo de recuperação deve usar token com expiração e uso único;
- a aplicação deve operar preferencialmente sob HTTPS nos ambientes implantados.

## 8. Diretriz canônica
Até nova decisão arquitetural formal, o sistema **não adota JWT em `sessionStorage` como modelo principal de autenticação**.

O modelo oficial vigente é:

- **sessão PHP**
- **token aleatório persistido em hash no banco**
- **validação server-side em páginas e endpoints internos**
- **timeout de inatividade de 60 minutos**
- **reset por link com token único e expiração**
