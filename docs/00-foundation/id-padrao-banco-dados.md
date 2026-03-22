# Padrão de identificadores (ID) no banco de dados

## Regra obrigatória

**Todo identificador primário (ou equivalente) de tabela no sistema deve seguir o padrão de 30 caracteres:**

- **Formato:** `XXX-NNNNNNNNNNNNNNNNNNNNNNNNNN`
- **Estrutura:** prefixo de **3 letras** + **hífen** + **código** de **26 caracteres** = **30 caracteres** no total.
- **Exemplo:** `EST-A1B2C3D4E5F6G7H8I9J0K1L2M3N4`

## Prefixos por entidade

| Prefixo | Entidade / Tabela        |
|---------|--------------------------|
| EST     | estudantes               |
| USR     | usuarios                 |
| SET     | setores                  |
| USO     | usuario_setor            |
| LOG     | logs_acesso              |
| LGS     | logs_sistema             |
| TNT     | tentativas_login         |
| REC     | recuperacao_senha        |
| HIS     | historico_senha          |
| CLA     | classes                  |
| TRI     | tribunais                |
| COM     | comarcas                 |
| VAR     | varas                    |
| PRC     | processos                |
| CPR     | cartas_recebidas         |
| CPE     | cartas_expedidas         |
| CRS     | cartas_recebidas_subtarefas |
| CES     | cartas_expedidas_subtarefas |
| CRA     | cartas_recebidas_alocados |
| CEA     | cartas_expedidas_alocados |
| CJT     | cejusc_tarefas           |
| CJS     | cejusc_subtarefas        |
| CJA     | cejusc_alocados          |
| GUI     | guias                    |
| GUS     | guias_subtarefas         |
| GUA     | guias_alocados           |
| ALR     | alocacoes_regras         |
| DOC     | documentos               |

Ao criar **novas tabelas**, definir um prefixo de 3 letras único e documentá-lo aqui.

## Implementação

### Helper PHP (recomendado)

A aplicação deve usar o helper **`IdGenerator::generateUniquePrefixedId()`** ao criar novos registros com ID gerado em PHP:

- **Arquivo:** `app/helpers/IdGenerator.php`
- **Método:** `IdGenerator::generateUniquePrefixedId(PDO $connection, string $table, string $column, string $prefix, int $totalLength = 30, int $maxAttempts = 10): string`
- Garante formato **prefixo + hífen + 26 caracteres hex** (30 no total) e **unicidade** na tabela (tentativas até `maxAttempts`).

Exemplo:

```php
require_once __DIR__ . '/../helpers/IdGenerator.php';

$id = IdGenerator::generateUniquePrefixedId($pdo, 'cartas_recebidas', 'id', 'CPR', 30);
```

Repositórios e services que fazem INSERT com ID já utilizam esse helper. Em pontos isolados (ex.: `api/users.php`, `api/documents.php`), usar o mesmo formato manualmente ou carregar o helper e chamar `generateUniquePrefixedId`.

### No banco (MySQL)

- Tipo da coluna: **`CHAR(30)`** (ou `VARCHAR(30)`).
- Comentário na coluna: `COMMENT 'Formato XXX- + 26 chars (regra padrão 30 chars)'`.
- Geração pode ser feita por **trigger** (BEFORE INSERT) ou pela **aplicação** (PHP).

**Exemplo em trigger (MySQL):**

```sql
SET NEW.id = CONCAT('EST-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
```

### Na aplicação (PHP) — formato manual

Quando não for possível usar o helper (ex.: script sem PDO), gerar: prefixo de 3 letras + hífen + 26 caracteres hex (`random_bytes(13)` → 26 hex).

**Exemplo em PHP:**

```php
$id = 'USR-' . strtoupper(bin2hex(random_bytes(13)));
```

## Referências

- Scripts de criação de tabelas: `database/schema.sql`, `docs/sql/*.sql`.
- Esquema e convenções: [docs/10-architecture/database-schema-base.md](../10-architecture/database-schema-base.md).
