<?php
declare(strict_types=1);

// Environment labels alone do not release the guard. The E2E runner creates
// the token file only inside the verified disposable application container.
$guardFile = '/workspace/tests/auth/.isolated-runner';
$guardToken = getenv('AUTH_TEST_GUARD_TOKEN');
$isolated = getenv('APP_ENV') === 'testing'
    && getenv('AUTH_TEST_ISOLATION') === 'isolated-auth-test'
    && getenv('AUTH_TEST_GUARD_FILE') === $guardFile
    && is_string($guardToken)
    && preg_match('/^[a-f0-9]{64}$/', $guardToken) === 1
    && is_file($guardFile)
    && hash_equals($guardToken, trim((string) file_get_contents($guardFile)));

if (!$isolated) {
    throw new RuntimeException('Testes de autenticação suspensos: sandbox descartável não comprovado.');
}
