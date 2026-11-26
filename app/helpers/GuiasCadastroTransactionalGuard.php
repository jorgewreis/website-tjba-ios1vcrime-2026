<?php
declare(strict_types=1);

/** Assegura que as gravações do fluxo legado participem do rollback. */
final class GuiasCadastroTransactionalGuard
{
    private static ?WeakMap $unsafeConnections = null;

    public static function requireTransactionalTables(PDO $pdo, bool $idempotency = false, bool $documentos = false): void
    {
        self::$unsafeConnections ??= new WeakMap();
        if (isset(self::$unsafeConnections[$pdo])) {
            throw new RuntimeException('Conexão indisponível para nova operação de guia.');
        }
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('Operação de guia requer transação.');
        }
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        // SQLite, usado pelo teste isolado legado, dispõe de rollback transacional.
        if ($driver === 'sqlite') {
            if ($idempotency) throw new RuntimeException('Envio seguro requer MySQL.');
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Operação de guia requer banco transacional compatível.');
        }

        // Ordem fixa e nomes restritos: não aceitar identificadores do usuário.
        $tables = ['classes', 'guias', 'guias_alocados', 'guias_subtarefas', 'partes', 'processos', 'processos_partes'];
        if ($idempotency) $tables[] = 'guia_criacao_idempotencia';
        if ($documentos) $tables[] = 'partes_documentos';
        sort($tables, SORT_STRING);
        $timeoutStatement = $pdo->query('SELECT @@SESSION.lock_wait_timeout');
        if ($timeoutStatement === false) {
            throw new RuntimeException('Não foi possível consultar o timeout de metadados.');
        }
        $timeout = $timeoutStatement->fetchColumn();
        $timeoutStatement->closeCursor();
        if (!is_numeric($timeout) || (int) $timeout < 1) {
            throw new RuntimeException('Timeout de metadados inválido.');
        }
        try {
            if ($pdo->exec('SET SESSION lock_wait_timeout = ' . min(2, (int) $timeout)) === false) {
                throw new RuntimeException('Não foi possível limitar o timeout de metadados.');
            }
        } catch (Throwable $failure) {
            self::$unsafeConnections[$pdo] = true;
            throw $failure;
        }
        $primaryFailure = null;
        try {
            foreach ($tables as $table) {
                // O SELECT mantém o metadata lock até a transação terminar,
                // impedindo ALTER TABLE depois da verificação do engine.
                $statement = $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
                if ($statement === false) throw new RuntimeException('Tabela da guia indisponível.');
                $statement->closeCursor();
            }
            $caseMode = $pdo->query('SELECT @@lower_case_table_names')->fetchColumn();
            if (!in_array((string) $caseMode, ['0', '1', '2'], true)) {
                throw new RuntimeException('Não foi possível verificar a política de nomes de tabelas.');
            }
            // Em modo 0, nomes com caixa distinta podem ser objetos diferentes.
            // Nos modos 1/2 o servidor resolve nomes sem distinguir caixa.
            $tablePredicate = (string) $caseMode === '0'
                ? 'BINARY TABLE_NAME = BINARY ?'
                : 'BINARY LOWER(TABLE_NAME) = BINARY LOWER(?)';
            $schemaPredicate = (string) $caseMode === '0'
                ? 'BINARY TABLE_SCHEMA = BINARY DATABASE()'
                : 'BINARY LOWER(TABLE_SCHEMA) = BINARY LOWER(DATABASE())';
            $statement = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE ' . $schemaPredicate . ' AND ' . $tablePredicate . ' AND TABLE_TYPE = ?');
            if ($statement === false) throw new RuntimeException('Não foi possível consultar engines das tabelas.');
            foreach ($tables as $table) {
                if (!$statement->execute([$table, 'BASE TABLE'])) throw new RuntimeException('Não foi possível verificar engine.');
                $engine = $statement->fetchColumn();
                $statement->closeCursor();
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                    throw new RuntimeException('Operação de guia requer tabelas InnoDB.');
                }
            }
            if ($idempotency) {
                $columns = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLLATION_NAME, IS_NULLABLE, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE ' . $schemaPredicate . ' AND ' . $tablePredicate);
                $columns->execute(['guia_criacao_idempotencia']);
                $found = [];
                foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) $found[$column['COLUMN_NAME']] = $column;
                foreach (['ator_hash','chave','payload_hash'] as $name) {
                    $column = $found[$name] ?? null;
                    if (!$column || $column['DATA_TYPE'] !== 'char' || (int)$column['CHARACTER_MAXIMUM_LENGTH'] !== 64 || $column['COLLATION_NAME'] !== 'ascii_bin' || $column['IS_NULLABLE'] !== 'NO') throw new RuntimeException('Schema de envio seguro incompatível.');
                }
                if (!isset($found['guia_id'], $found['criado_utc'], $found['hash_versao']) || $found['hash_versao']['DATA_TYPE'] !== 'tinyint' || $found['hash_versao']['IS_NULLABLE'] !== 'NO' || $found['guia_id']['IS_NULLABLE'] !== 'NO' || (int)$found['guia_id']['CHARACTER_MAXIMUM_LENGTH'] < 30 || $found['criado_utc']['DATA_TYPE'] !== 'datetime' || (int)$found['criado_utc']['DATETIME_PRECISION'] !== 6 || $found['criado_utc']['IS_NULLABLE'] !== 'NO') throw new RuntimeException('Schema de envio seguro incompatível.');
                $indices = $pdo->prepare('SELECT COLUMN_NAME, SEQ_IN_INDEX, NON_UNIQUE, SUB_PART FROM information_schema.STATISTICS WHERE ' . $schemaPredicate . ' AND ' . $tablePredicate . " AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX");
                $indices->execute(['guia_criacao_idempotencia']);
                $parts = $indices->fetchAll(PDO::FETCH_ASSOC);
                if (count($parts) !== 2 || $parts[0]['COLUMN_NAME'] !== 'ator_hash' || $parts[1]['COLUMN_NAME'] !== 'chave' || (int)$parts[0]['NON_UNIQUE'] !== 0 || (int)$parts[1]['NON_UNIQUE'] !== 0 || $parts[0]['SUB_PART'] !== null || $parts[1]['SUB_PART'] !== null) throw new RuntimeException('Índice de envio seguro incompatível.');
                $unique = $pdo->prepare('SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE ' . $schemaPredicate . ' AND ' . $tablePredicate . ' AND NON_UNIQUE=0');
                $unique->execute(['guia_criacao_idempotencia']);
                if ($unique->fetchAll(PDO::FETCH_COLUMN) !== ['PRIMARY']) throw new RuntimeException('Índices únicos de envio seguro incompatíveis.');

