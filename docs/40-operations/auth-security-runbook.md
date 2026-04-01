# Runbook de Autenticação, Segurança e Pós-Deploy

## 1. Objetivo

Este runbook consolida os passos operacionais mínimos para deploy, validação e suporte dos fluxos de autenticação e segurança atualmente implementados no sistema.

## 2. Premissas importantes

- o workflow [`deploy-dev.yml`](../../.github/workflows/deploy-dev.yml) sincroniza arquivos e recarrega serviços;
- o deploy automatizado **não executa migrações SQL automaticamente**;
- portanto, qualquer mudança de banco exigida pela release precisa ser aplicada manualmente no ambiente.

## 3. Checklist de pós-deploy

### 3.1. Banco de dados

Verificar se a base do ambiente já possui a estrutura esperada para a release.

Conferências mínimas:

- tabela `login_rate_limits` criada;
- colunas de `usuarios` necessárias ao ambiente presentes;
- tabela `recuperacao_senha` disponível;
- tabela `historico_senha` disponível quando a política de reutilização estiver ativa.

Arquivos importantes:

- [`database/migrations/20260401_login_rate_limits.sql`](../../database/migrations/20260401_login_rate_limits.sql)
- [`database/migrations/usuarios_created_at_updated_at.sql`](../../database/migrations/usuarios_created_at_updated_at.sql)
- [`database/README-migracoes.md`](../../database/README-migracoes.md)

### 3.2. Variáveis e configuração

Validar no ambiente:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_URL`
- `APP_TIMEZONE`
- `MAIL_DRIVER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME` ou `MAIL_USER`
- `MAIL_PASSWORD` ou `MAIL_PASS`

## 4. Smoke test mínimo obrigatório

Após o deploy, validar:

1. abertura de `public/index.php`;
2. login com usuário válido;
3. navegação autenticada até `public/home.php`;
4. abertura de página interna via `public/frame.php`;
5. verificação de sessão por [`public/admin/api/checkToken.php`](../../public/admin/api/checkToken.php);
6. logout;
7. solicitação de recuperação de senha;
8. abertura do link de redefinição;
9. redefinição com nova senha válida.

## 5. Rate limit e bloqueio de login

O mecanismo oficial atual usa `login_rate_limits`.

Conferir:

- se a tabela existe;
- se tentativas inválidas incrementam o controle por IP e CPF;
- se o login bem-sucedido limpa o bloqueio aplicável;
- se o usuário recebe mensagem de bloqueio temporário coerente.

Arquivo principal:

- [`app/services/LoginRateLimiter.php`](../../app/services/LoginRateLimiter.php)

## 6. Timeout de inatividade

O sistema opera com timeout de inatividade de 60 minutos.

Quando houver relato de “sessão expirada”, verificar:

- validade da sessão PHP;
- token persistido do usuário;
- consistência entre sessão e banco;
- horário/clock do servidor;
- comportamento do endpoint [`public/admin/api/checkToken.php`](../../public/admin/api/checkToken.php).

Arquivos principais:

- [`config/config.php`](../../config/config.php)
- [`config/security.php`](../../config/security.php)
- [`public/home.php`](../../public/home.php)
- [`public/frame.php`](../../public/frame.php)

## 7. Recuperação e redefinição de senha

Fluxo atual:

1. solicitação em [`public/recupera_senha.php`](../../public/recupera_senha.php);
2. processamento em [`public/envia_mail.php`](../../public/envia_mail.php);
3. geração e persistência via [`app/services/PasswordResetService.php`](../../app/services/PasswordResetService.php);
4. redefinição final em [`public/redefinir_senha.php`](../../public/redefinir_senha.php).

### 7.1. Observação operacional crítica

Se `MAIL_DRIVER` não estiver configurado para SMTP, o sistema registra o link de reset no `error_log`.

Isso implica:

- comportamento aceitável para desenvolvimento/homologação;
- necessidade de cuidado extra com acesso aos logs;
- recomendação forte de SMTP ativo em produção.

## 8. Sinais de incidente e triagem rápida

### 8.1. Usuários não conseguem logar

Verificar:

- variáveis de banco;
- conectividade PDO;
- existência de `login_rate_limits`;
- bloqueio ativo por IP/CPF;
- `cadastro_ativo` do usuário;
- erros recentes no `error_log`.

### 8.2. Sistema derruba sessão cedo demais

Verificar:

- `SESSION_IDLE_TIMEOUT`;
- horário do servidor;
- proxy/HTTPS afetando cookie `Secure`;
- limpeza indevida de sessão;
- respostas de `Sessão expirada` nos endpoints internos.

### 8.3. Reset de senha não chega

Verificar:

- `MAIL_DRIVER`;
- `MAIL_HOST`, `MAIL_PORT`, usuário e senha;
- disponibilidade de PHPMailer no ambiente;
- presença de logs `[RESET SENHA]` no `error_log`.

## 9. Estado atual de auditoria

Hoje a auditoria do sistema é híbrida:

- parte em `error_log`;
- parte em estruturas auxiliares de banco;
- parte em `AuditLogger`, com fallback.

Referências:

- [`docs/40-operations/audit-traceability-requirements.md`](audit-traceability-requirements.md)
- [`docs/30-security/log-retention-policy.md`](../30-security/log-retention-policy.md)

## 10. Referências rápidas

- [`README.md`](../../README.md)
- [`docs/30-security/authentication_password_policy.md`](../30-security/authentication_password_policy.md)
- [`docs/10-architecture/database-schema-base.md`](../10-architecture/database-schema-base.md)
- [`docs/10-architecture/documento-integracoes-api.md`](../10-architecture/documento-integracoes-api.md)
