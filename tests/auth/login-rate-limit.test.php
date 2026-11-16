<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/LoginRateLimiter.php';

function assertLoginTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = Conection::getInstance()->getConnection();
$user = new Usuario();
$cpf = '529.982.247-25';
$ip = '127.0.0.77';
$limiter = new LoginRateLimiter($pdo);
$limiter->clear($cpf, $ip);

$invalid = $user->autenticar($cpf, 'Errada!12345');
assertLoginTest(($invalid['success'] ?? false) === false, 'Credencial sintética inválida não pode autenticar.');

$inactive = $user->autenticar('111.444.777-35', 'Inativo!12345');
assertLoginTest(($inactive['success'] ?? false) === false && ($inactive['inactive'] ?? false) === true, 'Conta sintética inativa deve ser recusada.');

for ($attempt = 1; $attempt <= LOGIN_RATE_LIMIT_MAX_ATTEMPTS; $attempt++) {
    $status = $limiter->registerFailure($cpf, $ip);
}
assertLoginTest(($status['allowed'] ?? true) === false, 'A última falha deve bloquear o escopo sintético.');
assertLoginTest(($limiter->canAttempt($cpf, $ip)['allowed'] ?? true) === false, 'Falha de credencial não pode limpar o limite.');

$valid = $user->autenticar($cpf, 'Ativo!12345');
assertLoginTest(($valid['success'] ?? false) === true, 'Credencial sintética válida deve autenticar.');
$limiter->clear($cpf, $ip);
assertLoginTest(($limiter->canAttempt($cpf, $ip)['allowed'] ?? false) === true, 'Login válido deve permitir a limpeza dos escopos CPF/IP.');

echo "PASS login-rate-limit\n";
