# Perfil dos dados — `CartorioOficios` (export / DISTINCT)

Fonte: análise do dump [`CartorioOficios.sql`](../../CartorioOficios.sql) na raiz do repositório.

## Volume

- Aproximadamente **6211** registros (linhas `INSERT`).

## Distribuição observada (amostra completa do arquivo)

| Campo | Valores / notas |
|--------|------------------|
| `Meio` | Predominância `'0'`; ocorrências de `'1'` **isoladas** (pouquíssimas). |
| `Prioridade` | `'0'` e `'1'` em volume relevante (`'1'` ≈ flag de urgência no legado). |
| `Processo` | Formato CNJ com pontuação na maioria; placeholder **`0000000-00.0000.8.05.0103`** em múltiplas linhas (“Ofício diversos”). |
| `Usuario` | Nome livre (ex.: nome + sobrenome em um único campo). |
| `IdCrtOficios` | Padrão **14 dígitos** `YYYYMMDDHHMMSS`. |

## De-para aplicado na migração (fechado)

| Legado | Novo |
|--------|------|
| `Meio` / `Prioridade` | **Ignorados** nas colunas do modelo novo (`meio_envio` = **NULL**). |
| `forma_cumprimento` | **`ccm`** para todos. |
| `status` | **`finalizada`** para todos. |
| `Usuario` | Resolução para `id_usuario` via `usuarios` (nome completo); se não achar → **NULL**. |

Não há subtarefas (`oficios_cartorio_subtarefas`) nesta carga — fluxo equivalente a CCM já concluído.

## Artefatos de implementação

| Artefato | Descrição |
|----------|-----------|
| [`database/migrations/20260330_cartorio_oficios_legacy_and_map.sql`](../../database/migrations/20260330_cartorio_oficios_legacy_and_map.sql) | DDL: `cartorio_oficios_legacy`, `oficios_cartorio_migracao_map`, `oficios_cartorio_migracao_rejects`. |
| [`tools/migrate-cartorio-oficios.php`](../../tools/migrate-cartorio-oficios.php) | CLI: `--dry-run`, `--limit`, `--batch-size`; `IdGenerator`, `ensureProcess`, datas do legado, mapa e log. |
| [`docs/sql/migracao-cartorio-oficios-validacao.sql`](migracao-cartorio-oficios-validacao.sql) | Consultas somente leitura pós-carga. |

Importação do dump: ajustar o `CREATE TABLE` do export para `cartorio_oficios_legacy` ou renomear após import (`RENAME TABLE CartorioOficios TO cartorio_oficios_legacy`).
