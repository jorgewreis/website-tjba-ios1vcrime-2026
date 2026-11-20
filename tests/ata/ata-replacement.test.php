<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaStorage.php';
require dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaService.php';

final class AtaReplacementTestStorage implements SalaAudienciasAtaStorage
{
    public bool $failMove = false;
    /** @var array<string, bool> */
    public array $failDelete = [];

    public function __construct(private string $root)
    {
    }

    public function rootPath(): string
    {
        return $this->root;
    }

    public function isUploadedFile(string $path): bool
    {
        return is_file($path);
    }

    public function moveUploadedFile(string $source, string $destination): bool
    {
        if ($this->failMove) {
            return false;
        }
        return copy($source, $destination);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function deleteFile(string $path): bool
    {
        if (($this->failDelete[$path] ?? false) === true) {
            return false;
        }
        return !is_file($path) || unlink($path);
    }
}

function ataTestAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function ataTestPdo(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    return new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function ataTestStorageRoot(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . '.runtime-storage';
}

function ataTestReset(PDO $pdo): array
{
    $root = ataTestStorageRoot();
    if (is_dir($root)) {
        foreach (glob($root . DIRECTORY_SEPARATOR . 'sala-audiencias-atas' . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
    }
    $dir = $root . DIRECTORY_SEPARATOR . 'sala-audiencias-atas';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar o armazenamento isolado de atas.');
    }

    $pdo->exec('DROP TRIGGER IF EXISTS ata_replacement_test_fail_update');
    $pdo->exec('DELETE FROM sala_audiencias_ata');
    $pdo->exec('DELETE FROM sala_audiencias_pauta');
    $pdo->prepare('INSERT INTO sala_audiencias_pauta (id_pauta, processo, data_audiencia, status, ativo) VALUES (?, NULL, CURDATE(), ?, 1)')
        ->execute(['PAT-ATA-0000000000000000000001', 'realizada']);

    $oldRelative = 'sala-audiencias-atas/ata_antiga.pdf';
    $oldFull = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldRelative);
    file_put_contents($oldFull, "%PDF-1.4\nold ata\n");
    $pdo->prepare('INSERT INTO sala_audiencias_ata (id_ata, pauta, arquivo_relativo, nome_original, mime, tamanho_bytes, uploaded_at, uploaded_by, expires_at, identificador_arquivo) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?)')
        ->execute(['SAT-ATA-0000000000000000000001', 'PAT-ATA-0000000000000000000001', $oldRelative, 'ata-antiga.pdf', 'application/pdf', filesize($oldFull), 'USR-ATA-0000000000000000000001', 'ATA-OLD']);

    $source = $root . DIRECTORY_SEPARATOR . 'novo.pdf';
    file_put_contents($source, "%PDF-1.4\nnew ata\n");

    return [$root, $source, $oldRelative, $oldFull];
}

function ataTestCurrentRow(PDO $pdo): array
{
    $st = $pdo->query("SELECT * FROM sala_audiencias_ata WHERE pauta = 'PAT-ATA-0000000000000000000001'");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new RuntimeException('Ata de fixture ausente.');
    }
    return $row;
}

$pdo = ataTestPdo();

[$root, $source, $oldRelative, $oldFull] = ataTestReset($pdo);
$storage = new AtaReplacementTestStorage($root);
$service = new SalaAudienciasAtaService($pdo, $storage);
$service->uploadPdf('PAT-ATA-0000000000000000000001', ['name' => 'ata-nova.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], 'USR-ATA-0000000000000000000001');
$row = ataTestCurrentRow($pdo);
ataTestAssert($row['arquivo_relativo'] !== $oldRelative, 'A substituição deve gravar a nova referência.');
ataTestAssert(is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['arquivo_relativo'])), 'O PDF referenciado deve existir após sucesso.');
ataTestAssert(!is_file($oldFull), 'O PDF anterior só deve ser removido após o commit.');

[$root, $source, $oldRelative, $oldFull] = ataTestReset($pdo);
$storage = new AtaReplacementTestStorage($root);
$storage->failMove = true;
$service = new SalaAudienciasAtaService($pdo, $storage);
try {
    $service->uploadPdf('PAT-ATA-0000000000000000000001', ['name' => 'ata-nova.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], 'USR-ATA-0000000000000000000001');
    throw new RuntimeException('A falha de armazenamento deveria interromper a substituição.');
} catch (RuntimeException $e) {
    ataTestAssert(ataTestCurrentRow($pdo)['arquivo_relativo'] === $oldRelative, 'Falha ao mover não pode alterar referência anterior.');
    ataTestAssert(is_file($oldFull), 'Falha ao mover não pode apagar PDF anterior.');
}

[$root, $source, $oldRelative, $oldFull] = ataTestReset($pdo);
$pdo->exec('ALTER TABLE sala_audiencias_ata ADD CONSTRAINT chk_ata_replacement_test_identificador CHECK (CHAR_LENGTH(identificador_arquivo) <= 10)');
$storage = new AtaReplacementTestStorage($root);
$service = new SalaAudienciasAtaService($pdo, $storage);
try {
    $service->uploadPdf('PAT-ATA-0000000000000000000001', ['name' => 'ata-nova.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], 'USR-ATA-0000000000000000000001');
    throw new RuntimeException('A falha de persistência deveria interromper a substituição.');
} catch (PDOException $e) {
    ataTestAssert(ataTestCurrentRow($pdo)['arquivo_relativo'] === $oldRelative, 'Falha de persistência não pode alterar referência anterior.');
    ataTestAssert(is_file($oldFull), 'Falha de persistência não pode apagar PDF anterior.');
    ataTestAssert(count(glob($root . DIRECTORY_SEPARATOR . 'sala-audiencias-atas' . DIRECTORY_SEPARATOR . '*') ?: []) === 1, 'Falha de persistência deve remover o novo PDF.');
} finally {
    $pdo->exec('ALTER TABLE sala_audiencias_ata DROP CONSTRAINT chk_ata_replacement_test_identificador');
}

[$root, $source, $oldRelative, $oldFull] = ataTestReset($pdo);
$storage = new AtaReplacementTestStorage($root);
$storage->failDelete[$oldFull] = true;
$service = new SalaAudienciasAtaService($pdo, $storage);
$service->uploadPdf('PAT-ATA-0000000000000000000001', ['name' => 'ata-nova.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], 'USR-ATA-0000000000000000000001');
$row = ataTestCurrentRow($pdo);
ataTestAssert($row['arquivo_relativo'] !== $oldRelative, 'Falha de limpeza pós-commit não pode reverter a nova referência.');
ataTestAssert(is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $row['arquivo_relativo'])), 'Nova ata deve permanecer disponível quando a limpeza do antigo falha.');
ataTestAssert(is_file($oldFull), 'Falha de limpeza deve preservar o antigo como órfão seguro.');

[$root, $source, $oldRelative, $oldFull] = ataTestReset($pdo);
$pdo->exec("UPDATE sala_audiencias_ata SET arquivo_relativo = '', tamanho_bytes = 0");
$storage = new AtaReplacementTestStorage($root);
$service = new SalaAudienciasAtaService($pdo, $storage);
try {
    $service->uploadPdf('PAT-ATA-0000000000000000000001', ['name' => 'ata-nova.pdf', 'type' => 'application/pdf', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)], 'USR-ATA-0000000000000000000001');
    throw new RuntimeException('Ata expirada não pode aceitar novo PDF.');
} catch (InvalidArgumentException $e) {
    ataTestAssert(ataTestCurrentRow($pdo)['arquivo_relativo'] === '', 'Ata expirada deve continuar sem referência de arquivo.');
    $stored = glob($root . DIRECTORY_SEPARATOR . 'sala-audiencias-atas' . DIRECTORY_SEPARATOR . '*') ?: [];
    ataTestAssert(count($stored) === 1 && basename($stored[0]) === 'ata_antiga.pdf', 'Rejeição de ata expirada deve remover somente o PDF novo criado.');
}

echo "PASS ata-replacement\n";
