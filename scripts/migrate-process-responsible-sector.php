<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Add the nullable process -> responsible sector reference used by REI-69.
 * Existing processes are intentionally left unassigned; no process data is
 * backfilled or printed by this migration.
 */
final class ProcessResponsibleSectorMigration
{
    private const PROCESS_TABLE = 'processos';
    private const SECTOR_TABLE = 'setores';
    private const PROCESS_COLUMN = 'id_setor_responsavel';
    private const SECTOR_COLUMN = 'id_setor';
    private const INDEX_NAME = 'idx_processos_id_setor_responsavel';
    private const FOREIGN_KEY_NAME = 'fk_processos_setor_responsavel';

    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array{add_column:bool,add_index:bool,add_foreign_key:bool} */
    public function preflight(): array
    {
        $database = trim((string) $this->connection->query('SELECT DATABASE()')->fetchColumn());
        if ($database === '') {
            throw new RuntimeException('Nenhum schema de banco selecionado.');
        }

        $processTable = $this->tableMetadata($database, self::PROCESS_TABLE);
        $sectorTable = $this->tableMetadata($database, self::SECTOR_TABLE);
        if (strcasecmp((string) ($processTable['ENGINE'] ?? ''), 'InnoDB') !== 0
            || strcasecmp((string) ($sectorTable['ENGINE'] ?? ''), 'InnoDB') !== 0) {
            throw new RuntimeException('As tabelas processos e setores precisam usar InnoDB.');
        }

        $sectorColumn = $this->columnMetadata($database, self::SECTOR_TABLE, self::SECTOR_COLUMN);
        $sectorIndexes = $this->indexes($database, self::SECTOR_TABLE);
        $this->assertChar30PrimaryKey($sectorColumn, $sectorIndexes, 'setores.id_setor');

        $processColumn = $this->columnMetadata($database, self::PROCESS_TABLE, self::PROCESS_COLUMN);
        $addColumn = $processColumn === null;
        if ($processColumn !== null) {
            $this->assertCompatibleNullableColumn($processColumn, $sectorColumn);
        }

        $indexes = $this->indexes($database, self::PROCESS_TABLE);
        $targetIndex = null;
        foreach ($indexes as $name => $index) {
            if (strcasecmp((string) $name, self::INDEX_NAME) === 0) {
                $targetIndex = $index;
                break;
            }
        }
        if ($targetIndex !== null
            && !$this->isExactTargetIndex($targetIndex)) {
            throw new RuntimeException('O índice reservado para setor responsável existe com definição incompatível.');
        }
        $hasUsableIndex = false;
        foreach ($indexes as $index) {
            if ($this->isUsableIndex($index)) {
                $hasUsableIndex = true;
                break;
            }
        }

        $foreignKeys = $this->foreignKeys($database);
        $targetForeignKeys = [];
        foreach ($foreignKeys as $foreignKey) {
            if (strcasecmp((string) ($foreignKey['COLUMN_NAME'] ?? ''), self::PROCESS_COLUMN) === 0) {
                $targetForeignKeys[] = $foreignKey;
            }
        }
        if (count($targetForeignKeys) > 1) {
            throw new RuntimeException('Há múltiplas constraints de referência para o setor responsável.');
        }
        $addForeignKey = $targetForeignKeys === [];
        if (!$addForeignKey) {
            $constraintRows = array_values(array_filter(
                $foreignKeys,
                static fn (array $row): bool => $row['CONSTRAINT_NAME'] === $targetForeignKeys[0]['CONSTRAINT_NAME']
            ));
            if (count($constraintRows) !== 1) {
                throw new RuntimeException('A constraint de setor responsável existente é composta e incompatível.');
            }
            $this->assertCompatibleForeignKey($targetForeignKeys[0], $database);
        }

        return [
            'add_column' => $addColumn,
            'add_index' => !$hasUsableIndex,
            'add_foreign_key' => $addForeignKey,
        ];
    }

