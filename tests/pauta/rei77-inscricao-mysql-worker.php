<?php
declare(strict_types=1);

$args = $argv;
array_shift($args);
[$pauta, $usuario, $barrier, $index, $runId, $marker] = array_pad($args, 6, '');
$expectedDsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei77_test;charset=utf8mb4';
if (getenv('REI77_TEST_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || getenv('REI77_MYSQL_DSN') !== $expectedDsn
    || getenv('REI77_MYSQL_USER') !== 'root'
    || !in_array($index, ['0', '1'], true)
    || !preg_match('/^\/tmp\/rei77-' . preg_quote($runId, '/') . '-[a-f0-9]{16}$/', $barrier)
    || !is_dir($barrier)
) {
    fwrite(STDERR, "Worker fail-closed: disposable configuration invalid\n");
    exit(90);
}

require_once '/repo/app/services/SalaAudienciasAtaService.php';
$db = new PDO(
    (string) getenv('REI77_MYSQL_DSN'),
    (string) getenv('REI77_MYSQL_USER'),
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 120]
);
$db->exec('SET SESSION innodb_lock_wait_timeout = 90');
$guard = $db->prepare('SELECT COUNT(*) FROM rei77_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei77_test'
    || !str_starts_with((string) $db->query('SELECT VERSION()')->fetchColumn(), '8.4.')
    || strtoupper((string) $db->query('SELECT @@transaction_isolation')->fetchColumn()) !== 'REPEATABLE-READ'
    || (int) $guard->fetchColumn() !== 1
) {
    fwrite(STDERR, "Worker fail-closed: disposable database marker missing\n");
    exit(91);
}

if (file_put_contents($barrier . '/ready-' . $index, $runId, LOCK_EX) === false) {
    fwrite(STDERR, "Worker could not join race barrier\n");
    exit(92);
}
$deadline = microtime(true) + 20;
$goFile = $barrier . '/go';
while (microtime(true) < $deadline) {
    if (is_file($goFile)) {
        $go = file_get_contents($goFile);
        if ($go !== $runId . ':' . $marker) {
            fwrite(STDERR, "Worker barrier marker mismatch\n");
            exit(93);
        }
        $threadId = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
        if ($threadId < 1 || file_put_contents($barrier . '/started-' . $index, (string) $threadId, LOCK_EX) === false) {
            fwrite(STDERR, "Worker could not announce its MySQL session\n");
            exit(95);
        }
        try {
            (new SalaAudienciasAtaService($db))->inscreverEstudante($pauta, $usuario);
            echo json_encode(['result' => 'success'], JSON_THROW_ON_ERROR) . "\n";
            exit(0);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['result' => 'refused', 'message' => $e->getMessage()], JSON_THROW_ON_ERROR) . "\n";
            exit(0);
        }
    }
    usleep(10000);
}

fwrite(STDERR, "Worker timed out at race barrier\n");
exit(94);
