<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';

$readyPath = $argv[1] ?? '';
$editalId = $argv[2] ?? '';
if ($readyPath === '' || $editalId === '') {
    fwrite(STDERR, "Worker requires readiness path and edital ID.\n");
    exit(2);
}
try {
    $pdo = new PDO(
        (string) getenv('FERIADOS_TEST_DSN'),
        (string) getenv('FERIADOS_TEST_USER'),
        (string) getenv('FERIADOS_TEST_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    file_put_contents($readyPath, json_encode(['connection_id' => $connectionId], JSON_THROW_ON_ERROR), LOCK_EX);
    $result = (new EditaisService($pdo))->reconciliarEditais([$editalId]);
    $lockOwnerAfter = $pdo->query("SELECT IS_USED_LOCK('fokus-law-feriados-recalc')")->fetchColumn();
    fwrite(STDOUT, json_encode(['result' => $result, 'lock_owner_after' => $lockOwnerAfter], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
