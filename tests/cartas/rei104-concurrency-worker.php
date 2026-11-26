<?php
declare(strict_types=1);

if (getenv('REI104_TEST_ISOLATION') !== 'disposable-rei104-mysql'
    || !preg_match('/^[a-f0-9]{32}$/D', (string) getenv('REI104_TEST_RUN_ID'))
    || !preg_match('/^[a-f0-9]{64}$/D', (string) getenv('REI104_TEST_MARKER'))
    || !str_starts_with((string) getenv('REI104_TEST_DSN'), 'mysql:unix_socket=')) {
    fwrite(STDERR, "FAIL CLOSED: REI-104 worker fixture not proved\n");
    exit(90);
}

require_once __DIR__ . '/../../app/services/CartasRecebidasService.php';
$_SESSION = [];
$db = new PDO((string) getenv('REI104_TEST_DSN'), (string) getenv('REI104_TEST_USER'), (string) getenv('REI104_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$guard = $db->prepare('SELECT COUNT(*) FROM rei104_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([(string) getenv('REI104_TEST_RUN_ID'), (string) getenv('REI104_TEST_MARKER')]);
if ((int) $guard->fetchColumn() !== 1 || (string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei104_test') {
    fwrite(STDERR, "FAIL CLOSED: REI-104 worker database guard failed\n");
    exit(91);
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
if (fgets(STDIN) !== "GO\n") {
    fwrite(STDERR, "FAIL: REI-104 worker did not receive start signal\n");
    exit(92);
}

fwrite(STDOUT, "LOCKING\n");
fflush(STDOUT);
try {
    (new CartasRecebidasService($db))->concluirSubtarefa('DEV-CONCURRENT', '{"etapa":"devolucao"}');
    fwrite(STDOUT, "PASS|concurrent order-2\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL|concurrent order-2|' . $e->getMessage() . "\n");
    exit(1);
}