    /** @return array{add_column:bool,add_index:bool,add_foreign_key:bool} */
    public function migrate(bool $dryRun = false): array
    {
        $plan = $this->preflight();
        if ($dryRun) {
            echo 'Preflight estrutural passou; nenhuma alteração executada.' . PHP_EOL;
            echo 'Coluna: ' . ($plan['add_column'] ? 'seria adicionada' : 'compatível') . PHP_EOL;
            echo 'Índice: ' . ($plan['add_index'] ? 'seria adicionado' : 'compatível') . PHP_EOL;
            echo 'Chave estrangeira: ' . ($plan['add_foreign_key'] ? 'seria validada e adicionada com ALGORITHM=COPY; pode bloquear escritas enquanto reconstrói a tabela' : 'compatível') . PHP_EOL;
            echo 'Dry run não verifica órfãos nem comprova execução do DDL.' . PHP_EOL;
            return $plan;
        }

        // MySQL DDL commits implicitly. Each step is additive and can be safely
        // retried; a failure can leave a compatible prefix for the next run.
        if ($plan['add_column']) {
            $this->connection->exec(
                'ALTER TABLE `processos` ADD COLUMN `id_setor_responsavel` '
                . 'CHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, ALGORITHM=INSTANT'
            );
            echo 'Coluna nullable id_setor_responsavel adicionada; registros existentes permanecem sem atribuição.' . PHP_EOL;
        }

        if ($plan['add_index']) {
            $this->connection->exec(
                'ALTER TABLE `processos` ADD INDEX `idx_processos_id_setor_responsavel` (`id_setor_responsavel`), '
                . 'ALGORITHM=INPLACE, LOCK=NONE'
            );
            echo 'Índice para a consulta de setor responsável adicionado.' . PHP_EOL;
        }

        if ($plan['add_foreign_key']) {
            if ($this->hasOrphanSectorReference()) {
                throw new RuntimeException('Há referências a setores inexistentes; nenhuma constraint foi criada.');
            }
            $foreignKeyChecks = (int) $this->connection->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn();
            if ($foreignKeyChecks !== 1) {
                throw new RuntimeException('foreign_key_checks da sessão precisa estar habilitado antes da migração.');
            }
            // Keep checks enabled so InnoDB validates rows while rebuilding the
            // table. COPY blocks concurrent DML during validation and closes
            // the orphan-insert race between preflight and constraint creation.
            $this->connection->exec(
                'ALTER TABLE `processos` ADD CONSTRAINT `fk_processos_setor_responsavel` '
                . 'FOREIGN KEY (`id_setor_responsavel`) REFERENCES `setores` (`id_setor`) '
                . 'ON DELETE RESTRICT ON UPDATE RESTRICT, ALGORITHM=COPY, LOCK=SHARED'
            );
            echo 'Chave estrangeira validada e criada com verificações habilitadas; DDL COPY permite leituras e bloqueia escritas.' . PHP_EOL;
        }

        $remaining = $this->preflight();
        if (in_array(true, $remaining, true)) {
            throw new RuntimeException('A verificação posterior encontrou itens de migração pendentes.');
        }
        echo 'Migração REI-69 concluída; nenhuma atribuição foi inferida ou aplicada.' . PHP_EOL;
        return $remaining;
    }

