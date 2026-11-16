<?php
declare(strict_types=1);

$dsn = (string) getenv('FERIADOS_TEST_DSN');
$marker = trim((string) ($argv[1] ?? ''));
if (getenv('FERIADOS_TEST_ISOLATION') !== 'isolated-feriados-test'
    || !preg_match('/^mysql:host=127\.0\.0\.1;dbname=feriados_[a-f0-9]{32};charset=utf8mb4$/', $dsn)
    || getenv('FERIADOS_TEST_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{32}$/', (string) getenv('FERIADOS_TEST_PASSWORD'))
    || $marker === '' || !str_starts_with($marker, sys_get_temp_dir())) {
    throw new RuntimeException('Worker REI-114 permitido somente no runner MySQL descartável de feriados.');
}

$connection = new PDO($dsn, 'root', (string) getenv('FERIADOS_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$lock = $connection->query("SELECT GET_LOCK('fokus-law-feriados-recalc', 5)");
if ($lock === false || (int) $lock->fetchColumn() !== 1) {
    throw new RuntimeException('Worker não obteve o mutex global do calendário.');
}

try {
    if (file_put_contents($marker, 'ready', LOCK_EX) === false) {
        throw new RuntimeException('Worker não sinalizou a aquisição do mutex.');
    }
    usleep(750000);
} finally {
    $release = $connection->query("SELECT RELEASE_LOCK('fokus-law-feriados-recalc')");
    if ($release === false || (int) $release->fetchColumn() !== 1) {
        throw new RuntimeException('Worker não liberou o mutex global do calendário.');
    }
}
