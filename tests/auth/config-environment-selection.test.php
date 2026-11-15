<?php
declare(strict_types=1);

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; recebido: ' . var_export($actual, true));
    }
}

$projectRoot = dirname(__DIR__, 2);
$fixturePath = __DIR__ . '/.env.config-fixture.runtime';
file_put_contents($fixturePath, implode(PHP_EOL, [
    'APP_ENV=testing',
    'AUTH_TEST_ISOLATION=isolated-auth-test',
    'DB_HOST=mysql',
    'DB_PORT=3306',
    'DB_NAME=auth_test_ios1vcrime',
    'DB_USER=auth_test',
    'DB_PASS=local-only',
    'MAIL_DRIVER=smtp',
    'MAIL_HOST=mailpit',
    'MAIL_PORT=1025',
    'MAIL_USER=auth-test',
    'MAIL_PASSWORD=local-only',
    'MAIL_ENCRYPTION=none',
    'MAIL_FROM_ADDRESS=no-reply@auth-test.invalid',
    'APP_URL=https://auth-test.invalid',
]) . PHP_EOL);

$script = sprintf(
    'putenv(%1$s); putenv(%2$s); require %3$s; echo DB_HOST; echo PHP_EOL; echo DB_NAME;',
    var_export('APP_ENV=testing', true),
    var_export('AUTH_TEST_ENV_FILE=' . $fixturePath, true),
    var_export($projectRoot . '/config/config.php', true)
);
$command = sprintf('%s -r %s', escapeshellarg(PHP_BINARY), escapeshellarg($script));

$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);
@unlink($fixturePath);

assertSameValue(0, $exitCode, 'O subprocesso de teste deve iniciar.');
assertSameValue(['mysql', 'auth_test_ios1vcrime'], $output, 'O ambiente de teste deve usar somente o arquivo explícito.');

echo "PASS config-environment-selection\n";
