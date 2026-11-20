# Política de Logs, Auditoria e Retenção

## 1. Objetivo

Esta política define como tratar logs técnicos, trilhas operacionais e registros auxiliares de auditoria no estado atual do projeto.

Ela considera a implementação real em PHP, Nginx/PHP-FPM, `error_log`, helpers internos e estruturas SQL auxiliares já versionadas.

## 2. Fontes de log consideradas oficiais

Hoje devem ser tratadas como fontes válidas de observabilidade:

- logs do servidor web;
- logs do PHP-FPM;
- `error_log` da aplicação PHP;
- eventos persistidos em estruturas auxiliares de banco quando o módulo efetivamente as utilizar;
- fallback de auditoria do helper `AuditLogger`.

Estruturas relacionadas já documentadas no repositório:

- `logs_acesso`
- `logs_sistema`
- `login_rate_limits`
- `recuperacao_senha`
- `historico_senha`

## 3. Classificação operacional

### 3.1. Logs técnicos

Usados para:

- exceções;
- falhas de configuração;
- falhas de conexão;
- erros de endpoint;
- problemas de integração.

Fonte principal atual:

- `error_log`

### 3.2. Logs de segurança

Usados para:

- autenticação;
- sessões expiradas;
- bloqueios temporários de login;
- recuperação e redefinição de senha;
- acessos inválidos a endpoints autenticados.

Fontes atuais:

- `error_log`
- `login_rate_limits`
- `recuperacao_senha`
- respostas de endpoints autenticados

### 3.3. Logs de auditoria de negócio

Usados para:

- criação;
- edição;
- exclusão;
- mudanças administrativas;
- operações sensíveis por módulo.

No estado atual, essa camada ainda é parcial. Há uso explícito do helper `AuditLogger`, mas a cobertura não é uniforme entre todos os módulos.

## 4. Política de retenção

Na ausência de uma infraestrutura centralizada única, a retenção deve seguir a seguinte orientação operacional:

- **Logs técnicos de aplicação e infraestrutura:** 30 a 90 dias online, conforme volume e capacidade do ambiente.
- **Eventos de segurança e autenticação:** retenção recomendada de no mínimo 12 meses quando persistidos em banco ou coletor central.
- **Trilhas de auditoria relevantes para conformidade:** retenção recomendada de 5 anos, sobretudo quando vinculadas a operações administrativas e dados sensíveis.
- **Backups de banco:** seguir a política operacional do ambiente e a orientação de [`docs/40-operations/availability-backup-requirements.md`](../40-operations/availability-backup-requirements.md).

## 5. Rotação e armazenamento

O projeto pode operar com arquivos físicos do servidor ou integração com coletor centralizado, mas a regra operacional é a mesma:

- rotação automática dos logs de infraestrutura;
- compressão de arquivos antigos quando aplicável;
- controle de crescimento de disco;
- proteção de permissões no sistema operacional.

Para ambientes Linux com Nginx/PHP-FPM, o uso de `logrotate` continua sendo a abordagem operacional recomendada.

## 6. Controle de acesso

Como não existe hoje um painel canônico e completo de auditoria no sistema, o acesso aos logs é predominantemente operacional.

Diretriz atual:

- acesso bruto a logs técnicos, `error_log` e banco deve ser restrito a `ADMIN` e operação técnica autorizada;
- relatórios derivados para outros perfis dependem de implementação explícita;
- `CHEFIA` não deve ter acesso bruto automático à infraestrutura apenas porque a política fala em auditoria.

## 7. Privacidade e dados sensíveis

Nunca registrar em texto claro:

- senhas;
- tokens de sessão;
- segredos de integração;
- payloads sensíveis completos sem necessidade operacional.

### 7.1. Recuperação de senha e logs

O fluxo de recuperação exige entrega de e-mail. Se a entrega falhar ou SMTP estiver indisponível, o serviço tenta invalidar o token e devolve resposta genérica; falhas de invalidação são registradas para investigação. Não há fallback autorizado que registre o link de redefinição ou token em logs. A resposta genérica não comprova entrega nem invalidação bem-sucedida.

Verifique a configuração e a disponibilidade de SMTP e os eventos de erro, que podem conter detalhes técnicos; revise e sanitize antes de compartilhar, sem copiar credenciais, destinatários, links ou tokens para registros de suporte. O fallback de auditoria é separado e não deve conter esses dados.

## 8. Eventos que devem merecer atenção prioritária

- falhas recorrentes de login;
- aumento de bloqueios em `login_rate_limits`;
- erros de sessão expirada em massa;
- falhas no envio de redefinição de senha;
- falhas de conexão com banco;
- eventos `AUDIT [...]` gerados por fallback do helper de auditoria.

## 9. Estado de maturidade documentado

O projeto já possui mecanismos suficientes para suporte operacional e investigação básica, mas ainda não deve ser descrito como plataforma com logging/auditoria totalmente centralizados.

Até que haja convergência entre `error_log`, `audit_log`, `logs_acesso` e `logs_sistema`, a documentação deve continuar refletindo esse modelo híbrido.
