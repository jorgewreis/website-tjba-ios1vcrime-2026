<?php
declare(strict_types=1);

/** Assegura que as gravações do fluxo legado participem do rollback. */
final class PasswordResetTransactionalGuard
{
    private static ?WeakMap $unsafeConnections = null;

    public static function requireTransactionalTables(PDO $pdo, bool $includeHistory = true, bool $requireHistory = true): void
    {
        self::$unsafeConnections ??= new WeakMap();
        if (isset(self::$unsafeConnections[$pdo])) {
            throw new RuntimeException('Conexão indisponível para nova operação de recuperação de senha.');
        }
        if (!$pdo->inTransaction()) {
            throw new RuntimeException('Operação de recuperação de senha requer transação.');
        }
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        // SQLite, usado pelo teste isolado legado, dispõe de rollback transacional.
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Operação de recuperação de senha requer banco transacional compatível.');
        }

        $requiredTables = ['recuperacao_senha', 'usuarios'];
        self::withBoundedMetadataWait($pdo, static function () use ($pdo, $requiredTables, $includeHistory, $requireHistory): void {
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

            $tables = $requiredTables;
            $metadata = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE ' . $schemaPredicate . ' AND ' . $tablePredicate . ' AND TABLE_TYPE = ?');
            if ($metadata === false) throw new RuntimeException('Não foi possível consultar engines das tabelas.');
            if ($includeHistory) {
                if (!$metadata->execute(['historico_senha', 'BASE TABLE'])) {
                    throw new RuntimeException('Não foi possível verificar a tabela de histórico de senhas.');
                }
                $historyEngine = $metadata->fetchColumn();
                $metadata->closeCursor();
                if ($historyEngine === false) {
                    if ($requireHistory) {
                        throw new RuntimeException('A tabela de histórico de senhas é obrigatória para esta operação.');
                    }
                } else {
                    $tables[] = 'historico_senha';
                }
            }

            foreach ($tables as $table) {
                // O SELECT mantém o metadata lock até a transação terminar,
                // impedindo ALTER TABLE depois da verificação do engine.
                $statement = $pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
                if ($statement === false) throw new RuntimeException('Tabela da recuperação de senha indisponível.');
                $statement->closeCursor();
            }

            foreach ($tables as $table) {
                if (!$metadata->execute([$table, 'BASE TABLE'])) throw new RuntimeException('Não foi possível verificar engine.');
                $engine = $metadata->fetchColumn();
                $metadata->closeCursor();
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                    throw new RuntimeException('Operação de recuperação de senha requer tabelas InnoDB.');
                }
            }
        });
    }

    /** Normaliza o nome conforme a política efetiva do servidor. */
    public static function normalizeMetadataTableName(string $tableName, string $caseMode): string
    {
        if (!in_array($caseMode, ['0', '1', '2'], true)) throw new RuntimeException('Modo de nomes de tabela inválido.');
        return $caseMode === '0' ? $tableName : strtolower($tableName);
    }

    /** Limita operações que podem esperar metadados e preserva o erro primário. */
    public static function withBoundedMetadataWait(PDO $pdo, callable $operation): mixed
    {
        self::$unsafeConnections ??= new WeakMap();
        if (isset(self::$unsafeConnections[$pdo])) {
            throw new RuntimeException('Conexão indisponível para nova operação de recuperação de senha.');
        }
        $statement = $pdo->query('SELECT @@SESSION.lock_wait_timeout');
        if ($statement === false) throw new RuntimeException('Timeout de metadados indisponível.');
        $timeout = $statement->fetchColumn();
        $statement->closeCursor();
        if (!is_numeric($timeout) || (int)$timeout < 1) throw new RuntimeException('Timeout de metadados inválido.');
        try {
            if ($pdo->exec('SET SESSION lock_wait_timeout = ' . min(2, (int)$timeout)) === false) throw new RuntimeException('Não foi possível limitar o timeout.');
        } catch (Throwable $failure) {
            self::$unsafeConnections[$pdo] = true;
            throw $failure;
        }
        $primary = null;
        $result = null;
        try { $result = $operation(); } catch (Throwable $failure) { $primary = $failure; }
        try {
            if ($pdo->exec('SET SESSION lock_wait_timeout = ' . (int)$timeout) === false) throw new RuntimeException('Não foi possível restaurar timeout de metadados.');
        } catch (Throwable $restoreFailure) {
            self::$unsafeConnections[$pdo] = true;
            if ($primary === null) throw $restoreFailure;
            error_log('PasswordResetTransactionalGuard: falha adicional ao restaurar timeout de metadados.');
        }
        if ($primary !== null) throw $primary;
        return $result;
    }
}
