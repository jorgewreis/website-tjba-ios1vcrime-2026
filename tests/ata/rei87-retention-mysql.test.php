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
    public function __construct(private string $root) {}
    public function rootPath(): string { return $this->root; }
    public function isUploadedFile(string $path): bool { return false; }
    public function moveUploadedFile(string $source, string $destination): bool { return false; }
    public function isFile(string $path): bool { return is_file($path); }
    public function deleteFile(string $path): bool
    {
        if ($this->failDelete) return false;
        if (!file_exists($path) && !is_link($path)) return true;
        return @unlink($path);
    }
}

function rei87Assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 30]);
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei87_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
rei87Assert((int) $guard->fetchColumn() === 1, 'Disposable MySQL ownership marker mismatch.');
$pdo->exec("CREATE TABLE sala_audiencias_ata (
    id_ata VARCHAR(40) PRIMARY KEY,
    pauta VARCHAR(40) NOT NULL,
    arquivo_relativo VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    tamanho_bytes INT NOT NULL DEFAULT 0,
    KEY idx_ata_retention (expires_at,id_ata)
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

echo "PASS: REI-87 retention retry, path confinement, expiry checks and row locks on isolated MySQL/InnoDB\n";
