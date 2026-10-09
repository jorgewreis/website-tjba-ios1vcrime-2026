# Esquema Canônico do Banco de Dados

## 1. Objetivo

Este documento descreve o **estado canônico atual** do banco de dados do projeto, alinhado ao código PHP em produção e aos scripts SQL versionados no repositório.

Ele substitui a narrativa antiga baseada em `tb_*`, `id_perfil`/`id_setor` 1:1 e snippets que não correspondem mais ao sistema real.

## 2. Fontes de verdade do schema

Hoje o repositório **não contém um dump único e completo** de todo o banco. O schema real é composto por:

- tabelas já existentes nos ambientes ativos;
- [`database/schema.sql`](../../database/schema.sql), que serve como **estrutura auxiliar parcial**;
- scripts incrementais em [`database/migrations/`](../../database/migrations);
- leitura do código PHP que consome colunas opcionais ou adicionadas por migração.

Consequência prática:

- `database/schema.sql` **não** deve ser tratado como a única fonte do banco inteiro;
- a documentação de schema deve sempre considerar as migrações posteriores e o uso real no código.

## 3. Convenções globais

- Banco oficial: `MySQL`
- Charset/collation padrão: `utf8mb4` / `utf8mb4_unicode_ci`
- Engine padrão: `InnoDB`
- Colunas e tabelas: `snake_case`
- IDs semânticos: `CHAR(30)` ou `VARCHAR(30)` no formato `XXX-` + 26 caracteres
- CPF: armazenado **com máscara**; validação no backend considera apenas os dígitos

### 3.1. Nomenclatura real

O projeto **não adota prefixo obrigatório `tb_`** nas tabelas ativas. Os nomes reais hoje seguem convenções como:

- `usuarios`
- `perfis`
- `usuario_perfil`
- `setores`
- `usuario_setor`
- `logs_acesso`
- `logs_sistema`
- `recuperacao_senha`
- `historico_senha`
- `login_rate_limits`

## 4. Núcleo de identidade, perfis e lotação

### 4.1. `usuarios`

Tabela central de autenticação e cadastro interno/externo.

Colunas usadas pelo código atual:

- `id_usuario`
- `nome`
- `sobrenome`
- `cpf`
- `email`
- `telefone`
- `cargo`
- `instituicao`
- `senha_hash`
- `token_acesso`
- `token_expira`
- `ultimo_acesso`
- `cadastro_ativo`

Colunas opcionais, condicionadas ao ambiente/migrações:

- `sexo`
- `data_nascimento`
- `created_at`
- `updated_at`
- `meta`

Regras importantes:

- `senha_hash` armazena o hash da senha do usuário;
- `token_acesso` armazena o **hash SHA-256** do token de sessão persistido;
- `token_expira` controla a validade absoluta do token persistido;
- `cadastro_ativo` diferencia cadastro pendente de cadastro apto a autenticar.

### 4.2. `perfis`

Catálogo de perfis usados pelo RBAC.

Estrutura mínima esperada pelo código:

- `id_perfil`
- `descricao`

Os valores efetivamente usados pelo sistema incluem perfis globais e compostos, por exemplo:

- `ADMIN`
- `CHEFIA`
- `ESTUDANTE`
- `CONSULTOR`
- `ESTAGIARIO-CARTORIO`
- `ESTAGIARIO-GABINETE`
- `ESTAGIARIO-AUDIENCIAS`
- `SERVIDOR-CARTORIO`
- `SERVIDOR-GABINETE`
- `SERVIDOR-AUDIENCIAS`

### 4.3. `usuario_perfil`

Relacionamento entre usuário e perfil.

Estrutura mínima usada pelo código:

- `id_usu_perfil`
- `usuario`
- `perfil`

Observação: o modelo atual é **N:N**, e não um único `id_perfil` direto dentro de `usuarios`.

### 4.4. `setores`

Tabela organizacional base para lotação e isolamento por área.

Estrutura versionada em [`database/schema.sql`](../../database/schema.sql):

- `id_setor`
- `codigo`
- `codigo_area`
- `nome`
- `descricao`
- `setor_pai`
- `ativo`
- `data_criacao`

Valores operacionais esperados em `codigo_area`:

- `CARTORIO`
- `GABINETE`
- `AUDIENCIAS`

### 4.5. `usuario_setor`

Relacionamento entre usuário e setor.

Estrutura mínima:

- `id_usu_setor`
- `usuario`
- `setor`
- `data_alocacao`
- `alocado_por`

Observação: o controle de escopo por área hoje depende desta tabela e dos perfis compostos. O modelo atual **não** pressupõe um único `id_setor` dentro de `usuarios`.

