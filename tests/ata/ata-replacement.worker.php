<?php
declare(strict_types=1);

if ($argc !== 7) {
    fwrite(STDERR, "Uso: ata-replacement.worker.php <origem> <raiz> <início> <pronto> <continuar> <nome>\n");
    exit(2);
}

[, $source, $root, $startBarrierPath, $readyPath, $continueBarrierPath, $name] = $argv;
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaStorage.php';
require dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaService.php';

final class AtaReplacementWorkerStorage implements SalaAudienciasAtaStorage
{
    public function __construct(private string $root, private string $readyPath, private string $continueBarrierPath)
    {
    }

    public function rootPath(): string { return $this->root; }
    public function isUploadedFile(string $path): bool { return is_file($path); }
    public function moveUploadedFile(string $source, string $destination): bool
    {
        if (!copy($source, $destination)) {
            return false;
        }
        file_put_contents($this->readyPath, 'ready');
        $deadline = microtime(true) + 10;
        while (!is_file($this->continueBarrierPath)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('Barreira após move expirou.');
            }
            usleep(1000);
        }
        return true;
    }
    public function isFile(string $path): bool { return is_file($path); }
    public function deleteFile(string $path): bool { return !is_file($path) || unlink($path); }
}

$deadline = microtime(true) + 10;
while (!is_file($startBarrierPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDERR, "Barreira de concorrência expirou.\n");
        exit(3);
    }
    usleep(1000);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    (new SalaAudienciasAtaService($pdo, new AtaReplacementWorkerStorage($root, $readyPath, $continueBarrierPath)))->uploadPdf(
        'PAT-ATA-0000000000000000000001',
        ['name' => $name . '.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)],
        'USR-ATA-0000000000000000000001'
    );
    echo json_encode(['success' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR) . PHP_EOL;
}
