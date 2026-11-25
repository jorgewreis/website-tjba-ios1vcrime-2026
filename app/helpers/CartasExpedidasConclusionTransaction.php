<?php
declare(strict_types=1);

/** Prevent reuse after an uncertain transaction outcome within this request. */
final class CartasExpedidasConclusionTransaction
{
    private static ?WeakMap $unsafe = null;
    public static function markUnsafe(PDO $connection): void
    {
        self::$unsafe ??= new WeakMap();
        self::$unsafe[$connection] = true;
    }
    public static function assertUsable(PDO $connection): void
    {
        if (self::$unsafe !== null && isset(self::$unsafe[$connection])) {
            throw new RuntimeException('ConexÃ£o indisponÃ­vel apÃ³s resultado transacional incerto.');
        }
    }
    /** Acquire metadata locks before checking engines; retain them until commit/rollback. */
    public static function requireInnoDb(PDO $pdo): void
    {
        self::assertUsable($pdo);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || !$pdo->inTransaction()) {
            throw new RuntimeException('Operação requer transação MySQL com tabelas InnoDB.');
        }
        $query = static function (string $sql, array $params = []) use ($pdo): PDOStatement {
            $st = $pdo->prepare($sql);
            if ($st === false || !$st->execute($params)) throw new RuntimeException('Pré-requisito transacional não confirmado.');
            return $st;
        };
        $st = $query('SELECT @@SESSION.lock_wait_timeout');
        $previous = $st->fetchColumn(); $st->closeCursor();
        if (!ctype_digit((string) $previous) || (int) $previous < 1) throw new RuntimeException('Timeout de metadados desconhecido.');
        try {
            if ($pdo->exec('SET SESSION lock_wait_timeout = ' . min(2, (int) $previous)) === false) throw new RuntimeException('Timeout não confirmado.');
            foreach (['cartas_expedidas', 'cartas_expedidas_subtarefas', 'dashboard_conclusoes'] as $table) {
                $query('SELECT 1 FROM `' . $table . '` LIMIT 0')->closeCursor();
            }
            $st = $query('SELECT @@lower_case_table_names');
            $case = $st->fetchColumn(); $st->closeCursor();
            if (!in_array((string) $case, ['0','1','2'], true)) throw new RuntimeException('Política de nomes desconhecida.');
            $predicate = (string) $case === '0'
                ? 'BINARY TABLE_SCHEMA = BINARY DATABASE() AND BINARY TABLE_NAME = BINARY ?'
                : 'BINARY LOWER(TABLE_SCHEMA) = BINARY LOWER(DATABASE()) AND BINARY LOWER(TABLE_NAME) = BINARY LOWER(?)';
            foreach (['cartas_expedidas', 'cartas_expedidas_subtarefas', 'dashboard_conclusoes'] as $table) {
                $st = $query('SELECT ENGINE FROM information_schema.TABLES WHERE ' . $predicate . ' AND TABLE_TYPE = ?', [$table, 'BASE TABLE']);
                $engine = $st->fetchColumn(); $st->closeCursor();
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) throw new RuntimeException('Operação requer tabelas InnoDB disponíveis.');
            }
        } finally {
            try {
                if ($pdo->exec('SET SESSION lock_wait_timeout = ' . (int) $previous) === false) throw new RuntimeException('Restauração do timeout não confirmada.');
            } catch (Throwable $failure) {
                self::markUnsafe($pdo);
                throw new RuntimeException('Conexão indisponível após falha ao restaurar timeout.', 0, $failure);
            }
        }
    }

}
