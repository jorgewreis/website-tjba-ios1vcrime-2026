<?php
declare(strict_types=1);

$dsn = (string) getenv('REI107_MYSQL_DSN');
$runId = (string) getenv('REI107_TEST_RUN_ID');
$marker = (string) getenv('REI107_TEST_MARKER');
if (getenv('REI107_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei107_test;charset=utf8mb4') {
    fwrite(STDERR, "FAIL CLOSED: invalid worker database configuration\n");
    exit(90);
}

require_once '/repo/app/services/CartasExpedidasService.php';
require_once '/repo/app/services/CartasRecebidasService.php';
require_once '/repo/app/services/EditaisService.php';
require_once '/repo/app/services/GuiasService.php';

$operations = [
    'expedidas' => [
        CartasExpedidasService::class,
        CartasExpedidasSubtarefasRepository::class,
        CartasExpedidasAlocadosRepository::class,
        CartasExpedidasSubtarefasRepository::TIPO_ENVIO,
        CartasExpedidasSubtarefasRepository::ORDEM_ENVIO,
    ],
    'recebidas' => [
        CartasRecebidasService::class,
        CartasRecebidasSubtarefasRepository::class,
        CartasRecebidasAlocadosRepository::class,
        CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO,
        CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO,
    ],
    'editais' => [
        EditaisService::class,
        EditaisSubtarefasRepository::class,
        EditaisAlocadosRepository::class,
        EditaisSubtarefasRepository::TIPO_PUBLICACAO,
        EditaisSubtarefasRepository::ORDEM_PUBLICACAO,
    ],
    'guias' => [
        GuiasService::class,
        GuiasSubtarefasRepository::class,
        GuiasAlocadosRepository::class,
        GuiasSubtarefasRepository::TIPO_ORDEM_1,
        1,
    ],
];

[$mode, $module, $slot, $parentId] = array_slice($argv, 1) + [null, null, null, null];
if (!in_array($mode, ['create', 'allocate', 'delete'], true) || !isset($operations[$module]) || !preg_match('/^[a-f0-9]{32}$/', (string) $slot) || !preg_match('/^[A-Z0-9_-]{1,64}$/', (string) $parentId)) {
    fwrite(STDERR, "FAIL CLOSED: invalid worker arguments\n");
    exit(91);
}

$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$guard = $db->prepare('SELECT COUNT(*) FROM rei107_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((int) $guard->fetchColumn() !== 1 || (string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei107_test') {
    fwrite(STDERR, "FAIL CLOSED: worker database guard mismatch\n");
    exit(92);
}

$status = $db->prepare('INSERT INTO rei107_worker_runs (slot, status, connection_id) VALUES (:slot, \'running\', CONNECTION_ID()) ON DUPLICATE KEY UPDATE status=VALUES(status), connection_id=VALUES(connection_id), message=NULL');
$status->execute([':slot' => $slot]);
[$serviceClass, $repositoryClass, $allocationClass, $type, $order] = $operations[$module];
try {
    if ($mode === 'delete') {
        $result = (new $serviceClass($db))->delete($parentId);
    } elseif ($mode === 'create') {
        $result = (new $repositoryClass($db))->create($parentId, $type, $order, 'pendente', null);
    } else {
        $result = (new $allocationClass($db))->addAlocado($parentId, 'RACE-USER-' . $slot);
    }
    $done = $db->prepare('UPDATE rei107_worker_runs SET status=\'done\', message=:message WHERE slot=:slot');
    $done->execute([':message' => json_encode($result, JSON_THROW_ON_ERROR), ':slot' => $slot]);
} catch (Throwable $exception) {
    $failed = $db->prepare('UPDATE rei107_worker_runs SET status=\'error\', message=:message WHERE slot=:slot');
    $failed->execute([':message' => get_class($exception) . ': ' . $exception->getMessage(), ':slot' => $slot]);
}
