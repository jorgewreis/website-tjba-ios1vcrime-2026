<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaStorage.php';
require_once dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaService.php';

final class ReadPurityPdo extends PDO
{
    /** @var list<string> */
    public array $statements = [];
    public int $mutationStatements = 0;
    /** @var list<array<string, mixed>> */
    public array $expiredRows = [];
    /** @var array<string, mixed>|null */
    public ?array $lockedRow = null;
    /** @var array<string, array<string, mixed>> */
    public array $lockedRows = [];
    public bool $transactionActive = false;
    /** @var list<string> */
    public array $transactionEvents = [];
    public bool $clearReferenceSucceeds = true;
    public int $clearAttempts = 0;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new ReadPurityStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new ReadPurityStatement($this, $query);
    }

    public function beginTransaction(): bool { $this->transactionEvents[] = 'begin'; $this->transactionActive = true; return true; }
    public function commit(): bool { $this->transactionEvents[] = 'commit'; $this->transactionActive = false; return true; }
    public function rollBack(): bool { $this->transactionEvents[] = 'rollback'; $this->transactionActive = false; return true; }
    public function inTransaction(): bool { return $this->transactionActive; }
}

final class ReadPurityStatement extends PDOStatement
{
    /** @var array<string|int, mixed> */
    private array $values = [];

    public function __construct(private ReadPurityPdo $owner, private string $sql) {}

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->values[$param] = $value;
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->owner->statements[] = $this->sql;
        if (preg_match('/^\s*(UPDATE|DELETE|INSERT|REPLACE|ALTER|DROP|CREATE)\b/i', $this->sql) === 1) {
            $this->owner->mutationStatements++;
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'FOR UPDATE')) {
            $id = (string) ($this->values[':id'] ?? '');
            return $this->owner->lockedRows[$id] ?? $this->owner->lockedRow ?? false;
        }
        return false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (str_contains($this->sql, 'SELECT `id_ata` FROM `sala_audiencias_ata`')) return $this->owner->expiredRows;
        return [];
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return 0;
    }

    public function rowCount(): int
    {
        if (str_contains($this->sql, 'SET `arquivo_relativo`')) {
            $this->owner->clearAttempts++;
            return $this->owner->clearReferenceSucceeds ? 1 : 0;
        }
        return 0;
    }
}

final class ReadPurityStorage implements SalaAudienciasAtaStorage
{
    public bool $failDelete = false;
    public function __construct(private string $root) {}
    public function rootPath(): string { return $this->root; }
    public function isUploadedFile(string $path): bool { return false; }
    public function moveUploadedFile(string $source, string $destination): bool { return false; }
    public function isFile(string $path): bool { return is_file($path); }
    public function deleteFile(string $path): bool
    {
        if ($this->failDelete) return false;
        if (!file_exists($path) && !is_link($path)) return true;
        return unlink($path);
    }
}

