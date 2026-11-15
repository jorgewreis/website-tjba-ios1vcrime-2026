<?php
declare(strict_types=1);

final class LoginRateLimiter
{
    private const TABLE = 'login_rate_limits';

    private PDO $pdo;
    private bool $storageChecked = false;
    private bool $storageReady = false;
    private string $scopePrefix;

    public function __construct(PDO $pdo, string $scopePrefix = '')
    {
        $this->pdo = $pdo;
        $scopePrefix = strtolower(trim($scopePrefix));
        if ($scopePrefix !== '' && !preg_match('/^[a-z][a-z0-9_]{0,15}$/', $scopePrefix)) {
            throw new InvalidArgumentException('Prefixo de escopo de rate limit inválido.');
        }
        $this->scopePrefix = $scopePrefix;
    }

    /**
     * @return array{allowed:bool,message:?string,retryAfter:int}
     */
    public function canAttempt(string $cpf, string $ip): array
    {
        if (!$this->ensureStorage()) {
            return $this->storageUnavailable();
        }

        $lockedUntil = $this->getMaximumLockedUntil($this->buildScopes($cpf, $ip));
        if ($lockedUntil === null) {
            return ['allowed' => true, 'message' => null, 'retryAfter' => 0];
        }

        $retryAfter = max(1, strtotime($lockedUntil) - time());
        return [
            'allowed' => false,
            'message' => $this->buildLockedMessage($retryAfter),
            'retryAfter' => $retryAfter,
        ];
    }

    /**
     * @return array{allowed:bool,message:?string,retryAfter:int}
     */
    public function registerFailure(string $cpf, string $ip): array
    {
        if (!$this->ensureStorage()) {
            return $this->storageUnavailable();
        }

        $scopes = $this->buildScopes($cpf, $ip);
        $retryAfter = 0;

        foreach ($scopes as $scope) {
            $retryAfter = max($retryAfter, $this->registerFailureForScope($scope['type'], $scope['value']));
        }

        if ($retryAfter > 0) {
            return [
                'allowed' => false,
                'message' => $this->buildLockedMessage($retryAfter),
                'retryAfter' => $retryAfter,
            ];
        }

        return ['allowed' => true, 'message' => null, 'retryAfter' => 0];
    }

    public function clear(string $cpf, string $ip): void
    {
        if (!$this->ensureStorage()) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE (scope_type = ? AND scope_value = ?) OR (scope_type = ? AND scope_value = ?)'
        );