    private function tableMetadata(string $database, string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND TABLE_TYPE = \'BASE TABLE\''
        );
        $statement->execute([':schema' => $database, ':table' => $table]);
        $metadata = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($metadata)) {
            throw new RuntimeException('Tabela necessária ausente: ' . $table . '.');
        }
        return $metadata;
    }

    private function columnMetadata(string $database, string $table, string $column): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH, '
            . 'CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_KEY '
            . 'FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $statement->execute([':schema' => $database, ':table' => $table, ':column' => $column]);
        $metadata = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($metadata) ? $metadata : null;
    }

    private function assertChar30PrimaryKey(?array $column, array $indexes, string $label): void
    {
        $primaryIndex = $indexes['PRIMARY'] ?? null;
        if ($column === null
            || strtolower((string) $column['DATA_TYPE']) !== 'char'
            || (int) $column['CHARACTER_MAXIMUM_LENGTH'] !== 30
            || $column['IS_NULLABLE'] !== 'NO'
            || $column['COLUMN_KEY'] !== 'PRI'
            || strtolower((string) $column['CHARACTER_SET_NAME']) !== 'utf8mb4'
            || strtolower((string) $column['COLLATION_NAME']) !== 'utf8mb4_unicode_ci'
            || $primaryIndex === null
            || (int) $primaryIndex['non_unique'] !== 0
            || count($primaryIndex['columns']) !== 1
            || strcasecmp((string) $primaryIndex['columns'][0], self::SECTOR_COLUMN) !== 0
            || $primaryIndex['sub_parts'] !== [null]) {
            throw new RuntimeException($label . ' não corresponde ao contrato CHAR(30) utf8mb4_unicode_ci e PRIMARY KEY.');
        }
    }

    private function assertCompatibleNullableColumn(array $column, array $sectorColumn): void
    {
        foreach (['DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH', 'CHARACTER_SET_NAME', 'COLLATION_NAME'] as $key) {
            if (strcasecmp((string) ($column[$key] ?? ''), (string) ($sectorColumn[$key] ?? '')) !== 0) {
                throw new RuntimeException('processos.id_setor_responsavel tem tipo ou collation incompatível.');
            }
        }
        if ($column['IS_NULLABLE'] !== 'YES' || $column['COLUMN_DEFAULT'] !== null) {
            throw new RuntimeException('processos.id_setor_responsavel precisa ser nullable e sem valor padrão.');
        }
    }

    /** @return array<string,array{non_unique:int,columns:list<?string>,sub_parts:list<?int>}> */
    private function indexes(string $database, string $table): array
    {
        $statement = $this->connection->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART '
            . 'FROM INFORMATION_SCHEMA.STATISTICS '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $statement->execute([':schema' => $database, ':table' => $table]);
        $indexes = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = (string) $row['INDEX_NAME'];
            $indexes[$name] ??= ['non_unique' => (int) $row['NON_UNIQUE'], 'columns' => [], 'sub_parts' => []];
            $indexes[$name]['columns'][] = $row['COLUMN_NAME'] === null ? null : (string) $row['COLUMN_NAME'];
            $indexes[$name]['sub_parts'][] = $row['SUB_PART'] === null ? null : (int) $row['SUB_PART'];
        }
        return $indexes;
    }

    private function isUsableIndex(array $index): bool
    {
        return (int) $index['non_unique'] === 1
            && strcasecmp((string) ($index['columns'][0] ?? ''), self::PROCESS_COLUMN) === 0
            && ($index['sub_parts'][0] ?? null) === null;
    }

    private function isExactTargetIndex(array $index): bool
    {
        return (int) $index['non_unique'] === 1
            && count($index['columns']) === 1
            && strcasecmp((string) ($index['columns'][0] ?? ''), self::PROCESS_COLUMN) === 0
            && $index['sub_parts'] === [null];
    }

    /** @return list<array<string,string>> */
    private function foreignKeys(string $database): array
    {
        $statement = $this->connection->prepare(
            'SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, '
            . 'r.DELETE_RULE, r.UPDATE_RULE '
            . 'FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k '
            . 'JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r '
            . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            . 'AND r.TABLE_NAME = k.TABLE_NAME '
            . 'WHERE k.CONSTRAINT_SCHEMA = :schema AND k.TABLE_NAME = :table '
            . 'AND k.REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $statement->execute([':schema' => $database, ':table' => self::PROCESS_TABLE]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertCompatibleForeignKey(array $foreignKey, string $database): void
    {
        $lowerCaseTableNames = (int) $this->connection->query('SELECT @@lower_case_table_names')->fetchColumn();
        if (!in_array($lowerCaseTableNames, [0, 1, 2], true)) {
            throw new RuntimeException('Modo de nomes de tabelas não reconhecido.');
        }
        $matchesIdentifier = static fn (string $actual, string $expected): bool => $lowerCaseTableNames === 0
            ? $actual === $expected
            : strcasecmp($actual, $expected) === 0;
        if (!$matchesIdentifier((string) ($foreignKey['REFERENCED_TABLE_SCHEMA'] ?? ''), $database)
            || !$matchesIdentifier((string) ($foreignKey['REFERENCED_TABLE_NAME'] ?? ''), self::SECTOR_TABLE)
            || strcasecmp((string) ($foreignKey['REFERENCED_COLUMN_NAME'] ?? ''), self::SECTOR_COLUMN) !== 0
            || !in_array(strtoupper($foreignKey['DELETE_RULE']), ['RESTRICT', 'NO ACTION'], true)
            || !in_array(strtoupper($foreignKey['UPDATE_RULE']), ['RESTRICT', 'NO ACTION'], true)) {
            throw new RuntimeException('A referência existente de setor responsável não corresponde à política esperada.');
        }
    }

    private function hasOrphanSectorReference(): bool
    {
        $statement = $this->connection->query(
            'SELECT 1 FROM `processos` p LEFT JOIN `setores` s '
            . 'ON s.`id_setor` = p.`id_setor_responsavel` '
            . 'WHERE p.`id_setor_responsavel` IS NOT NULL AND s.`id_setor` IS NULL LIMIT 1'
        );
        return $statement->fetchColumn() !== false;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    require_once dirname(__DIR__) . '/app/helpers/Conection.php';
    try {
        $arguments = array_slice($argv ?? [], 1);
        $unknownArguments = array_diff($arguments, ['--dry-run', '--apply']);
        if ($unknownArguments !== [] || (in_array('--dry-run', $arguments, true) && in_array('--apply', $arguments, true))) {
            throw new InvalidArgumentException('Use no argumento ou --dry-run para preflight; use --apply para executar o DDL.');
        }
        // The safe default is preflight only. Schema mutation requires an explicit flag.
        $dryRun = !in_array('--apply', $arguments, true);
        (new ProcessResponsibleSectorMigration(Conection::getInstance()->getConnection()))->migrate($dryRun);
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Erro na migração REI-69: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