function assertReadPurity(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = new ReadPurityPdo();
$storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ata-read-purity-' . bin2hex(random_bytes(8));
mkdir($storageRoot, 0700, true);
mkdir($storageRoot . DIRECTORY_SEPARATOR . 'sala-audiencias-atas', 0700);
$service = new SalaAudienciasAtaService($pdo, new ReadPurityStorage($storageRoot));

try {
    $service->listRealizadasParaUpload(25, 0);
    $service->listMinhasInscricoesEstudante('synthetic-user');
    try { $service->getDownloadPayloadForEstudante('synthetic-pauta', 'synthetic-user'); }
    catch (InvalidArgumentException) { /* fake DB denies the synthetic enrollment */ }
    try { $service->getDownloadPayloadInterno('synthetic-pauta'); }
    catch (InvalidArgumentException) { /* fake DB has no synthetic pauta */ }

    assertReadPurity($pdo->mutationStatements === 0, 'List and download methods must not issue SQL mutations.');

    $source = file(dirname(__DIR__, 2) . '/app/services/SalaAudienciasAtaService.php');
    if ($source === false) throw new RuntimeException('Could not read ata service source.');
    foreach (['listRealizadasParaUpload', 'listMinhasInscricoesEstudante', 'uploadPdf', 'getDownloadPayloadForEstudante', 'getDownloadPayloadInterno'] as $methodName) {
        $method = new ReflectionMethod(SalaAudienciasAtaService::class, $methodName);
        $methodSource = implode("\n", array_slice($source, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        assertReadPurity(!str_contains($methodSource, 'purgeAtasExpiradas('), $methodName . ' must not invoke global retention.');
    }
    $repositorySource = file(dirname(__DIR__, 2) . '/app/repositories/SalaAudienciasAtaRepository.php');
    if ($repositorySource === false) throw new RuntimeException('Could not read ata repository source.');
    $repositorySourceText = implode("\n", $repositorySource);
    assertReadPurity(str_contains($repositorySourceText, 'LIMIT 1 FOR UPDATE'), 'Retention must revalidate the expired row under a database lock.');
    assertReadPurity(str_contains($repositorySourceText, '`arquivo_relativo` = :rel AND `expires_at` = :expires'), 'Retention must clear only the locked file reference and expiry it inspected.');
    $maintenanceSource = (new ReflectionMethod(SalaAudienciasAtaService::class, 'purgeAtasExpiradas'));
    $maintenanceLines = implode("\n", array_slice($source, $maintenanceSource->getStartLine() - 1, $maintenanceSource->getEndLine() - $maintenanceSource->getStartLine() + 1));
    $lockPosition = strpos($maintenanceLines, 'findExpiredByIdForUpdate');
    $deletePosition = strpos($maintenanceLines, 'deleteFile');
    $clearPosition = strpos($maintenanceLines, 'clearArquivoAposExpiracaoRetencao');
    assertReadPurity($lockPosition !== false && $deletePosition !== false && $clearPosition !== false && $lockPosition < $deletePosition, 'Retention must lock/revalidate before deleting the file.');
    assertReadPurity($deletePosition !== false && $clearPosition !== false && $deletePosition < $clearPosition, 'Retention must preserve the DB reference until file deletion succeeds.');

    $resolve = new ReflectionMethod(SalaAudienciasAtaService::class, 'resolveExpiredAtaPath');
    $resolve->setAccessible(true);
    $valid = $resolve->invoke($service, 'sala-audiencias-atas/ata_synthetic_20260101.pdf');
    assertReadPurity(is_string($valid) && str_starts_with($valid, $storageRoot), 'A valid stored ata path must remain inside the configured storage root.');
    assertReadPurity($resolve->invoke($service, '../outside.pdf') === null, 'Traversal path must be rejected.');
    assertReadPurity($resolve->invoke($service, 'sala-audiencias-atas/../outside.pdf') === null, 'Nested traversal path must be rejected.');
    assertReadPurity($resolve->invoke($service, 'other/ata.pdf') === null, 'A file outside the ata directory must be rejected.');

    $relative = 'sala-audiencias-atas/ata_synthetic_20260101.pdf';
    $full = $storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    file_put_contents($full, 'synthetic-pdf');
    $pdo->expiredRows = [['id_ata' => 'synthetic-ata']];
    $pdo->lockedRow = ['id_ata' => 'synthetic-ata', 'arquivo_relativo' => $relative, 'expires_at' => '2000-01-01 00:00:00'];
    assertReadPurity($service->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 0], 'Explicit maintenance must process an expired ata.');
    assertReadPurity(!file_exists($full) && $pdo->clearAttempts === 1 && !$pdo->inTransaction() && $pdo->transactionEvents === ['begin', 'commit'], 'Successful retention must delete the file and clear its reference in a committed transaction.');

    $failurePdo = new ReadPurityPdo();
    $failurePdo->expiredRows = [['id_ata' => 'synthetic-retry']];
    $failurePdo->lockedRow = ['id_ata' => 'synthetic-retry', 'arquivo_relativo' => $relative, 'expires_at' => '2000-01-01 00:00:00'];
    $failureStorage = new ReadPurityStorage($storageRoot);
    $failureStorage->failDelete = true;
    file_put_contents($full, 'synthetic-pdf');
    $retryService = new SalaAudienciasAtaService($failurePdo, $failureStorage);
    assertReadPurity($retryService->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'Failed unlink must be reported as retryable.');
    assertReadPurity(file_exists($full) && $failurePdo->clearAttempts === 0 && !$failurePdo->inTransaction() && $failurePdo->transactionEvents === ['begin', 'rollback'], 'Failed file deletion must preserve the row for retry and roll back.');
    $failureStorage->failDelete = false;
    assertReadPurity($retryService->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 0] && !file_exists($full), 'A later explicit retention attempt must succeed.');

    $dbFailurePdo = new ReadPurityPdo();
    $dbFailurePdo->expiredRows = [['id_ata' => 'synthetic-db-retry']];
    $dbFailurePdo->lockedRow = ['id_ata' => 'synthetic-db-retry', 'arquivo_relativo' => $relative, 'expires_at' => '2000-01-01 00:00:00'];
    $dbFailurePdo->clearReferenceSucceeds = false;
    file_put_contents($full, 'synthetic-pdf');
    $dbRetryService = new SalaAudienciasAtaService($dbFailurePdo, new ReadPurityStorage($storageRoot));
    assertReadPurity($dbRetryService->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'Failed database clear must be reported as retryable.');
    assertReadPurity(!file_exists($full) && $dbFailurePdo->clearAttempts === 1 && !$dbFailurePdo->inTransaction() && $dbFailurePdo->transactionEvents === ['begin', 'rollback'], 'A failed database clear must roll back and leave the reference eligible for retry.');
    $dbFailurePdo->clearReferenceSucceeds = true;
    assertReadPurity($dbRetryService->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 0] && $dbFailurePdo->clearAttempts === 2 && $dbFailurePdo->transactionEvents === ['begin', 'rollback', 'begin', 'commit'], 'Retry must clear the reference when the file is already absent.');

    $badPathPdo = new ReadPurityPdo();
    $badPathPdo->expiredRows = [['id_ata' => 'synthetic-traversal']];
    $badPathPdo->lockedRow = ['id_ata' => 'synthetic-traversal', 'arquivo_relativo' => 'sala-audiencias-atas/../outside.pdf', 'expires_at' => '2000-01-01 00:00:00'];
    $badPathService = new SalaAudienciasAtaService($badPathPdo, new ReadPurityStorage($storageRoot));
    assertReadPurity($badPathService->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'Traversal must be reported as a failed item.');
    assertReadPurity($badPathPdo->clearAttempts === 0 && !$badPathPdo->inTransaction(), 'Traversal must not clear the ata reference.');

    $partialPdo = new ReadPurityPdo();
    $partialPdo->expiredRows = [['id_ata' => 'synthetic-poison'], ['id_ata' => 'synthetic-valid']];
    $partialPdo->lockedRows = [
        'synthetic-poison' => ['id_ata' => 'synthetic-poison', 'arquivo_relativo' => 'sala-audiencias-atas/../outside.pdf', 'expires_at' => '2000-01-01 00:00:00'],
        'synthetic-valid' => ['id_ata' => 'synthetic-valid', 'arquivo_relativo' => $relative, 'expires_at' => '2001-01-01 00:00:00'],
    ];
    file_put_contents($full, 'synthetic-pdf');
    $partialService = new SalaAudienciasAtaService($partialPdo, new ReadPurityStorage($storageRoot));
    assertReadPurity($partialService->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 1], 'An invalid expired path must not block later safe rows in the same batch.');
    assertReadPurity(!file_exists($full) && $partialPdo->clearAttempts === 1 && $partialPdo->transactionEvents === ['begin', 'rollback', 'begin', 'commit'], 'Batch retention must isolate item failure and continue safely.');

    $externalTxPdo = new ReadPurityPdo();
    $externalTxPdo->transactionActive = true;
    try { (new SalaAudienciasAtaService($externalTxPdo, new ReadPurityStorage($storageRoot)))->purgeAtasExpiradas(); throw new RuntimeException('Retention must refuse a caller transaction.'); }
    catch (LogicException) {}
    assertReadPurity($externalTxPdo->transactionActive && $externalTxPdo->mutationStatements === 0, 'Retention must not take ownership of an external transaction.');
} finally {
    rmdir($storageRoot . DIRECTORY_SEPARATOR . 'sala-audiencias-atas');
    rmdir($storageRoot);
}

echo "PASS ata read-purity, retention retry, transaction and storage-path fixture\n";
