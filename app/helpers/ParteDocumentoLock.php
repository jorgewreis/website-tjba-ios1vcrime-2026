<?php
declare(strict_types=1);

require_once __DIR__ . '/ParteDocumentoHelper.php';

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

    /** @param callable():mixed $operation */
    public static function run(PDO $connection, string $type, string $number, callable $operation): mixed
    {
        $type = ParteDocumentoHelper::normalizarTipo($type);
        $number = ParteDocumentoHelper::normalizarNumero($type, $number);
        if ($type === '' || $number === '' || $connection->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return $operation();
        }

        self::$locksHeld ??= new WeakMap();
        if (self::isConnectionUnsafe($connection)) {
            throw new RuntimeException('Conexão indisponível para alteração de documento.');
        }

        $database = $connection->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($database) || $database === '') {
            throw new RuntimeException('Banco de dados indisponível para alteração de documento.');
        }
        $lockName = $type === 'CPF'
            ? 'rei250_' . substr(hash('sha256', $database . ':' . $number), 0, 52)
            : 'rei152_' . substr(hash('sha256', $database . ':' . $type . ':' . $number), 0, 56);

        $held = self::$locksHeld[$connection] ?? [];
        if (isset($held[$lockName])) {
            return $operation();
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
            throw new RuntimeException('Estado do lock documental incerto; conexão isolada.', 0, $failure ?? $releaseError);
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

    /** @param list<array{tipo:string, numero:string}> $documents @param callable():mixed $operation */
    public static function runMany(PDO $connection, array $documents, callable $operation): mixed
    {
        $keys = [];
        foreach ($documents as $document) {
            $type = ParteDocumentoHelper::normalizarTipo((string) ($document['tipo'] ?? ''));
            $number = ParteDocumentoHelper::normalizarNumero($type, (string) ($document['numero'] ?? ''));
            if ($type !== '' && $number !== '') {
                $keys[$type . ':' . $number] = ['tipo' => $type, 'numero' => $number];
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
