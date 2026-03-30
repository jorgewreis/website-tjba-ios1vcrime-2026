# Plano de migração: `CartorioOficios` → `oficios_cartorio`

## Fonte de dados (atualizada)

- **Arquivo no repositório:** [`CartorioOficios.sql`](../../CartorioOficios.sql) (raiz do projeto).
- **Origem do dump:** phpMyAdmin, banco `ios1_users`, MariaDB 10.4, charset `utf8mb4` no export (com `CREATE TABLE` e blocos `INSERT`).
- **Volume:** ~**6211** linhas de dados (tuplas `INSERT`), além do DDL inicial.

Este documento substitui a etapa “anexar dump” do plano genérico: o SQL completo já está disponível para análise e para gerar scripts de migração.

## Perfil dos dados legados (extraído do dump)

### Chave e identificadores (obrigatório: [`IdGenerator`](../../app/helpers/IdGenerator.php))

**Regra:** todo registro inserido em `oficios_cartorio` na migração deve usar **exclusivamente** o padrão gerado por `IdGenerator::generateUniquePrefixedId()` — **não** reutilizar `IdCrtOficios` como PK e **não** inventar IDs em SQL estático.

- **Formato do ID** (implementação em [`IdGenerator::buildCandidate`](../../app/helpers/IdGenerator.php)):
  - Prefixo de **3 letras** em maiúsculas (`OFC` para ofícios do cartório, alinhado a [`OficiosCartorioService::create`](../../app/services/OficiosCartorioService.php)).
  - **Hífen** + **26 caracteres** hexadecimais em maiúsculas (`random_bytes(13)` → `bin2hex`).
  - **Comprimento total:** **30 caracteres** (parâmetro padrão `$totalLength = 30`).
  - Exemplo de forma: `OFC-A1B2C3D4E5F6G7H8I9J0K1L2M3`.

- **Chamada obrigatória** (por linha migrada, após conectar ao PDO do banco destino):

  ```php
  IdGenerator::generateUniquePrefixedId(
      $connection,
      'oficios_cartorio',
      'id',
      'OFC',
      30
  );
  ```

  O método verifica **unicidade** na tabela antes de retornar (`SELECT 1 FROM oficios_cartorio WHERE id = ?`).

- **`IdCrtOficios` legado:** string numérica **14 caracteres** (`YYYYMMDDHHMMSS`). Serve apenas como **chave de correlação** — persistir em tabela auxiliar de mapeamento, ex.: `oficios_cartorio_migracao_legacy(legacy_id VARCHAR(20) PRIMARY KEY, novo_id CHAR(30) NOT NULL)` ou coluna de staging; **nunca** como `id` da linha final.

- **Implicação:** a carga não pode ser apenas um `INSERT ... SELECT` com IDs concatenados em MySQL; o pipeline deve ser **script PHP (CLI)** com bootstrap de conexão (`Conection`/config) ou ferramenta que chame a mesma API. Migração em lotes com transação por lote é aceitável, desde que cada `id` passe pelo `IdGenerator`.

### Processo (CNJ)

- **`Processo`:** em geral no **formato CNJ com pontuação** (ex.: `0500951-74.2018.8.05.0103`), compatível com a extração de **20 dígitos** usada em [`OficiosCartorioService::create`](../../app/services/OficiosCartorioService.php).
- **Placeholder:** o número **`0000000-00.0000.8.05.0103`** aparece em dezenas de registros (ex.: “Ofício diversos”) — **não** produz CNJ válido para `ensureProcess`.
- **Decisão confirmada (produto):** para esses casos, migrar com **`processo_id` = NULL** e manter **`numero_processo`** com o **texto vindo do legado** (até 64 caracteres; o campo é NOT NULL no modelo novo). **Não** chamar `ensureProcess` quando não houver 20 dígitos válidos. A UI/listagens podem precisar de tratamento para linha sem vínculo em `processos` (já há `LEFT JOIN` em [`OficiosCartorioRepository::findById`](../../app/repositories/OficiosCartorioRepository.php) — validar exibição quando `processo_id` for NULL).

### Meio e prioridade (códigos legados)

- **`Meio`** e **`Prioridade`:** colunas `varchar(2)`, predominância **`'0','0'`**.
- **`Prioridade = '1'`:** centenas de ocorrências (flag provável de **urgência** no legado); o modelo novo **não** tem coluna equivalente — decidir: ignorar, gravar em nota de migração, ou campo opcional futuro (fora do escopo da tabela atual).
- **`Meio = '1'`:** apenas **2** registros no dump analisado (ambiente recente); **`Meio = '0'`** no restante. De-para sugerido para `meio_envio` (valores válidos na app: `email`, `malote_digital`, `pessoalmente`):
  - `'0'` → `NULL` se migrar como **`finalizada` + `ccm`** (sem meio); ou valor default **`email`** se migrar como **`cartorio` + `pendente`** (exige coerência com subtarefas).
  - `'1'` → confirmar significado com o negócio (ex. malote vs e-mail) antes de fixar no SQL.

### Usuário

