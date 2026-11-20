# Inventário de migrações — REI-97

**Referência:** `origin/main` em `c6b0274a`, inspecionado em 16/11/2026; arquivos locais inspecionados somente por leitura no checkout `website-tjba-ios1vcrime-2026`. Este retrato não é uma sequência de execução. Não foram conectados bancos nem executados scripts. Estado de aplicação em DEV, PROD ou outro ambiente: **desconhecido**. O histórico ou nome do arquivo não demonstra autoria, integridade ou execução; a origem dos arquivos não rastreados não foi estabelecida.

## Arquivos rastreados no commit de referência

| Arquivo em `database/migrations/` | Natureza e dependências observadas |
| --- | --- |
| `20260807_create_cejusc_registros.sql` | Cria tabela CEJUSC, se ausente. |
| `20260807_add_cejusc_classe_processual.sql` | Altera `cejusc_registros`; depende da tabela anterior ou equivalente. |
| `20261009_add_gabinete_reu_preso_controle_tipo.sql` | Altera tabela de réu preso e cria índice; exige tabela prévia. Há executor PHP específico no projeto: sua existência não prova aplicação. |
| `20261109_create_login_rate_limits.sql` | Cria tabela de limitação de login, se ausente. |
| `run_processo_prioridades_unificacao_data.php` | Copia dados de prioridades legadas para modelo unificado; exige esquema e condições descritas no próprio arquivo. |

A regra versionada `.gitignore:102` ignora `*.sql`, com exceções nominais; confirme no commit candidato com `git ls-files database/migrations` e `git check-ignore -v`. REI-132 trata a regra. Scripts PHP de `scripts/` são outro conjunto e não estão incluídos nesta tabela.

## Arquivos presentes somente no checkout local inspecionado

Os 22 arquivos abaixo não constavam no `git ls-files` do commit de referência. Na inspeção, 18 apareciam como não rastreados (`??`) e quatro como ignorados (`!!`): `guias.sql`, `salario_minimo_e_valor_multa_guias.sql`, `seed_salario_minimo_1994_2026.sql` e `usuarios_created_at_updated_at.sql`. O `.gitignore` do checkout principal estava modificado; por isso essa classificação local não deve ser transferida para outra cópia. Todos são artefatos locais a preservar, não migrações entregues pelo commit.

| Arquivo | Natureza, dependências e riscos por leitura estática |
| --- | --- |
| `20260322_gabinete_reus_presos.sql` | Cria tabelas de réus presos; depende de `processos` e contém FKs com ações de exclusão. |
| `20260322_oficios_criado_por.sql` | Altera tabelas de ofícios para rastreabilidade. |
| `20260322_seed_setores_inicial.sql` | Insere setores; comentário limita uso a tabela vazia e pressupõe colunas/setores. |
| `20260322_setores_codigo_area.sql` | Altera e atualiza setores e cria índice; comentário solicita backup e revisão. |
| `20260322_usuario_perfil_composite_limpeza.sql` | Modelo assistido de perfis com exemplos comentados de DELETE/INSERT; exige revisão e validação manual, não é execução automática. |
| `20260330_processoPrioridades.sql` | Cria tabela legada; o próprio arquivo a marca como deprecada. |
| `20260331_processo_prioridades_unificacao.sql` | Altera e atualiza prioridades, exclui linhas e remove índice/coluna; depende do modelo anterior e pode perder dados. |
| `20260401_login_rate_limits.sql` | Cria tabela de rate limit; não presumir equivalência com a versão rastreada de novembro sem comparar. |
| `20260402_sala_midias.sql` | Cria tabelas de mídia e insere regras de produtividade; depende de pauta, processos, usuários e regras existentes. |
| `20260406_sala_audiencias_pauta_alocados.sql` | Cria tabela de alocação da pauta; depende do modelo correspondente. |
| `20260410_sala_midias_produtividade_create.sql` | Insere regras; comentário depende da migração local de mídia de 02/04. |
| `20260411_sala_midias_status_listagem.sql` | Altera status e atualiza registros; comentário depende da migração local de mídia de 02/04. |
| `20260518_registro_metadata_criado_por.sql` | Altera várias tabelas e atualiza registros; pressupõe tabelas e colunas anteriores. |
| `20260521_disable_produtividade_automatica_regras.sql` | Atualiza regras de produtividade; comentário informa não excluir lançamentos históricos. |
| `20260521_drop_cejusc_ceapa.sql` | Exclui lançamentos/regras, remove coluna e tabelas; operação destrutiva e dependente do esquema. |
| `20260521_drop_produtividade_metas.sql` | Remove trigger e tabela de metas; operação destrutiva. |
| `20260521_remove_consulta_cep.sql` | Atualiza regra e exclui lançamentos vinculados; operação destrutiva para os dados afetados. |
| `20261115_editais_idempotencia.sql` | Cria tabela de idempotência; comentário exige processo autorizado. Origem e estado de aplicação não comprovados. |
| `guias.sql` | Cria tabelas e contém ALTER ativo em `guias`; pressupõe tabelas e regras citadas no arquivo. |
| `salario_minimo_e_valor_multa_guias.sql` | Cria tabela e altera `guias`; depende do modelo de guias. |
| `seed_salario_minimo_1994_2026.sql` | Insere série histórica; comentário exige tabela criada pelo script de salário mínimo. Fonte e conteúdo não foram validados nesta revisão. |
| `usuarios_created_at_updated_at.sql` | Altera `usuarios`; pode falhar se colunas já existirem. |

O README histórico menciona também `20260526_sala_audiencias_pauta_parte_contatos.sql` e `20260826_add_link_audiencia_sala_pauta.sql`: não foram encontrados no checkout principal inspecionado nem no conjunto rastreado da referência. Isso não prova inexistência em outras cópias ou ambientes.

## Operação e limites

Não aplique scripts com base nesta lista. Antes de qualquer operação autorizada: identifique o ambiente e versão reais, inspecione esquema e histórico de execução, dependências e consumidores; revise o conteúdo exato, impacto e compatibilidade entre versões; prepare recuperação proporcional e valide-a quando possível. `CREATE IF NOT EXISTS` não comprova que o esquema existente corresponde ao esperado; DDL MySQL pode ter commit implícito. Para remoções, avalie backup e restauração testada e preserve dados até autorização específica. Não se verificou aplicação, backup, reversão, origem dos arquivos locais ou integridade dos bancos nesta tarefa.

O workflow de produção possui chamadas condicionais a scripts específicos quando presentes; não executa indiscriminadamente esta lista. Consulte o workflow da revisão efetiva e o runbook vigente. Uma execução de pipeline sem inspeção do estado do banco não comprova que cada migração foi aplicada.
