<?php
declare(strict_types=1);

require_once __DIR__ . '/ParteDocumentoHelper.php';

final class DocumentoResultadoIncerto extends RuntimeException
{
    public function __construct(public readonly string $estadoGravacao, ?Throwable $previous = null)
    {
        parent::__construct('Resultado da operação ou estado da conexão incerto.', 0, $previous);
    }
}

/**
 * Serializa a verificação e gravação de uma identidade documental no MySQL.
 * O lock CPF usa o mesmo nome do fluxo REI-250 para coordenar os dois fluxos.
 */
final class ParteDocumentoLock
{
    private static ?WeakMap $locksHeld = null;
    private static ?WeakMap $unsafeConnections = null;

    public static function isConnectionUnsafe(PDO $connection): bool
    {
        self::$unsafeConnections ??= new WeakMap();
        return isset(self::$unsafeConnections[$connection]);
    }

    public static function markConnectionUnsafe(PDO $connection): void
    {
        self::$unsafeConnections ??= new WeakMap();
        self::$unsafeConnections[$connection] = true;
    }

    /** Confere os motores sob metadata locks retidos até o encerramento da transação. */
    /** @param list<string> $additionalTables Tabelas efetivamente usadas pelos repositórios. */
    public static function requireTransactionalTables(PDO $connection, array $additionalTables = []): void
    {
        $tables = array_values(array_unique(array_merge(['partes', 'partes_documentos'], $additionalTables)));
        foreach ($tables as $table) {
            if (!is_string($table) || preg_match('/\A[a-zA-Z0-9_]+\z/D', $table) !== 1) {
                throw new InvalidArgumentException('Nome de tabela documental inválido.');
            }
        }
        if (self::isConnectionUnsafe($connection) || !$connection->inTransaction()) {
            throw new LogicException('Alteração de documentos requer transação disponível.');
        }
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') return;
        if ($driver !== 'mysql') throw new RuntimeException('Banco sem suporte transacional para documentos.');
        $timeoutStatement = $connection->query('SELECT @@SESSION.lock_wait_timeout');
        if ($timeoutStatement === false) throw new RuntimeException('Timeout de metadados indisponível.');
        $timeout = $timeoutStatement->fetchColumn();
        $timeoutStatement->closeCursor();
        if (!is_numeric($timeout) || (int) $timeout < 1) throw new RuntimeException('Timeout de metadados inválido.');
        try {
            if ($connection->exec('SET SESSION lock_wait_timeout = ' . min(2, (int) $timeout)) === false) {
                throw new RuntimeException('Não foi possível limitar timeout de metadados.');
            }
        } catch (Throwable $error) {
            self::markConnectionUnsafe($connection);
            throw $error;
        }
        try {
            foreach ($tables as $table) {
                $read = $connection->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
                if ($read === false) throw new RuntimeException('Tabela documental indisponível.');
                $read->closeCursor();
            }
            $caseMode = (string) $connection->query('SELECT @@lower_case_table_names')->fetchColumn();
            if (!in_array($caseMode, ['0', '1', '2'], true)) throw new RuntimeException('Política de nomes de tabelas indisponível.');
            $predicate = $caseMode === '0' ? 'BINARY TABLE_NAME = BINARY ?' : 'BINARY LOWER(TABLE_NAME) = BINARY LOWER(?)';
            $schema = $caseMode === '0' ? 'BINARY TABLE_SCHEMA = BINARY DATABASE()' : 'BINARY LOWER(TABLE_SCHEMA) = BINARY LOWER(DATABASE())';
            $query = $connection->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE ' . $schema . ' AND ' . $predicate . ' AND TABLE_TYPE = ?');
            foreach ($tables as $table) {
                if (!$query->execute([$table, 'BASE TABLE'])) throw new RuntimeException('Motor documental indisponível.');
                $engine = $query->fetchColumn();
                $query->closeCursor();
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                    throw new RuntimeException('Alteração documental exige tabelas InnoDB.');
                }
            }
        } finally {
            try {
                if ($connection->exec('SET SESSION lock_wait_timeout = ' . (int) $timeout) === false) {
                    throw new RuntimeException('Não foi possível restaurar timeout de metadados.');
                }
            } catch (Throwable $error) {
                self::markConnectionUnsafe($connection);
                throw $error;
            }
        }
    }

    public static function commit(PDO $connection, string $operation): void
    {
        try {
            if ($connection->commit()) {
                return;
            }
        } catch (Throwable $error) {
            self::markConnectionUnsafe($connection);
            throw new DocumentoResultadoIncerto('nao_confirmada', $error);
        }

        self::markConnectionUnsafe($connection);
        throw new DocumentoResultadoIncerto('nao_confirmada');
    }

    /** @param callable():mixed $operation */
    public static function run(PDO $connection, string $type, string $number, callable $operation): mixed
    {
        $type = ParteDocumentoHelper::normalizarTipo($type);
        $number = ParteDocumentoHelper::normalizarNumero($type, $number);
        if (self::isConnectionUnsafe($connection)) {
            throw new RuntimeException('Conexão indisponível para alteração de documento.');
        }
        if ($type === '' || $number === '' || $connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return $operation();
        }

        self::$locksHeld ??= new WeakMap();

        $lockName = self::lockName($connection, $type, $number);

        $held = self::$locksHeld[$connection] ?? [];
        if (isset($held[$lockName])) {
            return $operation();
        }
        if ($connection->inTransaction()) {
            throw new LogicException('O lock documental precisa ser adquirido antes da transação externa.');
        }

        try {
            $statement = $connection->prepare('SELECT GET_LOCK(?, 5)');
            $statement->execute([$lockName]);
            $acquired = $statement->fetchColumn();
        } catch (Throwable $error) {
            self::markConnectionUnsafe($connection);
            throw $error;
        }
        if ((int) $acquired !== 1) {
            throw new RuntimeException('Não foi possível obter o lock de identidade documental.');
        }

        $held[$lockName] = true;
        self::$locksHeld[$connection] = $held;
        $result = null;
        $failure = null;
        try {
            $result = $operation();
        } catch (Throwable $error) {
            $failure = $error;
        }

        if ($connection->inTransaction()) {
            self::markConnectionUnsafe($connection);
            throw new DocumentoResultadoIncerto('nao_confirmada', $failure);
        }

        try {
            $statement = $connection->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$lockName]);
            if ((int) $statement->fetchColumn() !== 1) {
                throw new RuntimeException('Não foi possível confirmar a liberação do lock documental.');
            }
        } catch (Throwable $releaseError) {
            self::markConnectionUnsafe($connection);
            unset($held[$lockName]);
            if ($held === []) {
                unset(self::$locksHeld[$connection]);
            } else {
                self::$locksHeld[$connection] = $held;
            }
            if ($failure instanceof DocumentoResultadoIncerto) {
                throw $failure;
            }
            if ($failure === null) {
                throw new DocumentoResultadoIncerto('nao_confirmada', $releaseError);
            }
            throw new RuntimeException('Estado do lock documental incerto; conexão isolada.', 0, $failure);
        }

        unset($held[$lockName]);
        if ($held === []) {
            unset(self::$locksHeld[$connection]);
        } else {
            self::$locksHeld[$connection] = $held;
        }
        if ($failure !== null) {
            throw $failure;
        }
        return $result;
    }

    private static function lockName(PDO $connection, string $type, string $number): string
    {
        $database = $connection->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('Banco de dados indisponível para alteração de documento.');
        }
        if ($type !== 'CPF') {
            $canonical = $connection->prepare('SELECT UPPER(?)');
            $canonical->execute([$number]);
            $number = (string) $canonical->fetchColumn();
        }
        return $type === 'CPF'
            ? 'rei250_' . substr(hash('sha256', $database . ':' . $number), 0, 52)
            : 'rei152_' . substr(hash('sha256', $database . ':' . $type . ':' . $number), 0, 56);
    }

    /** @param list<array{tipo:string, numero:string}> $documents @param callable():mixed $operation */
    public static function runMany(PDO $connection, array $documents, callable $operation): mixed
    {
        if (self::isConnectionUnsafe($connection)) {
            throw new RuntimeException('Conexão indisponível para alteração de documento.');
        }
        $keys = [];
        foreach ($documents as $document) {
            $type = ParteDocumentoHelper::normalizarTipo((string) ($document['tipo'] ?? ''));
            $number = ParteDocumentoHelper::normalizarNumero($type, (string) ($document['numero'] ?? ''));
            if ($type !== '' && $number !== '') {
                $key = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                    ? self::lockName($connection, $type, $number)
                    : $type . ':' . $number;
                $keys[$key] = ['tipo' => $type, 'numero' => $number];
            }
        }
        ksort($keys, SORT_STRING);
        $keys = array_values($keys);
        $run = static function (int $index) use (&$run, $keys, $connection, $operation): mixed {
            if (!isset($keys[$index])) {
                return $operation();
            }
            return self::run($connection, $keys[$index]['tipo'], $keys[$index]['numero'], fn(): mixed => $run($index + 1));
        };
        return $run(0);
    }
}
