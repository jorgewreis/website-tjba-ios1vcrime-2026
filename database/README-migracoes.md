# Inventário e uso dos scripts SQL versionados

Este arquivo substitui a lista antiga de migrações que já não existem no repositório.

## 1. Observações gerais

- Hoje **não existe uma ordem única global** para todos os scripts de `database/`.
- O projeto combina:
  - estruturas auxiliares em `database/schema.sql`;
  - triggers opcionais em `database/triggers.sql`;
  - migrações incrementais por módulo em `database/migrations/`.
- Parte das tabelas-base do sistema já existe fora desses scripts auxiliares, especialmente `usuarios`, `perfis`, `usuario_perfil`, `processos` e `classes`.

## 2. Base de autenticação e usuários

### `usuarios_created_at_updated_at.sql`

Adiciona `created_at` e `updated_at` em `usuarios` quando essas colunas ainda não existem.

### `20260401_login_rate_limits.sql`

Cria a tabela `login_rate_limits`, hoje usada como mecanismo oficial de rate limit de login por IP/CPF.

### Observação importante

A tabela `tentativas_login` continua existindo como legado em alguns fluxos administrativos, mas **não** é mais a estrutura oficial de limitação de brute force.

## 3. Setores e perfis compostos

### `20260322_seed_setores_inicial.sql`

Popula a tabela `setores` com os registros iniciais de Cartório, Gabinete e Sala de Audiências.

Use somente quando a tabela `setores` estiver vazia.

### `20260322_setores_codigo_area.sql`

Adiciona/preenche `setores.codigo_area` com os valores operacionais:

- `CARTORIO`
- `GABINETE`
- `AUDIENCIAS`

### `20260322_usuario_perfil_composite_limpeza.sql`

Script assistido de transição para perfis compostos (`ESTAGIARIO-*`, `SERVIDOR-*`).

Não é uma migração “fire and forget”. Exige validação manual, backup e conferência de login antes da remoção de perfis legados.

## 4. Módulo Gabinete

### `20260322_gabinete_reus_presos.sql`

Cria as tabelas do módulo de acompanhamento de réus presos:

- `gabinete_reu_preso`
- `gabinete_reu_preso_processo`
- `gabinete_reu_preso_movimentacao`

Requer a tabela `processos`.

## 5. Módulo Ofícios

### `20260322_oficios_criado_por.sql`

Adiciona rastreabilidade de criação/edição em:

- `oficios_cartorio`
- `oficios_gabinete`

Colunas adicionadas:

- `criado_por`
- `atualizado_por`

## 6. Módulo Guias

### `guias.sql`

Cria as tabelas principais do módulo:

- `guias`
- `guias_subtarefas`
- `guias_alocados`

### `salario_minimo_e_valor_multa_guias.sql`

- cria `salario_minimo`;
- adiciona `guias.valor_multa`.

### `seed_salario_minimo_1994_2026.sql`

Popula a tabela `salario_minimo` com a série histórica necessária ao cálculo de multa.

Executar somente após `salario_minimo_e_valor_multa_guias.sql`.

## 7. Prioridades processuais

### `20260330_processoPrioridades.sql`

Arquivo **deprecado**.

Não deve ser usado em instalações novas. Ele representa o modelo legado `processoPrioridades`.

### `20260331_processo_prioridades_unificacao.sql`

Migração oficial para o modelo unificado em `processo_prioridades`, com:

- `codigo`
- `origem`
- `regra_detalhe`

### `run_processo_prioridades_unificacao_data.php`

Script PHP de apoio para migrar dados da tabela legada `processoPrioridades` para `processo_prioridades`.

Executar somente quando a base ainda contiver a tabela antiga.

## 8. Módulo Sala de Audiências

### `20260526_sala_audiencias_pauta_parte_contatos.sql`

Adiciona a coluna `contatos` (`VARCHAR(200) NULL`) em
`sala_audiencias_pauta_parte`. Usada no editor da parte da audiência
para registrar telefones/e-mails específicos daquela audiência.

Lida pelo helper `AudienciaResumoBuilder` (resumo da audiência em
TXT/PDF) em conjunto com `partes_telefones` quando há
`parte_id_global`, e como única fonte de contato quando a parte foi
criada apenas no contexto da audiência.

## 9. Ordem sugerida por cenário

### Nova instalação em ambiente já existente da aplicação

1. Validar existência das tabelas-base do sistema.
2. Aplicar `usuarios_created_at_updated_at.sql` se necessário.
3. Aplicar `20260322_setores_codigo_area.sql` se `setores.codigo_area` ainda não existir.
4. Aplicar `20260322_seed_setores_inicial.sql` se `setores` estiver vazia.
5. Aplicar `20260401_login_rate_limits.sql`.
6. Aplicar migrações por módulo (`oficios`, `gabinete`, `guias`, `prioridades`) conforme a funcionalidade desejada.

### Base legada com perfis antigos

1. Rodar `20260322_setores_codigo_area.sql`.
2. Conferir e corrigir `codigo_area`.
3. Executar o processo assistido descrito em `20260322_usuario_perfil_composite_limpeza.sql`.
4. Validar logins e permissões.
5. Só então remover perfis legados.

### Base com modelo antigo de prioridades

1. Aplicar `20260331_processo_prioridades_unificacao.sql`.
2. Se existir `processoPrioridades`, executar `php database/migrations/run_processo_prioridades_unificacao_data.php`.
3. Tratar `20260330_processoPrioridades.sql` apenas como referência histórica.

## 10. Arquivos presentes hoje em `database/migrations/`

- `20260322_gabinete_reus_presos.sql`
- `20260322_oficios_criado_por.sql`
- `20260322_seed_setores_inicial.sql`
- `20260322_setores_codigo_area.sql`
- `20260322_usuario_perfil_composite_limpeza.sql`
- `20260330_processoPrioridades.sql`
- `20260331_processo_prioridades_unificacao.sql`
- `20260401_login_rate_limits.sql`
- `20260526_sala_audiencias_pauta_parte_contatos.sql`
- `guias.sql`
- `run_processo_prioridades_unificacao_data.php`
- `salario_minimo_e_valor_multa_guias.sql`
- `seed_salario_minimo_1994_2026.sql`
- `usuarios_created_at_updated_at.sql`
