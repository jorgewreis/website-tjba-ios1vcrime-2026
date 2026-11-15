<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';
require dirname(__DIR__, 2) . '/app/services/LoginRateLimiter.php';

$cpf = '529.982.247-25';
$ip = '127.0.0.1';
$pdo = Conection::getInstance()->getConnection();
$limiter = new LoginRateLimiter($pdo);
$action = $argv[1] ?? '';

if ($action === 'seed') {
    $limiter->clear($cpf, $ip);
    $limiter->registerFailure($cpf, $ip);
    exit(0);
}

if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_rate_limits WHERE (scope_type = 'cpf' AND scope_value = ?) OR (scope_type = 'ip' AND scope_value = ?)");
    $stmt->execute([Security::normalizeCPF($cpf), $ip]);
    echo (string) $stmt->fetchColumn();
    exit(0);
}

throw new InvalidArgumentException('Ação de fixture de rate limit inválida.');