- **`Usuario`:** **nome livre** (ex.: `Giulia Santana`), não ID. Resolver `criado_por` / `atualizado_por` com **JOIN** à tabela `usuarios` (nome + sobrenome, ou normalização), senão **NULL** + lista de não resolvidos.

### Datas

- **`Data`:** **decisão confirmada:** `created_at` = **`Data`** e `updated_at` = **`Data`** (mesmo instante legado; sem histórico de alteração distinto no sistema antigo).

## Mapeamento de colunas (refinado)

| Legado | Novo | Notas |
|--------|------|--------|
| `IdCrtOficios` | — | Armazenar só no **mapa legado**; `id` = **`IdGenerator` `OFC-` + 26 hex** (30 chars) |
| — | `id` | Obrigatório: `generateUniquePrefixedId(..., 'oficios_cartorio', 'id', 'OFC', 30)` |
| `Processo` | `numero_processo` | Normalizar para formato aceito pela app (20 dígitos / máscara CNJ) |
| (join `processos`) | `processo_id` | `ensureProcess` ou equivalente SQL; NULL se inválido |
| `Ano` | `ano` | Já coerente com `Numero` por ano |
| `Numero` | `numero_sequencial` | Validar unicidade `(ano, numero_sequencial)` após carga |
| `Descricao` | `tipo_oficio` | Até 255 caracteres |
| — | `forma_cumprimento` | **Decisão confirmada:** todos os registros migrados → **`ccm`** |
| — | `status` | **Decisão confirmada:** todos → **`finalizada`** (sem subtarefas; alinhado ao fluxo CCM concluído) |
| `Meio` | `meio_envio` | Com `finalizada` + `ccm`: **`NULL`** (códigos legados `Meio`/`Prioridade` ignorados na coluna nova, podem ir para relatório opcional) |
| `Data` | `created_at`, `updated_at` | Ver acima |
| `Usuario` | `criado_por`, `atualizado_por` | Resolver via `usuarios` ou NULL |

## Subtarefas (`oficios_cartorio_subtarefas`)

- Se todos os registros migrados forem **`finalizada`**, **não** é obrigatório criar subtarefas (alinha ao fluxo “CCM já concluído” na aplicação).
- Se algum registro for migrado como **`pendente` + `cartorio`**, replicar as duas subtarefas como em [`OficiosCartorioService::create`](../../app/services/OficiosCartorioService.php) — só se houver decisão de negócio para reabrir fluxo. Os **IDs das subtarefas** também devem seguir o padrão do projeto via `IdGenerator` com prefixo **`OFS`**, tabela `oficios_cartorio_subtarefas` (ver [`OficiosCartorioSubtarefasRepository::create`](../../app/repositories/OficiosCartorioSubtarefasRepository.php)), não valores inventados em SQL.

## Entregáveis e ordem de execução

1. **Staging** no banco destino: tabela temporária ou schema com colunas do novo modelo + `legacy_id` (`IdCrtOficios`), `_mig_note`.
2. **Script de transformação:** **PHP (CLI)** obrigatório para **cada insert** em `oficios_cartorio`: `IdGenerator::generateUniquePrefixedId` + `ProcessosRepository::ensureProcess` (ou equivalente) para `processo_id` / `numero_processo` quando aplicável. **`OficiosCartorioRepository::insert` usa `NOW()`** nas datas — na migração usar **`INSERT` explícito** com `created_at`/`updated_at` = `Data` legado, ou `INSERT` + `UPDATE` imediato, para respeitar a decisão “data em ambos”. SQL puro só para normalização CNJ auxiliar ou leitura da staging — **não** para gerar IDs finais.
3. **Tabela de mapeamento** `legacy_id → novo_id` populada no mesmo script (para auditoria e suporte).
4. **Pré-validação:** contagem, linhas com processo placeholder, usuários não resolvidos; validar formato dos IDs (`OFC-` + 26 hex) com `REGEXP`/`LIKE`.
5. **Transação** por lote (ex.: N linhas) + backup prévio dos dois bancos.
6. **Pós-validação:** `COUNT(*)` legado vs novo; amostragem de joins com `processos`; **unicidade** de `id` (já garantida pelo gerador + verificação).

## Sugestões de implementação (artefatos e práticas)

### Script CLI dedicado

- **Local sugerido:** `tools/migrate-cartorio-oficios.php` (ou `database/scripts/`) carregando `config/config.php`, `Conection`, `IdGenerator`, `ProcessosRepository`, `OficiosCartorioRepository`.
- **Flags:** `--dry-run` (só lê legado, imprime contadores e erros, **sem** `INSERT`); `--limit=N` para testar com N linhas; `--batch-size=100` para transações; `--source=mysql:...` ou leitura do [`CartorioOficios.sql`](../../CartorioOficios.sql) já importado em tabela temporária `cartorio_oficios_legacy` no destino (evita parser PHP de SQL gigante).

### Idempotência e reexecução

