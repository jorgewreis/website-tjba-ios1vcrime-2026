# Referência histórica de migrações

**Atenção:** as seções abaixo descrevem scripts por módulo, mas várias referências não estão rastreadas ou não foram encontradas na revisão `c6b0274a` de 16/11/2026. Elas não são instruções de execução nem comprovam aplicação em qualquer ambiente. Consulte o [inventário REI-97](../docs/10-architecture/migracoes-inventario-rei97.md) para a distinção entre arquivos versionados, locais e ausentes. Confirme esquema, dependências, recuperação e autorização antes de qualquer migração.

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

### `20261009_add_gabinete_reu_preso_controle_tipo.sql`

Separa, na tabela `gabinete_reu_preso`, os registros dos controles “Réus Presos” e “Réus Presos - Substituto” por meio de `controle_tipo`. Os registros já existentes recebem o valor `principal`; novos registros do controle substituto recebem `substituto`.

O workflow de produção na revisão de referência prevê chamar `scripts/migrate-gabinete-reu-preso-controle-tipo.php` quando o arquivo estiver presente; isso não comprova sua execução nem o estado do banco.

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

### `20260826_add_link_audiencia_sala_pauta.sql`

Adiciona a coluna `link_audiencia` (`VARCHAR(1000) NULL`) em
`sala_audiencias_pauta`. Usada para registrar o link da audiência
quando a localização for `Virtual - Audin`.

## 9. Unidade responsável pelo processo

O script `scripts/migrate-process-responsible-sector.php` adiciona
`processos.id_setor_responsavel` como referência nullable para
`setores.id_setor`, com índice e chave estrangeira. Ele não infere nem
preenche unidades de processos existentes. Pode ser reaplicado com
`--apply`; o script valida a estrutura e preserva atribuições gravadas.
O workflow `.github/workflows/deploy-prod.yml` o executa antes de
sincronizar os arquivos da aplicação.

Ao criar a chave estrangeira, o script mantém `foreign_key_checks` ativo e
usa `ALGORITHM=COPY, LOCK=SHARED` para que o InnoDB valide as referências
durante a operação, sem permitir escritas concorrentes em `processos`.
Essa etapa reconstrói a tabela e pode bloquear gravações pelo tempo da cópia;
antes de executá-la em produção, verifique volume e espaço disponível e programe
uma janela operacional compatível. O preflight `--dry-run` não estima duração.

No cadastro inicial e para processos históricos com `id_setor_responsavel`
nulo, o Cartório é a unidade efetiva quando houver exatamente um setor ativo
com `codigo_area = CARTORIO`. Não há alteração em massa nem inferência por
perfil, nome, origem ou CNJ. Unidade explícita é preservada; se estiver inativa
ou o padrão não puder ser resolvido, as operações protegidas negam acesso.
A permissão individual e a lotação ativa continuam necessárias.

## 10. Ordem sugerida por cenário

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

## 11. Estado dos arquivos

A lista rastreada e a observação do checkout local estão no [inventário REI-97](../docs/10-architecture/migracoes-inventario-rei97.md). Use `git ls-files database/migrations` no commit candidato para atualizar o inventário; presença no disco ou em outro checkout não demonstra versionamento nem aplicação.
