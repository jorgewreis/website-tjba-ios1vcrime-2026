<?php
declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Uso: reset-concurrency.worker.php <token> <senha> <barreira>\n");
    exit(2);
}

[$script, $token, $password, $barrierPath] = $argv;
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';

$deadline = microtime(true) + 10;
while (!is_file($barrierPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Barreira de concorrência expirou.\n");
        exit(3);
    }
    usleep(1000);
}

$result = (new PasswordResetService())->resetPassword($token, $password);
echo json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL;
