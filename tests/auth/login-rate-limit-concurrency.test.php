<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';
require dirname(__DIR__, 2) . '/app/services/LoginRateLimiter.php';
set_exception_handler(static function (Throwable $e): void {
    $known = ['Contrato de sessão posterior à admissão não encontrado.', 'Rota de login deixou de serializar tentativas.', 'Worker sintético falhou.', 'Worker retornou resultado inválido.'];
    $index = array_search($e->getMessage(), $known, true);
    $stage = in_array($GLOBALS['rei158_stage'] ?? '', ['initial', 'workers', 'success', 'timeout', 'storage'], true) ? $GLOBALS['rei158_stage'] : 'initial';
    echo 'REI158_DIAG code-' . $stage . '-' . ($index === false ? 'other' : (string)$index) . PHP_EOL;
    exit(1);
});

function assertLoginConcurrency(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei158LockNames(string $database, string $cpf, string $ip): array
{
    $normalizedCpf = preg_replace('/\D+/', '', $cpf) ?: 'anon';
    $scopes = [
        ['type' => 'ip', 'value' => trim($ip) !== '' ? trim($ip) : '0.0.0.0'],
        ['type' => 'cpf', 'value' => $normalizedCpf],
    ];
    $names = [];
    foreach ($scopes as $scope) {
        $digest = hash('sha256', $database . "\0" . $scope['type'] . "\0" . $scope['value']);
        $names[] = 'fokus_login_' . substr($digest, 0, 52);
    }
    $names = array_values(array_unique($names));
    sort($names, SORT_STRING);
    return $names;
}

$GLOBALS['rei158_stage'] = 'initial';
$pdo = Conection::getInstance()->getConnection();
$limiter = new LoginRateLimiter($pdo);
$loginSource = file_get_contents(dirname(__DIR__, 2) . '/public/login.php');
assertLoginConcurrency(is_string($loginSource), 'Não foi possível inspecionar a rota de login.');
$lockMarker = strpos($loginSource, 'withLoginAttemptLock(');
assertLoginConcurrency($lockMarker !== false, 'Rota de login deixou de serializar tentativas.');
$lockedLoginBlock = substr($loginSource, $lockMarker);
$sessionStart = strpos($lockedLoginBlock, '/* =====================');
assertLoginConcurrency($sessionStart !== false, 'Contrato de sessão posterior à admissão não encontrado.');
$lockedLoginBlock = substr($lockedLoginBlock, 0, $sessionStart);
foreach (['->canAttempt(', '->autenticar(', '->registerFailure(', '->clear('] as $requiredCall) {
    assertLoginConcurrency(str_contains($lockedLoginBlock, $requiredCall), "Rota deixou de executar $requiredCall dentro da trava.");
}
$cpf = '529.982.247-25';
$ip = '127.0.0.181';
$successCpf = '111.444.777-35';
$successIp = '127.0.0.182';
assertLoginConcurrency(LOGIN_RATE_LIMIT_MAX_ATTEMPTS > 1, 'O teste requer limite maior que uma tentativa.');
$limiter->clear($cpf, $ip);
$limiter->clear($successCpf, $successIp);

$workerCode = <<<'PHP'
require getenv('REI158_REPO_ROOT') . '/tests/auth/sandbox-guard.php';
require getenv('REI158_REPO_ROOT') . '/config/config.php';
require getenv('REI158_REPO_ROOT') . '/app/helpers/Conection.php';
require getenv('REI158_REPO_ROOT') . '/app/services/LoginRateLimiter.php';
$target = (int) getenv('REI158_START_AT_NS');
while (hrtime(true) < $target) { usleep(1000); }
$limiter = new LoginRateLimiter(Conection::getInstance()->getConnection());
$cpf = (string) getenv('REI158_CPF');
$ip = (string) getenv('REI158_IP');
$result = $limiter->withLoginAttemptLock($cpf, $ip, static function () use ($limiter, $cpf, $ip): array {
    $status = $limiter->canAttempt($cpf, $ip);
    if (($status['allowed'] ?? false) !== true) {
        return ['admitted' => false, 'locked' => true];
    }
    // Represents the credential verification latency of an invalid login.
    usleep(150000);
    $status = $limiter->registerFailure($cpf, $ip);
    return ['admitted' => true, 'locked' => ($status['allowed'] ?? false) !== true];
});
echo json_encode($result, JSON_THROW_ON_ERROR);
PHP;

$processes = [];
try {
    $GLOBALS['rei158_stage'] = 'workers';
    $workerCount = LOGIN_RATE_LIMIT_MAX_ATTEMPTS + 4;
    $startAt = hrtime(true) + 600_000_000;
    for ($index = 0; $index < $workerCount; $index++) {
        putenv('REI158_REPO_ROOT=' . dirname(__DIR__, 2));
        putenv('REI158_START_AT_NS=' . $startAt);
        putenv('REI158_CPF=' . $cpf);
        putenv('REI158_IP=' . $ip);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open([PHP_BINARY, '-r', $workerCode], $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Falha ao iniciar trabalhador sintético de login.');
        }
        fclose($pipes[0]);
        $processes[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    $results = [];
    foreach ($processes as $index => $worker) {
        $stdout = stream_get_contents($worker['stdout']);
        $stderr = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        $processes[$index]['process'] = null;
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker sintético falhou: ' . trim((string) $stderr));
        }
        $decoded = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Worker retornou resultado inválido.');
        }
        $results[] = $decoded;
    }

    $admitted = count(array_filter($results, static fn (array $result): bool => ($result['admitted'] ?? false) === true));
    $blocked = count(array_filter($results, static fn (array $result): bool => ($result['admitted'] ?? true) === false));
    assertLoginConcurrency($admitted === LOGIN_RATE_LIMIT_MAX_ATTEMPTS, "Esperava $" . LOGIN_RATE_LIMIT_MAX_ATTEMPTS . " admissões serializadas; recebeu $admitted.");
    assertLoginConcurrency($blocked === $workerCount - LOGIN_RATE_LIMIT_MAX_ATTEMPTS, 'Tentativas simultâneas excedentes não foram bloqueadas.');
    assertLoginConcurrency(($limiter->canAttempt($cpf, $ip)['allowed'] ?? true) === false, 'A última tentativa concorrente não manteve o escopo aberto.');

    // A valid credential path clears previous failures and never calls the
    // failure counter; the next failure therefore starts at one.
    $GLOBALS['rei158_stage'] = 'success';
    for ($attempt = 1; $attempt < LOGIN_RATE_LIMIT_MAX_ATTEMPTS; $attempt++) {
        $limiter->registerFailure($successCpf, $successIp);
    }
    $successOutcome = $limiter->withLoginAttemptLock($successCpf, $successIp, static function () use ($limiter, $successCpf, $successIp): string {
        if (($limiter->canAttempt($successCpf, $successIp)['allowed'] ?? false) !== true) {
            throw new RuntimeException('Credencial válida sintética bloqueada antes da verificação.');
        }
        // A real public/login.php path clears here after successful authentication.
        $limiter->clear($successCpf, $successIp);
        return 'success';
    });
    assertLoginConcurrency($successOutcome === 'success', 'A admissão válida sintética falhou.');
    assertLoginConcurrency(($limiter->canAttempt($successCpf, $successIp)['allowed'] ?? false) === true, 'Sucesso não limpou as falhas anteriores.');
    $limiter->registerFailure($successCpf, $successIp);
    $stmt = $pdo->prepare("SELECT attempt_count FROM login_rate_limits WHERE scope_type='cpf' AND scope_value=?");
    $stmt->execute([preg_replace('/\D+/', '', $successCpf)]);
    assertLoginConcurrency((int) $stmt->fetchColumn() === 1, 'Tentativa válida contribuiu para o contador de falhas.');

    // Force a second-lock timeout while the first scope lock is acquired by
    // this connection; the partial acquisition must be released before return.
    $GLOBALS['rei158_stage'] = 'timeout';
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $timeoutCpf = '987.654.321-00';
    $timeoutIp = '127.0.0.183';
    $lockNames = rei158LockNames($database, $timeoutCpf, $timeoutIp);
    $holder = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $hold = $holder->prepare('SELECT GET_LOCK(?, 0)');
    $hold->execute([$lockNames[1]]);
    assertLoginConcurrency((int) $hold->fetchColumn() === 1, 'Não foi possível preparar a contenção sintética.');
    $callbackRan = false;
    try {
        $limiter->withLoginAttemptLock($timeoutCpf, $timeoutIp, static function () use (&$callbackRan): void {
            $callbackRan = true;
        });
        throw new RuntimeException('Timeout da trava não fechou a autenticação.');
    } catch (RuntimeException $expected) {
        if ($expected->getMessage() === 'Timeout da trava não fechou a autenticação.') {
            throw $expected;
        }
    } finally {
        $release = $holder->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockNames[1]]);
        if ((int) $release->fetchColumn() !== 1) {
            throw new RuntimeException('A trava mantida pelo teste não foi liberada.');
        }
    }
    assertLoginConcurrency($callbackRan === false, 'Callback de autenticação executou após timeout da trava.');
    $used = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    $used->execute([$lockNames[0]]);
    $lockOwner = $used->fetchColumn();
    assertLoginConcurrency($lockOwner === false || $lockOwner === null, 'Trava parcialmente adquirida não foi liberada.');

    // Storage is also fail closed before the callback, with the original table
    // restored so the rest of the isolated authentication suite can continue.
    $GLOBALS['rei158_stage'] = 'storage';
    $pdo->exec('RENAME TABLE login_rate_limits TO login_rate_limits_rei158_hold');
    try {
        $storageUnavailable = new LoginRateLimiter($pdo);
        $callbackRan = false;
        try {
            $storageUnavailable->withLoginAttemptLock($timeoutCpf, $timeoutIp, static function () use (&$callbackRan): void {
                $callbackRan = true;
            });
            throw new RuntimeException('Falha de armazenamento não fechou a autenticação.');
        } catch (RuntimeException $expected) {
            if ($expected->getMessage() === 'Falha de armazenamento não fechou a autenticação.') {
                throw $expected;
            }
        }
        assertLoginConcurrency($callbackRan === false, 'Callback executou sem armazenamento de rate limit.');
    } finally {
        $pdo->exec('RENAME TABLE login_rate_limits_rei158_hold TO login_rate_limits');
    }

    echo "PASS REI-158: admissão CPF/IP serializada, sucesso não conta como falha e timeout/storage falham fechados.\n";
} finally {
    foreach ($processes as $worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            proc_close($worker['process']);
        }
        foreach (['stdout', 'stderr'] as $pipeName) {
            if (isset($worker[$pipeName]) && is_resource($worker[$pipeName])) {
                fclose($worker[$pipeName]);
            }
        }
    }
    $limiter->clear($cpf, $ip);
    $limiter->clear($successCpf, $successIp);
}
