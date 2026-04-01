# Requisitos de Auditoria e Rastreabilidade

## 1. Objetivo

Este documento descreve o **estado operacional atual** da auditoria e da rastreabilidade do sistema, diferenciando:

- o que já está implementado no código;
- o que existe apenas como estrutura auxiliar no banco;
- o que continua como requisito evolutivo.

## 2. Estado atual da observabilidade e auditoria

Hoje o sistema possui um modelo **híbrido** de registro operacional.

As principais fontes são:

- `error_log` do PHP para falhas, exceções e eventos técnicos;
- logs de infraestrutura do servidor web e do PHP-FPM;
- helper [`app/helpers/AuditLoggerHelper.php`](../../app/helpers/AuditLoggerHelper.php), que tenta gravar em `audit_log` e, se falhar, faz fallback para `error_log`;
- estruturas auxiliares documentadas em [`database/schema.sql`](../../database/schema.sql), como `logs_acesso` e `logs_sistema`, ainda sem adoção uniforme em todos os módulos.

Conclusão importante:

- o projeto **ainda não possui** um subsistema único, centralizado e homogêneo de auditoria para todos os eventos;
- a rastreabilidade atual existe, mas está distribuída entre banco, runtime e logs de aplicação.

## 3. Eventos atualmente observáveis

### 3.1. Segurança e autenticação

O código atual já permite rastrear ou inferir operacionalmente:

- falhas de autenticação no login;
- bloqueios temporários progressivos via `login_rate_limits`;
- invalidação de sessão e logout;
- expiração de sessão/inatividade refletida em páginas e endpoints;
- solicitação de redefinição de senha;
- uso de token de redefinição e invalidação após consumo.

Artefatos principais:

- [`public/login.php`](../../public/login.php)
- [`app/services/LoginRateLimiter.php`](../../app/services/LoginRateLimiter.php)
- [`public/logout.php`](../../public/logout.php)
- [`public/admin/api/checkToken.php`](../../public/admin/api/checkToken.php)
- [`app/services/PasswordResetService.php`](../../app/services/PasswordResetService.php)
- [`config/security.php`](../../config/security.php)

### 3.2. Regras de negócio

A cobertura de auditoria de negócio ainda é parcial.

Hoje há exemplo explícito de auditoria de ação de negócio em:

- [`app/services/Guias.php`](../../app/services/Guias.php), que chama `AuditLogger::log(...)` no cadastro de guia.

Nos demais módulos, o padrão predominante ainda é:

- validação de regras;
- retorno controlado ao usuário;
- registro técnico por `error_log` em caso de falha.

### 3.3. Estruturas de banco relacionadas

As seguintes estruturas existem no material SQL versionado e devem ser tratadas como parte do desenho canônico de observabilidade:

- `logs_acesso`
- `logs_sistema`
- `login_rate_limits`
- `recuperacao_senha`
- `historico_senha`

Observação:

- `login_rate_limits`, `recuperacao_senha` e `historico_senha` não são logs de auditoria completos, mas deixam rastros operacionais úteis para suporte e investigação.

## 4. Eventos mínimos que devem ser tratados como obrigatórios

Mesmo quando ainda não houver persistência homogênea em tabela dedicada, os seguintes eventos devem ser considerados obrigatórios do ponto de vista operacional:

- login bem-sucedido;
- login mal-sucedido;
- bloqueio temporário por excesso de tentativas;
- logout;
- sessão expirada por timeout de inatividade;
- solicitação de recuperação de senha;
- redefinição de senha concluída;
- criação, edição e exclusão de registros sensíveis;
- alteração de perfil, lotação ou status de usuário;
- operações administrativas críticas.

## 5. Armazenamento atual e implicações práticas

### 5.1. `error_log` como trilha operacional real

Hoje, na prática, o `error_log` é uma das fontes mais importantes de suporte.

Ele registra, entre outros:

- falhas de configuração;
- falhas de conexão;
- exceções em endpoints;
- erros de sessão;
- falhas no fluxo de reset;
- fallback do `AuditLogger`.

Isso significa que retenção, permissão de acesso e rotação do `error_log` têm impacto direto na capacidade de auditoria e diagnóstico do sistema.

### 5.2. `audit_log` via helper

O helper de auditoria atual tenta gravar em uma tabela chamada `audit_log`.

Esse ponto é importante porque:

- essa tabela **não é a mesma** narrativa documentada em `logs_sistema`;
- a inserção é “best effort”;
- se a tabela não existir, o evento vai para `error_log`.

Portanto, a documentação operacional deve assumir que:

- `audit_log` é um mecanismo pontual já usado no código;
- `logs_sistema` e `logs_acesso` são estruturas auxiliares previstas no schema;
- ainda há trabalho futuro para convergir tudo em um modelo único.

## 6. Acesso operacional aos logs

No estado atual do projeto, **não existe um painel administrativo completo e canônico de auditoria** para navegação por filtros na interface.

Assim, o acesso real aos registros ocorre por:

- acesso operacional ao servidor;
- acesso controlado ao banco de dados;
- inspeção de arquivos/logs de infraestrutura;
- investigação pontual em tabelas auxiliares.

Diretriz atual:

- acesso direto a logs técnicos e a trilhas operacionais deve ficar restrito a `ADMIN` e à operação técnica autorizada;
- qualquer relatório filtrado para outros perfis deve depender de implementação explícita, não de acesso bruto à infraestrutura.

## 7. Sensibilidade dos dados de log

É proibido registrar em texto claro:

- senhas;
- tokens de sessão;
- hash de senha desnecessariamente exposto;
- segredos de integração;
- dados pessoais além do necessário para suporte e auditoria.

### 7.1. Observação importante sobre reset de senha

Quando `MAIL_DRIVER` não está configurado para SMTP, o fluxo atual de recuperação pode registrar o link de redefinição no `error_log` como fallback operacional.

Isso é aceitável para desenvolvimento/homologação, mas deve ser tratado com cuidado:

- o arquivo de log passa a conter material sensível temporário;
- em produção, o ideal é operar com SMTP configurado e acesso restrito aos logs.

## 8. Retenção e governança

A política de retenção deve ser lida em conjunto com [`docs/30-security/log-retention-policy.md`](../30-security/log-retention-policy.md).

Como regra:

- logs técnicos de alto volume podem ter retenção menor;
- trilhas ligadas a segurança, autenticação e operações administrativas exigem retenção mais longa;
- qualquer descarte deve respeitar a necessidade de suporte, conformidade e investigação.

## 9. Lacunas atuais registradas

As principais lacunas ainda abertas são:

- ausência de um pipeline único e uniforme para `logs_acesso`, `logs_sistema` e `audit_log`;
- ausência de painel canônico de auditoria na interface;
- cobertura desigual de eventos de negócio entre módulos;
- dependência forte de `error_log` para parte relevante da trilha operacional.

Essas lacunas não impedem operação, mas precisam ser conhecidas para que a documentação não descreva um nível de maturidade de auditoria que o sistema ainda não atingiu.
