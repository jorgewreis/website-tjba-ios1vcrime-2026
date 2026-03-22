# Convergência: réus/processoReus → partes/processos_partes

## Objetivo

O sistema possui dois modelos de vínculo pessoa–processo:

- **Legado:** tabelas `reus` e `processoReus` (número do processo + id_reu), usadas por Guias, Cejusc e Medidas Protetivas.
- **Canônico:** tabelas `partes` e `processos_partes` (processo_id, parte_id, tipo_parte, polo), usado pelo módulo Consulta Partes / Consulta Processos.

A longo prazo, o modelo canônico deve ser a única fonte de verdade. Este documento descreve onde está o legado e como convergir.

## Pontos de uso do legado

| Local | Uso |
|-------|-----|
| `app/services/ReuManager.php` | CRUD em `reus` e `processoReus`; listar réus por processo; vincular réu a processo. |
| `app/services/GuiasService.php` | ReuManager para garantir/cadastrar réu e vincular ao processo. |
| `app/services/MedidasProtetivasService.php` | ReuManager para réu da medida. |
| `app/services/CejuscService.php` | ReuManager. |
| `app/services/Guias.php` | ReuManager; join com `reus` em listagem. |
| `app/repositories/CejuscRepository.php` | JOIN com tabela `reus` (reu_id). |
| `public/api/cartorio-guias/reus-por-processo.php` | Lista réus do processo (legado); **atualizado para incluir também partes do modelo canônico** quando o processo existir em `processos` e tiver vínculos em `processos_partes`. |
| `app/views/internal/cartorio-guias-create.php` | Select de denunciado alimentado por reus-por-processo. |
| `app/views/internal/cartorio-cejusc-create.php` | Select de réu alimentado por reus-por-processo. |

## Ajustes já realizados

- **API reus-por-processo.php:** passa a consultar também `processos` + `processos_partes` + `partes`. Réus do legado e partes do modelo canônico (tipos réu/indiciado/requerido/sentenciado no polo passivo) são mesclados na resposta, para que telas de Guias e Cejusc possam exibir pessoas cadastradas no novo módulo sem quebrar o contrato da API.

## Próximos passos sugeridos

1. **Migração de dados (opcional):** script único que, para cada linha em `processoReus`, localize ou crie a `partes` pelo nome/CPF e crie o vínculo em `processos_partes` (processo_id obtido por número do processo em `processos`).
2. **Novos fluxos:** todo novo desenvolvimento deve usar apenas `PartesRepository`, `ProcessosPartesRepository` e `PessoasService`; evitar criar novos usos de ReuManager.
3. **Substituição gradual:** quando um fluxo (ex.: Guias) for refatorado para usar apenas partes/processos_partes, remover a dependência de ReuManager nesse fluxo e descontinuar o uso das tabelas legadas para esse caso.
4. **Réus Presos:** a tela `reuspresos` (Gabinete) pode no futuro passar a ler de uma visão ou tabela derivada de `partes`/`processos_partes` quando houver migração completa.

## Referência do modelo canônico

- **partes:** id (PRT-...), cpf, nome_completo, sexo, data_nascimento.
- **processos_partes:** id (PPR-...), processo_id, parte_id, tipo_parte, polo, prioridades.
- Tipos de parte (ex.): reu, indiciado, requerido, vitima, requerente, etc.
- Polos: ativo, passivo, terceiros.

Documentação do plano geral: ver plano "Pessoas E Processos" (consulta-partes, consulta-processos, instituições públicas, vínculos).
