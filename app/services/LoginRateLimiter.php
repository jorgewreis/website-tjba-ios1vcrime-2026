<?php
declare(strict_types=1);

final class LoginRateLimiter
{
    private const TABLE = 'login_rate_limits';
    private const FALLBACK_SESSION_KEY = 'login_rate_limits_fallback';

    private PDO $pdo;
    private bool $storageChecked = false;
    private bool $storageReady = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{allowed:bool,message:?string,retryAfter:int}
     */
    public function canAttempt(string $cpf, string $ip): array
    {
        if (!$this->ensureStorage()) {
            return $this->canAttemptFallback($cpf, $ip);
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
            return $this->registerFailureFallback($cpf, $ip);
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
            $this->clearFallback($cpf, $ip);
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . self::TABLE . ' WHERE (scope_type = ? AND scope_value = ?) OR (scope_type = ? AND scope_value = ?)'
        );

        $normalizedCpf = $this->normalizeCpfScope($cpf);
        $stmt->execute(['ip', $ip, 'cpf', $normalizedCpf]);
    }

    private function ensureStorage(): bool
    {
        if ($this->storageChecked) {
            return $this->storageReady;
        }

        $this->storageChecked = true;

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS " . self::TABLE . " (
                    id_rate_limit CHAR(30) NOT NULL PRIMARY KEY,
                    scope_type VARCHAR(20) NOT NULL,
                    scope_value VARCHAR(191) NOT NULL,
                    attempt_count INT NOT NULL DEFAULT 0,
                    lock_level INT NOT NULL DEFAULT 0,
                    window_started_at DATETIME NOT NULL,
                    last_attempt_at DATETIME NOT NULL,
                    locked_until DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_scope (scope_type, scope_value),
                    KEY idx_locked_until (locked_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $this->storageReady = true;
        } catch (Throwable $e) {
            error_log('LoginRateLimiter: fallback de armazenamento ativado: ' . $e->getMessage());
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
        $now = new DateTimeImmutable('now');
        $row = $this->findScope($type, $value);

        if ($row === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (
                    id_rate_limit, scope_type, scope_value, attempt_count, lock_level,
                    window_started_at, last_attempt_at, locked_until, created_at, updated_at
                 ) VALUES (?, ?, ?, 1, 0, NOW(), NOW(), NULL, NOW(), NOW())'
            );
            $stmt->execute([$this->generateId('RLT'), $type, $value]);
            return 0;
        }

        $windowStartedAt = (string) ($row['window_started_at'] ?? '');
        $lastAttemptAt = (string) ($row['last_attempt_at'] ?? '');
        $lockedUntil = (string) ($row['locked_until'] ?? '');
        $attemptCount = (int) ($row['attempt_count'] ?? 0);
        $lockLevel = (int) ($row['lock_level'] ?? 0);

        if ($lastAttemptAt !== '' && strtotime($lastAttemptAt) !== false && (time() - strtotime($lastAttemptAt)) > 86400) {
            $lockLevel = 0;
        }

        if ($lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time()) {
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

        return 0;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findScope(string $type, string $value): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT scope_type, scope_value, attempt_count, lock_level, window_started_at, last_attempt_at, locked_until
               FROM ' . self::TABLE . '
              WHERE scope_type = ? AND scope_value = ?
              LIMIT 1'
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
            ['type' => 'ip', 'value' => trim($ip) !== '' ? trim($ip) : '0.0.0.0'],
            ['type' => 'cpf', 'value' => $this->normalizeCpfScope($cpf)],
        ];
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

    /**
     * @return array{allowed:bool,message:?string,retryAfter:int}
     */
    private function canAttemptFallback(string $cpf, string $ip): array
    {
        $store = $_SESSION[self::FALLBACK_SESSION_KEY] ?? [];
        $retryAfter = 0;

        foreach ($this->buildScopes($cpf, $ip) as $scope) {
            $key = $scope['type'] . ':' . $scope['value'];
            $entry = $store[$key] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $lockedUntil = (int) ($entry['locked_until'] ?? 0);
            if ($lockedUntil > time()) {
                $retryAfter = max($retryAfter, $lockedUntil - time());
            }
        }

        if ($retryAfter > 0) {
            return ['allowed' => false, 'message' => $this->buildLockedMessage($retryAfter), 'retryAfter' => $retryAfter];
        }

        return ['allowed' => true, 'message' => null, 'retryAfter' => 0];
    }

    /**
     * @return array{allowed:bool,message:?string,retryAfter:int}
     */
    private function registerFailureFallback(string $cpf, string $ip): array
    {
        $store = $_SESSION[self::FALLBACK_SESSION_KEY] ?? [];
        $now = time();
        $retryAfter = 0;

        foreach ($this->buildScopes($cpf, $ip) as $scope) {
            $key = $scope['type'] . ':' . $scope['value'];
            $entry = $store[$key] ?? [
                'attempt_count' => 0,
                'lock_level' => 0,
                'window_started_at' => $now,
                'locked_until' => 0,
                'last_attempt_at' => $now,
            ];

            if (($now - (int) ($entry['last_attempt_at'] ?? $now)) > 86400) {
                $entry['lock_level'] = 0;
            }

            if ((int) ($entry['locked_until'] ?? 0) > $now) {
                $retryAfter = max($retryAfter, (int) $entry['locked_until'] - $now);
                $store[$key] = $entry;
                continue;
            }

            if (($now - (int) ($entry['window_started_at'] ?? $now)) > LOGIN_RATE_LIMIT_WINDOW_SECONDS) {
                $entry['attempt_count'] = 0;
                $entry['window_started_at'] = $now;
            }

            $entry['attempt_count'] = (int) ($entry['attempt_count'] ?? 0) + 1;
            $entry['last_attempt_at'] = $now;

            if ($entry['attempt_count'] >= LOGIN_RATE_LIMIT_MAX_ATTEMPTS) {
                $entry['lock_level'] = (int) ($entry['lock_level'] ?? 0) + 1;
                $lockSeconds = min(
                    LOGIN_RATE_LIMIT_BASE_LOCK_SECONDS * (2 ** max(0, $entry['lock_level'] - 1)),
                    LOGIN_RATE_LIMIT_MAX_LOCK_SECONDS
                );
                $entry['attempt_count'] = 0;
                $entry['window_started_at'] = $now;
                $entry['locked_until'] = $now + $lockSeconds;
                $retryAfter = max($retryAfter, $lockSeconds);
            } else {
                $entry['locked_until'] = 0;
            }

            $store[$key] = $entry;
        }

        $_SESSION[self::FALLBACK_SESSION_KEY] = $store;

        if ($retryAfter > 0) {
            return ['allowed' => false, 'message' => $this->buildLockedMessage($retryAfter), 'retryAfter' => $retryAfter];
        }

        return ['allowed' => true, 'message' => null, 'retryAfter' => 0];
    }

    private function clearFallback(string $cpf, string $ip): void
    {
        $store = $_SESSION[self::FALLBACK_SESSION_KEY] ?? [];
        foreach ($this->buildScopes($cpf, $ip) as $scope) {
            unset($store[$scope['type'] . ':' . $scope['value']]);
        }
        $_SESSION[self::FALLBACK_SESSION_KEY] = $store;
    }
}
