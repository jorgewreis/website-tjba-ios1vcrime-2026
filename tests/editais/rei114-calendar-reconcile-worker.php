<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';

$readyPath = $argv[1] ?? '';
$editalIds = array_values(array_filter(array_slice($argv, 2), static fn ($id) => is_string($id) && $id !== ''));
if ($readyPath === '' || $editalIds === []) {
    fwrite(STDERR, "Worker requires readiness path and at least one edital ID.\n");
    exit(2);
}
try {
    $pdo = new PDO(
        (string) getenv('FERIADOS_TEST_DSN'),
        (string) getenv('FERIADOS_TEST_USER'),
        (string) getenv('FERIADOS_TEST_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    file_put_contents($readyPath, json_encode(['connection_id' => $connectionId], JSON_THROW_ON_ERROR), LOCK_EX);
    $result = (new EditaisService($pdo))->reconciliarEditais($editalIds);
    $lockOwnerAfter = $pdo->query("SELECT IS_USED_LOCK('fokus-law-feriados-recalc')")->fetchColumn();
    $isolationAfter = (string) $pdo->query('SELECT @@transaction_isolation')->fetchColumn();
    fwrite(STDOUT, json_encode(['result' => $result, 'lock_owner_after' => $lockOwnerAfter, 'isolation_after' => $isolationAfter], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
