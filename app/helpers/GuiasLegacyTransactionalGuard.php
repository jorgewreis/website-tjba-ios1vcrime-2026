<?php
declare(strict_types=1);

/** Assegura que as gravações do fluxo legado participem do rollback. */
final class GuiasLegacyTransactionalGuard
{
    private static ?WeakMap $unsafeConnections = null;

    public static function requireTransactionalTables(PDO $pdo): void
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
            return;
        }
        if ($driver !== 'mysql') {
            throw new RuntimeException('Operação de guia requer banco transacional compatível.');
        }

        // Ordem fixa e nomes restritos: não aceitar identificadores do usuário.
        $tables = ['classes', 'guia', 'guia_subtarefas', 'guiasUsuarios', 'partes', 'processo_prioridades', 'processos', 'processos_partes'];
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
        } finally {
            try {
                if ($pdo->exec('SET SESSION lock_wait_timeout = ' . (int) $timeout) === false) {
                    throw new RuntimeException('Não foi possível restaurar timeout de metadados.');
                }
            } catch (Throwable $failure) {
                self::$unsafeConnections[$pdo] = true;
                throw $failure;
            }
        }
    }
}
