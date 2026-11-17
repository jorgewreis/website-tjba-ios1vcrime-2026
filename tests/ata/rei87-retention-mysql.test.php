<?php
declare(strict_types=1);

$runId = (string) getenv('REI87_TEST_RUN_ID');
$marker = (string) getenv('REI87_TEST_DISPOSABLE_MARKER');
$dsn = (string) getenv('REI87_MYSQL_DSN');
if (getenv('REI87_TEST_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei87_test;charset=utf8mb4'
    || getenv('REI87_MYSQL_USER') !== 'root') {
    fwrite(STDERR, "FAIL CLOSED: disposable MySQL fixture configuration invalid\n");
    exit(90);
}

require_once '/repo/app/services/SalaAudienciasAtaStorage.php';
require_once '/repo/app/services/SalaAudienciasAtaService.php';

final class Rei87MysqlTestStorage implements SalaAudienciasAtaStorage
{
    public bool $failDelete = false;
    public function __construct(
        private string $root,
        private ?string $deleteLockedMarker = null,
        private ?string $releaseDeleteMarker = null,
        private ?string $uploadMovedMarker = null
    ) {}
    public function rootPath(): string { return $this->root; }
    public function isUploadedFile(string $path): bool { return is_file($path); }
    public function moveUploadedFile(string $source, string $destination): bool
    {
        if (!@copy($source, $destination)) return false;
        if ($this->uploadMovedMarker !== null) file_put_contents($this->uploadMovedMarker, 'moved');
        return true;
    }
    public function isFile(string $path): bool { return is_file($path); }
    public function deleteFile(string $path): bool
    {
        if ($this->failDelete) return false;
        if ($this->deleteLockedMarker !== null) {
            file_put_contents($this->deleteLockedMarker, 'locked');
            $deadline = microtime(true) + 10;
            while ($this->releaseDeleteMarker === null || !is_file($this->releaseDeleteMarker)) {
                if (microtime(true) >= $deadline) throw new RuntimeException('Retention lock barrier expired.');
                usleep(1000);
            }
        }
        if (!file_exists($path) && !is_link($path)) return true;
        return @unlink($path);
    }
}

function rei87Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function rei87WaitForFile(string $path, string $message): void
{
    $deadline = microtime(true) + 10;
    while (!is_file($path)) {
        if (microtime(true) >= $deadline) throw new RuntimeException($message);
        usleep(1000);
    }
}

function rei87WaitForLock(PDO $pdo, int $connectionId, bool $waiting, ?int $blockingConnectionId = null): void
{
    $sql = $waiting
        ? 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w
           JOIN performance_schema.data_locks requested ON requested.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID
           JOIN performance_schema.threads requesting_thread ON requesting_thread.THREAD_ID=w.REQUESTING_THREAD_ID
           JOIN performance_schema.threads blocking_thread ON blocking_thread.THREAD_ID=w.BLOCKING_THREAD_ID
           WHERE requesting_thread.PROCESSLIST_ID=:connection_id
             AND requested.OBJECT_SCHEMA=DATABASE() AND requested.OBJECT_NAME=\'sala_audiencias_ata\'
             AND requested.LOCK_TYPE=\'RECORD\''
        : 'SELECT COUNT(*) FROM performance_schema.data_locks l JOIN performance_schema.threads t ON t.THREAD_ID=l.THREAD_ID WHERE t.PROCESSLIST_ID=:connection_id AND l.OBJECT_SCHEMA=DATABASE() AND l.OBJECT_NAME=\'sala_audiencias_ata\' AND l.LOCK_TYPE=\'RECORD\' AND l.LOCK_MODE LIKE \'X%\'';
    if ($waiting && $blockingConnectionId !== null) $sql .= ' AND blocking_thread.PROCESSLIST_ID=:blocking_connection_id';
    $statement = $pdo->prepare($sql);
    $deadline = microtime(true) + 10;
    do {
        $params = [':connection_id' => $connectionId];
        if ($waiting && $blockingConnectionId !== null) $params[':blocking_connection_id'] = $blockingConnectionId;
        $statement->execute($params);
        if ((int) $statement->fetchColumn() > 0) return;
        usleep(1000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException($waiting ? 'Worker never entered an InnoDB lock wait.' : 'Worker never held an InnoDB row lock.');
}

function rei87WaitForProcessState(PDO $pdo, int $connectionId, string $expectedState): void
{
    $statement = $pdo->prepare('SELECT PROCESSLIST_STATE FROM performance_schema.threads WHERE PROCESSLIST_ID=:connection_id');
    $deadline = microtime(true) + 10;
    do {
        $statement->execute([':connection_id' => $connectionId]);
        if ($statement->fetchColumn() === $expectedState) return;
        usleep(1000);
    } while (microtime(true) < $deadline);
    throw new RuntimeException('Upload did not enter the expected MySQL state: ' . $expectedState);
}

function rei87StartWorker(string $mode, string $root, string $pautaId, string $markerDirectory, ?string $source = null): array
{
    $command = [PHP_BINARY, __FILE__, '--worker', $mode, $root, $pautaId, $markerDirectory];
    if ($source !== null) $command[] = $source;
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start disposable race worker.');
    fclose($pipes[0]);
    return ['process' => $process, 'pipes' => $pipes, 'mode' => $mode, 'markerDirectory' => $markerDirectory];
}

function rei87FinishWorker(array $worker): array
{
    $deadline = microtime(true) + 15;
    do {
        $status = proc_get_status($worker['process']);
        if (!$status['running']) break;
        if (microtime(true) >= $deadline) {
            proc_terminate($worker['process']);
            throw new RuntimeException('Disposable race worker timed out: ' . $worker['mode']);
        }
        usleep(1000);
    } while (true);
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    $exit = proc_close($worker['process']);
    if ($exit !== 0) throw new RuntimeException('Disposable race worker failed (' . $worker['mode'] . '): ' . trim($stderr));
    $result = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($result)) throw new RuntimeException('Disposable race worker returned invalid JSON.');
    return $result;
}

$workerMode = (string) ($argv[1] ?? '');
if ($workerMode === '--worker') {
    $mode = (string) ($argv[2] ?? '');
    $workerRoot = (string) ($argv[3] ?? '');
    $pautaId = (string) ($argv[4] ?? '');
    $markerDirectory = (string) ($argv[5] ?? '');
    $source = (string) ($argv[6] ?? '');
    $expectedWorkerRoot = '/tmp/rei87-' . $runId;
    $expectedMarkerDirectory = in_array($pautaId, ['race1', 'race2'], true)
        ? $expectedWorkerRoot . ($pautaId === 'race1' ? '/race-one' : '/race-two')
        : '';
    $expectedSource = $mode === 'upload' ? $expectedWorkerRoot . '/' . $pautaId . '-upload.pdf' : '';
    if (!in_array($mode, ['purge', 'upload'], true)
        || $workerRoot !== $expectedWorkerRoot
        || !preg_match('/^race[12]$/', $pautaId)
        || $markerDirectory !== $expectedMarkerDirectory
        || ($mode === 'upload' && ($source !== $expectedSource || realpath($source) !== $expectedSource))) {
        fwrite(STDERR, "FAIL CLOSED: invalid disposable race worker parameters\n");
        exit(91);
    }
    try {
        $workerPdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
        $workerPdo->exec('SET SESSION innodb_lock_wait_timeout=10');
        file_put_contents($markerDirectory . '/' . $mode . '.connection', (string) $workerPdo->query('SELECT CONNECTION_ID()')->fetchColumn());
        $workerStorage = $mode === 'purge'
            ? new Rei87MysqlTestStorage(
                $workerRoot,
                $markerDirectory . '/purge-delete-locked',
                $markerDirectory . '/release-purge-delete'
            )
            : new Rei87MysqlTestStorage($workerRoot, null, null, $markerDirectory . '/upload-moved');
        $workerService = new SalaAudienciasAtaService($workerPdo, $workerStorage);
        if ($mode === 'purge') {
            echo json_encode(['ok' => true, 'result' => $workerService->purgeAtasExpiradas()], JSON_THROW_ON_ERROR) . PHP_EOL;
            exit(0);
        }
        try {
            $workerService->uploadPdf($pautaId, [
                'name' => 'replacement.pdf', 'type' => 'application/pdf', 'tmp_name' => $source,
                'error' => UPLOAD_ERR_OK, 'size' => filesize($source),
            ], 'synthetic-user');
            echo json_encode(['ok' => true, 'result' => 'uploaded'], JSON_THROW_ON_ERROR) . PHP_EOL;
        } catch (InvalidArgumentException $e) {
            echo json_encode(['ok' => true, 'result' => 'rejected', 'reason' => $e->getMessage()], JSON_THROW_ON_ERROR) . PHP_EOL;
        }
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n");
        exit(92);
    }
}

$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei87_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
rei87Assert((int) $guard->fetchColumn() === 1, 'Disposable MySQL ownership marker mismatch.');
$pdo->exec("CREATE TABLE sala_audiencias_ata (
    id_ata VARCHAR(40) PRIMARY KEY,
    pauta VARCHAR(40) NOT NULL,
    arquivo_relativo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL DEFAULT '',
    mime VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    expires_at DATETIME NOT NULL,
    uploaded_at DATETIME NULL,
    uploaded_by VARCHAR(40) NULL,
    tamanho_bytes INT NOT NULL DEFAULT 0,
    KEY idx_ata_retention (expires_at,id_ata)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE processos (
    id VARCHAR(40) PRIMARY KEY,
    numero_processo VARCHAR(40) NULL,
    sigiloso TINYINT NOT NULL DEFAULT 0,
    classe_id VARCHAR(40) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE classes (id VARCHAR(40) PRIMARY KEY, nome VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE sala_audiencias_pauta (
    id_pauta VARCHAR(40) PRIMARY KEY,
    processo VARCHAR(40) NULL,
    status VARCHAR(30) NOT NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    data_audiencia DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$engine = (string) $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sala_audiencias_ata'")->fetchColumn();
rei87Assert(strcasecmp($engine, 'InnoDB') === 0, 'Ata fixture must use InnoDB.');
$root = '/tmp/rei87-' . $runId;
$ataDirectory = $root . '/sala-audiencias-atas';
if (!mkdir($ataDirectory, 0700, true) && !is_dir($ataDirectory)) throw new RuntimeException('Could not create disposable ata storage directory.');
$storage = new Rei87MysqlTestStorage($root);
$service = new SalaAudienciasAtaService($pdo, $storage);
$insert = $pdo->prepare('INSERT INTO sala_audiencias_ata (id_ata,pauta,arquivo_relativo,expires_at,tamanho_bytes) VALUES (:id,:pauta,:path,:expires,12)');
$seed = static function (string $id, string $relative, string $expirySql) use ($insert): void {
    $insert->execute([':id' => $id, ':pauta' => 'pauta-' . $id, ':path' => $relative, ':expires' => $expirySql]);
};
$readAta = static function (PDO $pdo, string $id): array {
    $statement = $pdo->prepare('SELECT arquivo_relativo,tamanho_bytes,expires_at FROM sala_audiencias_ata WHERE id_ata=:id');
    $statement->execute([':id' => $id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Synthetic ata row disappeared: ' . $id);
    return $row;
};

$presentPath = 'sala-audiencias-atas/present.pdf';
$missingPath = 'sala-audiencias-atas/missing.pdf';
$futurePath = 'sala-audiencias-atas/future.pdf';
file_put_contents($ataDirectory . '/present.pdf', 'synthetic-pdf');
file_put_contents($ataDirectory . '/future.pdf', 'synthetic-future-pdf');
$seed('expired-present', $presentPath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$seed('expired-missing', $missingPath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$seed('future', $futurePath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$result = $service->purgeAtasExpiradas();
rei87Assert($result === ['processed' => 2, 'failed' => 0], 'Expired files and already-absent paths should process; future rows should remain untouched.');
rei87Assert(!file_exists($ataDirectory . '/present.pdf') && file_exists($ataDirectory . '/future.pdf'), 'Retention removed the wrong filesystem entries.');
rei87Assert($readAta($pdo, 'expired-present')['arquivo_relativo'] === '' && (int) $readAta($pdo, 'expired-present')['tamanho_bytes'] === 0, 'Expired file reference was not cleared.');
rei87Assert($readAta($pdo, 'expired-missing')['arquivo_relativo'] === '', 'Already-absent expired file reference was not cleared.');
rei87Assert($readAta($pdo, 'future')['arquivo_relativo'] === $futurePath, 'Future ata reference must be preserved.');

$deleteFailurePath = 'sala-audiencias-atas/delete-failure.pdf';
file_put_contents($ataDirectory . '/delete-failure.pdf', 'synthetic-pdf');
$seed('delete-failure', $deleteFailurePath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 2 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$storage->failDelete = true;
rei87Assert($service->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'Storage deletion failure must be reported.');
rei87Assert(file_exists($ataDirectory . '/delete-failure.pdf') && $readAta($pdo, 'delete-failure')['arquivo_relativo'] === $deleteFailurePath, 'Storage failure must preserve file and DB reference.');
$storage->failDelete = false;
rei87Assert($service->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 0], 'Storage failure should be retryable.');

$pdo->exec("CREATE TRIGGER rei87_fail_reference_clear BEFORE UPDATE ON sala_audiencias_ata FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic clear failure'");
file_put_contents($ataDirectory . '/db-failure.pdf', 'synthetic-pdf');
$dbFailurePath = 'sala-audiencias-atas/db-failure.pdf';
$seed('db-failure', $dbFailurePath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 4 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
rei87Assert($service->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'DB update failure must be reported.');
rei87Assert(!file_exists($ataDirectory . '/db-failure.pdf') && $readAta($pdo, 'db-failure')['arquivo_relativo'] === $dbFailurePath, 'DB update failure must preserve the reference for a retry.');
$pdo->exec('DROP TRIGGER rei87_fail_reference_clear');
rei87Assert($service->purgeAtasExpiradas() === ['processed' => 1, 'failed' => 0], 'Missing file after DB rollback must allow a later reference clear.');
rei87Assert($readAta($pdo, 'db-failure')['arquivo_relativo'] === '', 'Retry did not clear the stale file reference.');

$invalidPath = 'sala-audiencias-atas/../outside.pdf';
file_put_contents($root . '/outside.pdf', 'must-not-delete');
$seed('invalid-path', $invalidPath, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
rei87Assert($service->purgeAtasExpiradas() === ['processed' => 0, 'failed' => 1], 'Invalid stored path must be reported as a retryable failure.');
rei87Assert(file_exists($root . '/outside.pdf') && $readAta($pdo, 'invalid-path')['arquivo_relativo'] === $invalidPath, 'Invalid path must not delete external file or clear its reference.');
$pdo->exec("UPDATE sala_audiencias_ata SET expires_at=DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id_ata='invalid-path'");

$insertPauta = $pdo->prepare('INSERT INTO sala_audiencias_pauta (id_pauta,processo,status,ativo,data_audiencia) VALUES (:id,NULL,\'realizada\',1,CURDATE())');
$insertPauta->execute([':id' => 'race1']);
$raceOneRelative = 'sala-audiencias-atas/race1-old.pdf';
file_put_contents($ataDirectory . '/race1-old.pdf', 'expired synthetic ata');
$seed('race1-ata', $raceOneRelative, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$pdo->prepare('UPDATE sala_audiencias_ata SET pauta=:pauta WHERE id_ata=:id')->execute([':pauta' => 'race1', ':id' => 'race1-ata']);
$raceOneDirectory = $root . '/race-one';
mkdir($raceOneDirectory, 0700, true);
$raceOneSource = $root . '/race1-upload.pdf';
file_put_contents($raceOneSource, "%PDF-1.4\nsynthetic upload\n");
$purgeWorker = rei87StartWorker('purge', $root, 'race1', $raceOneDirectory);
rei87WaitForFile($raceOneDirectory . '/purge-delete-locked', 'Retention worker did not lock the expired row.');
$uploadWorker = rei87StartWorker('upload', $root, 'race1', $raceOneDirectory, $raceOneSource);
rei87WaitForFile($raceOneDirectory . '/upload-moved', 'Upload worker did not stage its synthetic PDF.');
$uploadConnectionId = (int) file_get_contents($raceOneDirectory . '/upload.connection');
$purgeConnectionId = (int) file_get_contents($raceOneDirectory . '/purge.connection');
rei87WaitForLock($pdo, $uploadConnectionId, true, $purgeConnectionId);
file_put_contents($raceOneDirectory . '/release-purge-delete', 'release');
$purgeResult = rei87FinishWorker($purgeWorker);
$uploadResult = rei87FinishWorker($uploadWorker);
rei87Assert(($purgeResult['result'] ?? null) === ['processed' => 1, 'failed' => 0], 'Retention-first race must finish with the expired file removed.');
rei87Assert(
    ($uploadResult['result'] ?? null) === 'rejected'
        && str_contains((string) ($uploadResult['reason'] ?? ''), 'prazo de disponibilidade'),
    'Upload waiting behind retention must reject the cleared expired ata: ' . json_encode($uploadResult)
);
rei87Assert($readAta($pdo, 'race1-ata')['arquivo_relativo'] === '', 'Retention-first race must leave the expired reference cleared.');
rei87Assert(!file_exists($ataDirectory . '/race1-old.pdf') && count(glob($ataDirectory . '/ata_race1_*.pdf') ?: []) === 0, 'Retention-first race must remove the old file and failed staged replacement.');

$insertPauta->execute([':id' => 'race2']);
$raceTwoRelative = 'sala-audiencias-atas/race2-old.pdf';
file_put_contents($ataDirectory . '/race2-old.pdf', 'expired synthetic ata');
$seed('race2-ata', $raceTwoRelative, (string) $pdo->query('SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 DAY), "%Y-%m-%d %H:%i:%s")')->fetchColumn());
$pdo->prepare('UPDATE sala_audiencias_ata SET pauta=:pauta WHERE id_ata=:id')->execute([':pauta' => 'race2', ':id' => 'race2-ata']);
$raceTwoDirectory = $root . '/race-two';
mkdir($raceTwoDirectory, 0700, true);
$raceTwoSource = $root . '/race2-upload.pdf';
file_put_contents($raceTwoSource, "%PDF-1.4\nsynthetic upload\n");
$pdo->exec("CREATE TRIGGER rei87_hold_upload_lock BEFORE UPDATE ON sala_audiencias_ata FOR EACH ROW BEGIN IF NEW.arquivo_relativo <> OLD.arquivo_relativo AND NEW.arquivo_relativo <> '' THEN DO SLEEP(5); END IF; END");
$uploadWorker = rei87StartWorker('upload', $root, 'race2', $raceTwoDirectory, $raceTwoSource);
rei87WaitForFile($raceTwoDirectory . '/upload-moved', 'Upload-first worker did not stage its synthetic PDF.');
$uploadConnectionId = (int) file_get_contents($raceTwoDirectory . '/upload.connection');
rei87WaitForLock($pdo, $uploadConnectionId, false);
rei87WaitForProcessState($pdo, $uploadConnectionId, 'User sleep');
$purgeWorker = rei87StartWorker('purge', $root, 'race2', $raceTwoDirectory);
rei87WaitForFile($raceTwoDirectory . '/purge.connection', 'Retention worker did not connect.');
$purgeConnectionId = (int) file_get_contents($raceTwoDirectory . '/purge.connection');
rei87WaitForLock($pdo, $purgeConnectionId, true, $uploadConnectionId);
$uploadResult = rei87FinishWorker($uploadWorker);
$purgeResult = rei87FinishWorker($purgeWorker);
$pdo->exec('DROP TRIGGER rei87_hold_upload_lock');
$raceTwoRow = $readAta($pdo, 'race2-ata');
$raceTwoCurrentPath = (string) $raceTwoRow['arquivo_relativo'];
$raceTwoCurrentFile = $root . '/' . str_replace('/', '/', $raceTwoCurrentPath);
rei87Assert(($uploadResult['result'] ?? null) === 'uploaded', 'Upload-first race must commit the replacement.');
rei87Assert(($purgeResult['result'] ?? null) === ['processed' => 0, 'failed' => 0], 'Retention must skip the replacement whose expiry advanced while waiting.');
rei87Assert($raceTwoCurrentPath !== '' && strtotime((string) $raceTwoRow['expires_at']) > time() && is_file($raceTwoCurrentFile), 'Upload-first race must preserve a live replacement reference and file.');
rei87Assert(!file_exists($ataDirectory . '/race2-old.pdf'), 'Upload-first race must remove the old file only after the replacement commits.');

echo "PASS: REI-87 retention retry, path confinement, expiry checks and purge/upload races on isolated MySQL/InnoDB\n";