        $normalizedCpf = $this->normalizeCpfScope($cpf);
        $stmt->execute([$this->scopeType('ip'), $ip, $this->scopeType('cpf'), $normalizedCpf]);
    }

    public function isStorageAvailable(): bool
    {
        return $this->ensureStorage();
    }

    private function ensureStorage(): bool
    {
        if ($this->storageChecked) {
            return $this->storageReady;
        }

        $this->storageChecked = true;

        try {
            $this->pdo->query('SELECT scope_type, scope_value, attempt_count, lock_level, window_started_at, last_attempt_at, locked_until FROM ' . self::TABLE . ' LIMIT 0');

            $this->storageReady = true;
        } catch (Throwable $e) {
            error_log('LoginRateLimiter: armazenamento indisponivel; login bloqueado: ' . $e->getMessage());
            $this->storageReady = false;
        }

        return $this->storageReady;
    }

    /**
     * @param array<int,array{type:string,value:string}> $scopes
     */
    private function getMaximumLockedUntil(array $scopes): ?string
    {
        $maxLockedUntil = null;

        foreach ($scopes as $scope) {
            $row = $this->findScope($scope['type'], $scope['value']);
            if ($row === null) {
                continue;
            }

            $lockedUntil = (string) ($row['locked_until'] ?? '');
            if ($lockedUntil === '' || strtotime($lockedUntil) === false || strtotime($lockedUntil) <= time()) {
                continue;
            }

            if ($maxLockedUntil === null || strtotime($lockedUntil) > strtotime($maxLockedUntil)) {
                $maxLockedUntil = $lockedUntil;
            }
        }

        return $maxLockedUntil;
    }

    private function registerFailureForScope(string $type, string $value): int
    {
        $this->pdo->beginTransaction();

        try {
            // O índice único resolve criações concorrentes. O lock explícito que
            // segue serializa todas as leituras e atualizações do mesmo escopo.
            $createStmt = $this->pdo->prepare(
                'INSERT IGNORE INTO ' . self::TABLE . ' (
                    id_rate_limit, scope_type, scope_value, attempt_count, lock_level,
                    window_started_at, last_attempt_at, locked_until, created_at, updated_at
                 ) VALUES (?, ?, ?, 1, 0, NOW(), NOW(), NULL, NOW(), NOW())'
            );
            $createStmt->execute([$this->generateId('RLT'), $type, $value]);
            $created = $createStmt->rowCount() === 1;

            $row = $this->findScope($type, $value, true);
            if ($row === null) {
                throw new RuntimeException('Não foi possível obter o escopo do rate limit.');
            }

            if ($created) {
                $this->pdo->commit();
                return 0;
            }

            $now = new DateTimeImmutable('now');
            $windowStartedAt = (string) ($row['window_started_at'] ?? '');
            $lastAttemptAt = (string) ($row['last_attempt_at'] ?? '');
            $lockedUntil = (string) ($row['locked_until'] ?? '');
            $attemptCount = (int) ($row['attempt_count'] ?? 0);
            $lockLevel = (int) ($row['lock_level'] ?? 0);

            if ($lastAttemptAt !== '' && strtotime($lastAttemptAt) !== false && (time() - strtotime($lastAttemptAt)) > 86400) {
                $lockLevel = 0;
            }

            if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
                $this->pdo->commit();
                return max(1, strtotime($lockedUntil) - time());
            }

            if ($windowStartedAt === '' || strtotime($windowStartedAt) === false || (time() - strtotime($windowStartedAt)) > LOGIN_RATE_LIMIT_WINDOW_SECONDS) {
                $attemptCount = 0;
                $windowStartedAt = $now->format('Y-m-d H:i:s');
            }

            $attemptCount++;

            if ($attemptCount >= LOGIN_RATE_LIMIT_MAX_ATTEMPTS) {
                $lockLevel++;
                $lockSeconds = min(
                    LOGIN_RATE_LIMIT_BASE_LOCK_SECONDS * (2 ** max(0, $lockLevel - 1)),
                    LOGIN_RATE_LIMIT_MAX_LOCK_SECONDS
                );
                $lockedUntil = $now->modify('+' . $lockSeconds . ' seconds')->format('Y-m-d H:i:s');

                $stmt = $this->pdo->prepare(
                    'UPDATE ' . self::TABLE . '
                        SET attempt_count = 0,
                            lock_level = ?,
                            window_started_at = NOW(),
                            last_attempt_at = NOW(),
                            locked_until = ?,
                            updated_at = NOW()
                      WHERE scope_type = ? AND scope_value = ?'
                );
                $stmt->execute([$lockLevel, $lockedUntil, $type, $value]);

                $this->pdo->commit();
                return $lockSeconds;
            }

            $stmt = $this->pdo->prepare(
                'UPDATE ' . self::TABLE . '
                    SET attempt_count = ?,
                        lock_level = ?,
                        window_started_at = ?,
                        last_attempt_at = NOW(),
                        locked_until = NULL,
                        updated_at = NOW()
                  WHERE scope_type = ? AND scope_value = ?'
            );
            $stmt->execute([$attemptCount, $lockLevel, $windowStartedAt, $type, $value]);

            $this->pdo->commit();
            return 0;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findScope(string $type, string $value, bool $forUpdate = false): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT scope_type, scope_value, attempt_count, lock_level, window_started_at, last_attempt_at, locked_until
               FROM ' . self::TABLE . '
              WHERE scope_type = ? AND scope_value = ?
              LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$type, $value]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @return array<int,array{type:string,value:string}>
     */
    private function buildScopes(string $cpf, string $ip): array
    {
        return [
            ['type' => $this->scopeType('ip'), 'value' => trim($ip) !== '' ? trim($ip) : '0.0.0.0'],
            ['type' => $this->scopeType('cpf'), 'value' => $this->normalizeCpfScope($cpf)],
        ];
    }

    private function scopeType(string $type): string
    {
        return $this->scopePrefix === '' ? $type : $this->scopePrefix . '_' . $type;
    }

    private function normalizeCpfScope(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        return $digits !== '' ? $digits : 'anon';
    }

    private function generateId(string $prefix): string
    {
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(13)));
    }

    private function buildLockedMessage(int $retryAfter): string
    {
        $minutes = (int) ceil($retryAfter / 60);
        return 'Muitas tentativas de login. Tente novamente em ' . $minutes . ' minuto(s).';
    }

    /** @return array{allowed:bool,message:?string,retryAfter:int} */
    private function storageUnavailable(): array
    {
        return [
            'allowed' => false,
            'message' => 'Login temporariamente indisponível. Tente novamente mais tarde.',
            'retryAfter' => 0,
        ];
    }
}
