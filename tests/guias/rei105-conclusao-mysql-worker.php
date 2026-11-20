<?php
declare(strict_types=1);

[$script, $operation, $subtaskId, $barrier, $index, $runId, $marker] = array_pad($argv, 7, '');
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei105_test;charset=utf8mb4';
if (getenv('REI105_TEST_DISPOSABLE') !== '1'
    || getenv('REI105_MYSQL_DSN') !== $dsn
    || getenv('REI105_MYSQL_USER') !== 'root'
    || !in_array($operation, ['complete', 'cancel'], true)
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || !in_array($index, ['0', '1'], true)
    || !preg_match('/^\/tmp\/rei105-' . preg_quote($runId, '/') . '-[a-f0-9]{16}$/', $barrier)
    || !is_dir($barrier)) {
    fwrite(STDERR, "Worker fail-closed: disposable configuration invalid\n"); exit(90);
}
require_once '/repo/app/services/GuiasService.php';
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 120]);
$db->exec('SET SESSION innodb_lock_wait_timeout=90');
$guard = $db->prepare('SELECT COUNT(*) FROM rei105_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((int) $guard->fetchColumn() !== 1 || strtoupper((string) $db->query('SELECT @@transaction_isolation')->fetchColumn()) !== 'REPEATABLE-READ') {
    fwrite(STDERR, "Worker fail-closed: guard/isolation check failed\n"); exit(91);
}
file_put_contents($barrier . '/ready-' . $index, $runId, LOCK_EX);
$deadline = microtime(true) + 20;
while (microtime(true) < $deadline) {
    if (is_file($barrier . '/go')) {
        if (file_get_contents($barrier . '/go') !== $runId . ':' . $marker) { fwrite(STDERR, "Worker barrier marker mismatch\n"); exit(92); }
        $threadId = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
        file_put_contents($barrier . '/thread-' . $index, (string) $threadId, LOCK_EX);
        try {
            $service = new GuiasService($db);
            if ($operation === 'cancel') {
                $service->cancelarSubtarefa($subtaskId);
                echo json_encode(['result' => 'cancelled'], JSON_THROW_ON_ERROR) . "\n";
            } else {
                $service->concluirSubtarefa($subtaskId, '{}');
                echo json_encode(['result' => 'completed'], JSON_THROW_ON_ERROR) . "\n";
            }
            exit(0);
        } catch (InvalidArgumentException) {
            echo json_encode(['result' => 'refused'], JSON_THROW_ON_ERROR) . "\n";
            exit(0);
        } catch (Throwable $exception) {
            fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . "\n"); exit(93);
        }
    }
    usleep(10000);
}
fwrite(STDERR, "Worker timeout at barrier\n"); exit(94);
