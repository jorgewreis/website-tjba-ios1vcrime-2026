<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';

$readyPath = $argv[1] ?? '';
$editalId = $argv[2] ?? '';
if ($readyPath === '' || $editalId === '') {
    fwrite(STDERR, "Worker requires readiness path and edital ID.\n");
    exit(2);
}
file_put_contents($readyPath, 'ready', LOCK_EX);

try {
    $pdo = new PDO(
        (string) getenv('FERIADOS_TEST_DSN'),
        (string) getenv('FERIADOS_TEST_USER'),
        (string) getenv('FERIADOS_TEST_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $result = (new EditaisService($pdo))->reconciliarEditais([$editalId]);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
}
