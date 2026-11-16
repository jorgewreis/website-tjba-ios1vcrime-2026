<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';

$pdo = Conection::getInstance()->getConnection();
$userId = 'USR-11111111111111111111111111';
$plainToken = bin2hex(random_bytes(32));
$pdo->prepare('UPDATE recuperacao_senha SET usado = 1 WHERE usuario = ? AND usado = 0')->execute([$userId]);
$pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)')->execute([
    'REC-' . strtoupper(bin2hex(random_bytes(13))),
    $userId,
    Security::hashAccessToken($plainToken),
    '127.0.0.1',
]);

echo $plainToken;
