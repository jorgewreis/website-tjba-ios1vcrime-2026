<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';

function assertResetState(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function insertResetScenario(PDO $pdo, string $id, string $userId, string $plainToken, string $expiresSql, int $used): void
{
    $statement = $pdo->prepare("INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, {$expiresSql}, ?, ?)");
    $statement->execute([$id, $userId, Security::hashAccessToken($plainToken), $used, '127.0.0.1']);
}

$pdo = Conection::getInstance()->getConnection();
$userId = 'USR-11111111111111111111111111';
$service = new PasswordResetService();
$originalHash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '{$userId}'")->fetchColumn();

$resetLimiter = new LoginRateLimiter($pdo, 'reset');
$resetLimiter->clear('529.982.247-25', '127.0.0.1');
$mailpitBefore = json_decode((string) file_get_contents('http://127.0.0.1:38025/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
$mailpitBeforeIds = array_column($mailpitBefore['messages'] ?? [], 'ID');

$knownResponse = $service->requestReset('529.982.247-25', 'ativo@auth-test.invalid');
$unknownResponse = $service->requestReset('123.456.789-09', 'ausente@auth-test.invalid');
assertResetState($knownResponse['success'] === $unknownResponse['success'], 'Reset conhecido e desconhecido precisam ter o mesmo status público.');
assertResetState($knownResponse['emailSent'] === $unknownResponse['emailSent'], 'Reset conhecido e desconhecido precisam ter o mesmo indicador público.');
assertResetState($knownResponse['message'] === $unknownResponse['message'], 'Reset conhecido e desconhecido precisam ter a mesma mensagem pública.');

$mailpit = json_decode((string) file_get_contents('http://127.0.0.1:38025/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
$captured = false;
foreach (($mailpit['messages'] ?? []) as $message) {
    if (in_array($message['ID'] ?? null, $mailpitBeforeIds, true)) {
        continue;
    }
    foreach (($message['To'] ?? []) as $recipient) {
        if (($recipient['Address'] ?? '') === 'ativo@auth-test.invalid') {
            $captured = true;
        }
    }
}
assertResetState($captured, 'O Mailpit precisa capturar o reset da conta sintética.');

$expiredToken = bin2hex(random_bytes(32));
$usedToken = bin2hex(random_bytes(32));
$weakToken = bin2hex(random_bytes(32));
$validToken = bin2hex(random_bytes(32));
$validPassword = 'NovaA' . bin2hex(random_bytes(4)) . '!9';
$pdo->exec("DELETE FROM recuperacao_senha WHERE id_recuperacao IN ('REC-33333333333333333333333333', 'REC-44444444444444444444444444', 'REC-55555555555555555555555555', 'REC-66666666666666666666666666')");
insertResetScenario($pdo, 'REC-33333333333333333333333333', $userId, $expiredToken, 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)', 0);
insertResetScenario($pdo, 'REC-44444444444444444444444444', $userId, $usedToken, 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)', 1);
insertResetScenario($pdo, 'REC-55555555555555555555555555', $userId, $weakToken, 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)', 0);
insertResetScenario($pdo, 'REC-66666666666666666666666666', $userId, $validToken, 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)', 0);

assertResetState(($service->resetPassword($expiredToken, 'Expirada!123')['success'] ?? true) === false, 'Token expirado deve ser recusado.');
assertResetState(($service->resetPassword($usedToken, 'UsadaSenha!123')['success'] ?? true) === false, 'Token já usado deve ser recusado.');
assertResetState(($service->resetPassword($weakToken, 'fraca')['success'] ?? true) === false, 'Senha fora da política deve ser recusada.');
assertResetState((int) $pdo->query("SELECT usado FROM recuperacao_senha WHERE id_recuperacao = 'REC-55555555555555555555555555'")->fetchColumn() === 0, 'Senha inválida não pode consumir token válido.');
assertResetState(($service->resetPassword($validToken, $validPassword)['success'] ?? false) === true, 'Token válido deve redefinir a senha.');
assertResetState((int) $pdo->query("SELECT usado FROM recuperacao_senha WHERE id_recuperacao = 'REC-66666666666666666666666666'")->fetchColumn() === 1, 'Reset válido precisa consumir o token.');
$currentHash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '{$userId}'")->fetchColumn();
assertResetState(Security::verifyPassword($validPassword, $currentHash), 'Reset válido precisa persistir a nova senha.');
assertResetState(!Security::verifyPassword('Expirada!123', $currentHash), 'Token inválido não pode alterar a senha.');

$oldMailPort = (string) envValue('MAIL_PORT', '');
$markAllUsed = $pdo->prepare('UPDATE recuperacao_senha SET usado = 1 WHERE usuario = ?');
$markAllUsed->execute([$userId]);
$resetCountBeforeDeliveryFailure = $pdo->prepare('SELECT COUNT(*) FROM recuperacao_senha WHERE usuario = ?');
$resetCountBeforeDeliveryFailure->execute([$userId]);
$resetCountBefore = (int) $resetCountBeforeDeliveryFailure->fetchColumn();
putenv('MAIL_PORT=1');
$_ENV['MAIL_PORT'] = '1';
try {
    $failedDeliveryResponse = $service->requestReset('529.982.247-25', 'ativo@auth-test.invalid');
} finally {
    putenv('MAIL_PORT=' . $oldMailPort);
    $_ENV['MAIL_PORT'] = $oldMailPort;
}
assertResetState(($failedDeliveryResponse['success'] ?? false) === true, 'Falha SMTP não pode distinguir a conta publicamente.');
$resetCountAfterDeliveryFailure = $pdo->prepare('SELECT COUNT(*) FROM recuperacao_senha WHERE usuario = ?');
$resetCountAfterDeliveryFailure->execute([$userId]);
assertResetState((int) $resetCountAfterDeliveryFailure->fetchColumn() === $resetCountBefore + 1, 'Falha SMTP deve criar exatamente um token sintético antes de invalidá-lo.');
$unusedAfterDeliveryFailure = $pdo->prepare('SELECT COUNT(*) FROM recuperacao_senha WHERE usuario = ? AND usado = 0');
$unusedAfterDeliveryFailure->execute([$userId]);
assertResetState((int) $unusedAfterDeliveryFailure->fetchColumn() === 0, 'Falha de entrega SMTP deve invalidar o token recém-criado.');
assertResetState($originalHash !== '', 'A fixture precisa iniciar com senha sintética.');

echo "PASS password-reset-states\n";