- Antes de inserir, verificar `oficios_cartorio_migracao_legacy` (ou equivalente): se `legacy_id` já existe, **pular** ou **atualizar** conforme política — evita duplicar ao rodar o script duas vezes.
- Opcional: coluna `migrated_at` / `migration_batch` na tabela de mapa.

### Processo CNJ e `ensureProcess`

- Centralizar função **`digitsOnly(Processo) → 20 dígitos`** alinhada ao serviço; se `strlen !== 20`, marcar linha como **erro** no relatório (não inserir ofício sem decisão).
- Para **placeholder** `0000000-00.0000.8.05.0103`, a decisão de produto é pré-requisito (ver pergunta ao usuário abaixo no fluxo de trabalho).

### Usuários (`Usuario` → `criado_por`)

- Query auxiliar: `SELECT id_usuario FROM usuarios WHERE TRIM(CONCAT(nome,' ',sobrenome)) = :nome` com fallback **LIKE** ou tabela de **sinônimos** (acentos, ordem nome/sobrenome).
- Usuários não resolvidos: registrar em **log** e/ou tabela auxiliar no MySQL (sem CSV automático, alinhado à decisão de auditoria).

### Performance (~6k linhas)

- **Transação por lote** (100–500 linhas) para equilibrar rollback e tempo.
- Desativar checagens desnecessárias só se DBA autorizar; para 6k linhas costuma ser desnecessário.

### Segurança e auditoria

- Log em arquivo: `migration-oficios-YYYYMMDD.log` com `legacy_id`, `novo_id`, erros.
- **Não** commitar credenciais; usar mesmas variáveis `.env` / config do app.
- **Decisão confirmada:** **sem** exportação CSV automática; auditoria fica na **tabela de mapeamento** no MySQL (`legacy_id` → `novo_id`, mais colunas opcionais se necessário). Se no futuro for preciso CSV, gerar por `SELECT ... INTO OUTFILE` ou export manual a partir dessa tabela.

### Tabelas relacionadas (escopo)

- **`oficios_cartorio_alocados`:** para histórico `finalizada` + `ccm`, normalmente **não** precisa copiar alocações do legado (não existiam na tabela antiga). Se no futuro for necessário “atribuir” quem criadou o ofício, usar `criado_por` + regra de negócio — documentar como **fora do escopo** da primeira migração.
- **`oficios_cartorio_subtarefas`:** omitir se política for 100% **finalizada** (recomendado para legado).

### Testes de aceitação (checklist)

- [ ] Contagem legado = linhas inseridas + linhas rejeitadas (soma bate).
- [ ] Nenhum `id` fora do padrão `OFC-[A-F0-9]{26}`.
- [ ] Unicidade `(ano, numero_sequencial)` no destino.
- [ ] Amostra de 20 registros conferidos manualmente na UI (`frame.php?page=oficioscartorio`).

## Perguntas em aberto (alinhar com o time)

- **Processo placeholder:** decidido — `processo_id` NULL + `numero_processo` = valor legado quando CNJ inválido (ver secção “Processo (CNJ)”).
- **Status / forma / meio:** decidido — **todos** `finalizada` + `ccm` + `meio_envio` NULL (ver tabela de mapeamento).
- **Auditoria:** decidido — só **banco** (mapa legado), sem CSV automático.

## Decisões de alinhamento (questionário final)

| # | Tema | Decisão |
|---|------|---------|
| 1 | **Primeira execução com INSERT** | **Homolog/staging obrigatório: mesmo código, DSN/config separado, checklist antes de produção.** |
| 2 | **Origem dos dados legados** | **Importar dump para tabela `cartorio_oficios_legacy` no destino; o script PHP lê só essa tabela** (sem PDO remoto nem parser do `.sql` no PHP). |
| 3 | **Conflito `(ano, numero_sequencial)`** | **Assumir que em produção não há sobreposição** (tabela vazia ou já preparada); conflito = erro de preparação a investigar. |
| 4 | **`created_at` / `updated_at`** | **`created_at` = `Data` legado; `updated_at` = `Data` legado** (fidelidade ao passado). |
| 5 | **Rollback** | **Restaurar tabelas** `oficios_cartorio` (+ mapa e relacionadas se aplicável) a partir de **mysqldump pré-migração** — padrão DBA; `DELETE` via mapa permanece opção complementar se necessário. |

## Referências no código

- [`IdGenerator`](../../app/helpers/IdGenerator.php) — geração e unicidade de IDs (`OFC`, 30 caracteres).
- [`OficiosCartorioRepository`](../../app/repositories/OficiosCartorioRepository.php) — insert e ordem de status.
- [`OficiosCartorioService`](../../app/services/OficiosCartorioService.php) — regras CNJ, meios válidos, criação de subtarefas; uso de `OFC` + `IdGenerator` em `create`.
- [id-padrao-banco-dados.md](../00-foundation/id-padrao-banco-dados.md) — norma geral de identificadores.

---

*Documento atualizado após inclusão de [`CartorioOficios.sql`](../../CartorioOficios.sql) na raiz do projeto. Ajuste posterior: IDs obrigatoriamente via [`IdGenerator`](../../app/helpers/IdGenerator.php).*