                // Metadata hides triggers without TRIGGER privilege. Require a
                // directly verifiable grant; role-only/partial-revoke setups
                // fail closed pending explicit operational validation.
                $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
                if (!is_string($dbName) || $dbName === '') throw new RuntimeException('Banco de envio seguro indisponível.');
                $scope = '`' . str_replace('`', '``', $dbName) . '`';
                $allowedScopes = ['*.*', $scope . '.*', $scope . '.`guia_criacao_idempotencia`'];
                $grants = $pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN);
                $canInspectTriggers = false;
                foreach ($grants as $grant) {
                    if (preg_match('/^REVOKE\s/i', (string)$grant)) throw new RuntimeException('Privilégios de envio seguro não comprovados.');
                    if (!preg_match('/^GRANT\s+(.+?)\s+ON\s+(.+?)\s+TO\s/i', (string)$grant, $matches)) continue;
                    $privileges = strtoupper($matches[1]);
                    if (in_array($matches[2], $allowedScopes, true) && ($privileges === 'ALL PRIVILEGES' || preg_match('/(?:^|,\s*)TRIGGER(?:\s*,|$)/', $privileges))) $canInspectTriggers = true;
                }
                if (!$canInspectTriggers) throw new RuntimeException('Privilégio para inspecionar triggers não comprovado.');
                $triggerSchema = (string)$caseMode === '0' ? 'BINARY EVENT_OBJECT_SCHEMA = BINARY DATABASE()' : 'BINARY LOWER(EVENT_OBJECT_SCHEMA) = BINARY LOWER(DATABASE())';
                $triggerTable = (string)$caseMode === '0' ? 'BINARY EVENT_OBJECT_TABLE = BINARY ?' : 'BINARY LOWER(EVENT_OBJECT_TABLE) = BINARY LOWER(?)';
                $triggers = $pdo->prepare('SELECT 1 FROM information_schema.TRIGGERS WHERE ' . $triggerSchema . ' AND ' . $triggerTable . ' LIMIT 1');
                $triggers->execute(['guia_criacao_idempotencia']);
                if ($triggers->fetchColumn() !== false) throw new RuntimeException('Triggers de envio seguro não são permitidos.');
            }
        } catch (Throwable $failure) {
            $primaryFailure = $failure;
        } finally {
            try {
                if ($pdo->exec('SET SESSION lock_wait_timeout = ' . (int) $timeout) === false) {
                    throw new RuntimeException('Não foi possível restaurar timeout de metadados.');
                }
            } catch (Throwable $failure) {
                self::$unsafeConnections[$pdo] = true;
                if ($primaryFailure === null) throw $failure;
                error_log('GuiasCadastroTransactionalGuard: restauração de timeout não confirmada.');
            }
        }
        if ($primaryFailure !== null) throw $primaryFailure;
    }
}
