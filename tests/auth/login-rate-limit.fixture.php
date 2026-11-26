<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';
require dirname(__DIR__, 2) . '/app/services/LoginRateLimiter.php';

$stage = 'initial';
set_exception_handler(static function (Throwable $exception) use (&$stage): void {
    $class = in_array(get_class($exception), [PDOException::class, RuntimeException::class, InvalidArgumentException::class], true)
        ? get_class($exception)
        : 'Throwable';
    $sqlState = $exception instanceof PDOException && preg_match('/^[A-Z0-9]{5}$/', (string) $exception->getCode())
        ? (string) $exception->getCode()
        : 'none';
    $nativeCode = $exception instanceof PDOException && isset($exception->errorInfo[1]) && is_numeric($exception->errorInfo[1])
        ? (string) (int) $exception->errorInfo[1]
        : 'none';
    echo 'AUTH_FIXTURE_DIAG stage=' . $stage . ' class=' . $class . ' sqlstate=' . $sqlState . ' native=' . $nativeCode . PHP_EOL;
    exit(1);
});

$cpf = '529.982.247-25';
$ip = '127.0.0.1';
$pdo = Conection::getInstance()->getConnection();
$limiter = new LoginRateLimiter($pdo);
$action = $argv[1] ?? '';

if ($action === 'seed') {
    $stage = 'clear';
    $limiter->clear($cpf, $ip);
    $stage = 'register';
    $limiter->registerFailure($cpf, $ip);
    exit(0);
}

if ($action === 'count') {
    $stage = 'count';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_rate_limits WHERE (scope_type = 'cpf' AND scope_value = ?) OR (scope_type = 'ip' AND scope_value = ?)");
    $stmt->execute([Security::normalizeCPF($cpf), $ip]);
    echo (string) $stmt->fetchColumn();
    exit(0);
}

throw new InvalidArgumentException('Ação de fixture de rate limit inválida.');
