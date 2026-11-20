<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';

$action = $argv[1] ?? '';
$pdo = Conection::getInstance()->getConnection();

if ($action === 'disable') {
    $pdo->exec('RENAME TABLE login_rate_limits TO login_rate_limits_unavailable');
    exit(0);
}

if ($action === 'restore') {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'login_rate_limits_unavailable'")->fetchColumn();
    if ($exists === 1) {
        $pdo->exec('RENAME TABLE login_rate_limits_unavailable TO login_rate_limits');
    }
    exit(0);
}

throw new InvalidArgumentException('Ação de fixture de armazenamento inválida.');