## 5. Segurança, autenticação e credenciais

### 5.1. `login_rate_limits`

Tabela **oficial** de rate limit de login.

Foi introduzida na migração [`database/migrations/20260401_login_rate_limits.sql`](../../database/migrations/20260401_login_rate_limits.sql) e é usada pelo serviço [`app/services/LoginRateLimiter.php`](../../app/services/LoginRateLimiter.php).

Estrutura:

- `id_rate_limit`
- `scope_type`
- `scope_value`
- `attempt_count`
- `lock_level`
- `window_started_at`
- `last_attempt_at`
- `locked_until`
- `created_at`
- `updated_at`

Uso atual:

- escopo por `ip` e por `cpf`;
- janela de tempo de tentativas;
- bloqueio temporário progressivo;
- limpeza após login bem-sucedido.

### 5.2. `tentativas_login`

Tabela **legada ainda presente** em partes do sistema.

Hoje ela **não é mais a fonte principal** de proteção contra brute force. Ainda assim, continua sendo usada em alguns pontos administrativos e de limpeza de dados, por exemplo:

- listagens de usuários;
- edição manual de status/bloqueio;
- limpeza de usuários externos antigos.

Conclusão:

- `login_rate_limits` = mecanismo oficial de limitação de login;
- `tentativas_login` = legado ainda em uso parcial, que precisa ser tratado como tal.

### 5.3. `recuperacao_senha`

Tabela de recuperação de senha por link único.

Estrutura usada pelo serviço [`app/services/PasswordResetService.php`](../../app/services/PasswordResetService.php):

- `id_recuperacao`
- `usuario`
- `token`
- `expira_em`
- `usado`
- `ip`
- `data_solicitacao`

Regra importante:

- a coluna `token` **não armazena o token plano** enviado ao usuário;
- ela armazena o **hash do token de redefinição**, embora o nome da coluna continue sendo apenas `token`.

### 5.4. `historico_senha`

Tabela usada para impedir reutilização recente de senha.

Estrutura:

- `id_historico`
- `usuario`
- `senha_hash`
- `data_alteracao`

Ela é consultada tanto na alteração de senha autenticada quanto na redefinição por link.

## 6. Logs e auditoria

### 6.1. `logs_acesso`

Tabela voltada a eventos de autenticação e acesso:

- `id_log`
- `usuario`
- `cpf`
- `acao`
- `ip`
- `user_agent`
- `sucesso`
- `mensagem`
- `data_hora`

### 6.2. `logs_sistema`

Tabela voltada a rastreabilidade de operações críticas:

- `id_log_sistema`
- `usuario`
- `modulo`
- `acao`
- `tabela_afetada`
- `registro_id`
- `dados_anteriores`
- `dados_novos`
- `ip`
- `data_hora`

## 7. Views e índices auxiliares versionados

O arquivo [`database/schema.sql`](../../database/schema.sql) também versiona artefatos auxiliares importantes:

- índices em `usuarios` e `processos`;
- view `v_usuarios_completo`;
- view `v_processos_completo`.

Esses artefatos partem da premissa de que tabelas-base como `usuarios`, `perfis`, `usuario_perfil`, `processos` e `classes` já existem no ambiente.

## 8. Migrations atualmente versionadas

Arquivos presentes hoje em [`database/migrations/`](../../database/migrations):

- `20260322_gabinete_reus_presos.sql`
- `20260322_oficios_criado_por.sql`
- `20260322_seed_setores_inicial.sql`
- `20260322_setores_codigo_area.sql`
- `20260322_usuario_perfil_composite_limpeza.sql`
- `20260330_processoPrioridades.sql` (`deprecated`)
- `20260331_processo_prioridades_unificacao.sql`
- `20260401_login_rate_limits.sql`
- `guias.sql`
- `run_processo_prioridades_unificacao_data.php`
- `salario_minimo_e_valor_multa_guias.sql`
- `seed_salario_minimo_1994_2026.sql`
- `usuarios_created_at_updated_at.sql`

O arquivo [`database/README-migracoes.md`](../../database/README-migracoes.md) deve ser consultado para a classificação por módulo, base e scripts legados/deprecados.

## 9. Decisões canônicas deste item

- O schema real **não usa** `tb_*` como convenção obrigatória.
- `usuario_perfil` e `usuario_setor` são parte do modelo oficial atual.
- `database/schema.sql` é **parcial** e auxiliar, não um dump completo.
- `login_rate_limits` é a estrutura oficial de controle de tentativas de login.
- `tentativas_login` ainda existe como legado em uso parcial e deve ser documentada como tal.
- `recuperacao_senha.token` armazena **hash** do token, apesar do nome da coluna.
