<?php
declare(strict_types=1);

/** Aplica e verifica a migration da reserva idempotente de guias. */
final class GuiasIdempotenciaMigration
{
    private const TABLE = 'guia_criacao_idempotencia';

    /** Retorna true quando a tabela foi criada e false quando já era válida. */
    public static function apply(PDO $pdo, string $sqlPath): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Migration de envio seguro de guias requer MySQL.');
        }
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('Banco de dados indisponível para migration de guias.');
        }

        $existing = self::findTable($pdo);
        if ($existing === null) {
            $sql = file_get_contents($sqlPath);
            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException('SQL da migration de guias ausente ou vazio.');
            }
            $pdo->exec($sql);
        }

        self::verifyTable($pdo);
        return $existing === null;
    }

    private static function findTable(PDO $pdo): ?array
    {
        $query = $pdo->prepare(
            'SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = LOWER(?)'
        );
        $query->execute([self::TABLE]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) return null;
        if (count($rows) !== 1 || (string)$rows[0]['TABLE_NAME'] !== self::TABLE || $rows[0]['TABLE_TYPE'] !== 'BASE TABLE') {
            throw new RuntimeException('Migration recusada: objeto homônimo incompatível para guias.');
        }
        return $rows[0];
    }

    private static function verifyTable(PDO $pdo): void
    {
        $table = self::findTable($pdo);
        if ($table === null || strcasecmp((string)$table['ENGINE'], 'InnoDB') !== 0) {
            throw new RuntimeException('Migration recusada: tabela de idempotência precisa ser InnoDB.');
        }

        $columnsQuery = $pdo->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_SET_NAME, '
            . 'COLLATION_NAME, IS_NULLABLE, DATETIME_PRECISION FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $columnsQuery->execute([self::TABLE]);
        $columns = [];
        foreach ($columnsQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[strtolower((string)$column['COLUMN_NAME'])] = $column;
        }
        $expectedNames = ['ator_hash', 'chave', 'payload_hash', 'hash_versao', 'guia_id', 'criado_utc'];
        if (count($columns) !== count($expectedNames) || array_diff($expectedNames, array_keys($columns)) !== []) {
            throw new RuntimeException('Migration recusada: colunas da tabela de idempotência incompatíveis.');
        }
        foreach (['ator_hash', 'chave', 'payload_hash'] as $name) {
            $column = $columns[$name];
            if (strtolower((string)$column['DATA_TYPE']) !== 'char' || (int)$column['CHARACTER_MAXIMUM_LENGTH'] !== 64
                || strtolower((string)$column['CHARACTER_SET_NAME']) !== 'ascii' || strtolower((string)$column['COLLATION_NAME']) !== 'ascii_bin'
                || $column['IS_NULLABLE'] !== 'NO') {
                throw new RuntimeException('Migration recusada: coluna hash da tabela de idempotência incompatível.');
            }
        }
        $version = $columns['hash_versao'];
        if (strtolower((string)$version['COLUMN_TYPE']) !== 'tinyint unsigned' || $version['IS_NULLABLE'] !== 'NO') {
            throw new RuntimeException('Migration recusada: versão da tabela de idempotência incompatível.');
        }
        $guideId = $columns['guia_id'];
        if (strtolower((string)$guideId['DATA_TYPE']) !== 'varchar' || (int)$guideId['CHARACTER_MAXIMUM_LENGTH'] !== 64
            || strtolower((string)$guideId['CHARACTER_SET_NAME']) !== 'ascii' || strtolower((string)$guideId['COLLATION_NAME']) !== 'ascii_bin'
            || $guideId['IS_NULLABLE'] !== 'NO') {
            throw new RuntimeException('Migration recusada: identificador da guia incompatível.');
        }
        $createdAt = $columns['criado_utc'];
        if (strtolower((string)$createdAt['DATA_TYPE']) !== 'datetime' || (int)$createdAt['DATETIME_PRECISION'] !== 6
            || $createdAt['IS_NULLABLE'] !== 'NO') {
            throw new RuntimeException('Migration recusada: timestamp da tabela de idempotência incompatível.');
        }

        $indicesQuery = $pdo->prepare(
            'SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        $indicesQuery->execute([self::TABLE]);
        $indices = $indicesQuery->fetchAll(PDO::FETCH_ASSOC);
        $primary = array_values(array_filter($indices, static fn(array $index): bool => $index['INDEX_NAME'] === 'PRIMARY'));
        $expiry = array_values(array_filter($indices, static fn(array $index): bool => $index['INDEX_NAME'] === 'idx_guia_idempotencia_expira'));
        $uniqueNames = [];
        foreach ($indices as $index) if ((int)$index['NON_UNIQUE'] === 0) $uniqueNames[(string)$index['INDEX_NAME']] = true;
        if (count($primary) !== 2 || $primary[0]['COLUMN_NAME'] !== 'ator_hash' || $primary[1]['COLUMN_NAME'] !== 'chave'
            || (int)$primary[0]['NON_UNIQUE'] !== 0 || (int)$primary[1]['NON_UNIQUE'] !== 0
            || $primary[0]['SUB_PART'] !== null || $primary[1]['SUB_PART'] !== null
            || count($expiry) !== 1 || $expiry[0]['COLUMN_NAME'] !== 'criado_utc' || (int)$expiry[0]['NON_UNIQUE'] !== 1
            || $expiry[0]['SUB_PART'] !== null || array_keys($uniqueNames) !== ['PRIMARY']) {
            throw new RuntimeException('Migration recusada: índices da tabela de idempotência incompatíveis.');
        }

        $trigger = $pdo->prepare(
            'SELECT 1 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ? LIMIT 1'
        );
        $trigger->execute([self::TABLE]);
        if ($trigger->fetchColumn() !== false) {
            throw new RuntimeException('Migration recusada: tabela de idempotência não pode ter triggers.');
        }
    }
}
