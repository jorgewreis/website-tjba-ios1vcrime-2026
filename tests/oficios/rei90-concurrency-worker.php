<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/OficiosCartorioService.php';

$runId = (string) getenv('REI90_RUN_ID');
$marker = (string) getenv('REI90_MARKER');
$dsn = (string) getenv('REI90_MYSQL_DSN');
if (getenv('REI90_TEST_ISOLATION') !== 'disposable-rei90-mysql'
    || !preg_match('/^[a-f0-9]{32}$/D', $runId)
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)
    || $dsn !== 'mysql:host=mysql;port=3306;dbname=rei90_test;charset=utf8mb4') {
    fwrite(STDERR, "FAIL CLOSED: REI-90 worker fixture not proved\n");
    exit(90);
}

$pdo = new PDO($dsn, 'root', (string) getenv('REI90_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei90_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([$runId, $marker]);
if ((int) $guard->fetchColumn() !== 1 || (string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei90_test') {
    fwrite(STDERR, "FAIL CLOSED: REI-90 worker database guard failed\n");
    exit(91);
}

$connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
$ready = $pdo->prepare('INSERT INTO rei90_worker_ready (run_id, connection_id) VALUES (?, ?)');
$ready->execute([$runId, $connectionId]);
fwrite(STDOUT, "READY|{$connectionId}\n");
fflush(STDOUT);
if (fgets(STDIN) !== "GO\n") {
    fwrite(STDERR, "FAIL: REI-90 worker did not receive the start signal\n");
    exit(92);
}

try {
    (new OficiosCartorioService($pdo))->concluirSubtarefa('S2', 'USR-REI90');
    fwrite(STDOUT, "PASS|concurrent order-2\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL|concurrent order-2|' . $e->getMessage() . "\n");
    exit(1);
}
