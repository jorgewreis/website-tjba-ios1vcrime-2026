<?php
declare(strict_types=1);

// Incluir apenas após rei152-documentos-mysql.test.php, no runner descartável CI.
$dsnGate = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4';
$markerGate = (string) getenv('REI152_CI_MARKER');
if (!is_file('/.dockerenv') || realpath(__DIR__) !== '/repo/tests'
    || getenv('REI152_CI_REQUIRED') !== '1' || getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql'
    || getenv('REI152_MYSQL_DSN') !== $dsnGate || getenv('REI152_MYSQL_USER') !== 'root'
    || (string) getenv('REI152_MYSQL_PASSWORD') !== '' || !preg_match('/^[a-f0-9]{64}$/D', $markerGate)
    || !isset($pdo) || !$pdo instanceof PDO || !function_exists('rei152AssertCiConnection')) {
    throw new RuntimeException('Identidade do laboratório CI recusada.');
}
rei152AssertCiConnection($pdo);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei152_test') {
    throw new RuntimeException('Banco sintético inesperado.');
}
$tableGate = 'rei152_gate_' . bin2hex(random_bytes(12));
$existsGate = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
$existsGate->execute([$tableGate]);
if ((int) $existsGate->fetchColumn() !== 0) throw new RuntimeException('Nome da fixture já existe.');
$createdGate = false;
$blockerGate = null;
$savedTimeoutGate = null;
$failureGate = null;
$cleanupErrorsGate = [];
try {
    $pdo->exec('CREATE TABLE `' . $tableGate . '` (id INT PRIMARY KEY) ENGINE=InnoDB');
    $createdGate = true;
    $savedTimeoutGate = (int) $pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
    $pdo->exec('SET SESSION lock_wait_timeout = 20');
    $originalTimeoutGate = 20;
    $blockerGate = new PDO($dsnGate, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    rei152AssertCiConnection($blockerGate);
    $blockerGate->exec('SET SESSION lock_wait_timeout = 1');
    $pdo->beginTransaction();
    ParteDocumentoLock::requireTransactionalTables($pdo, [$tableGate]);
    $ddlBlockedGate = false;
    try {
        $blockerGate->exec('ALTER TABLE `' . $tableGate . '` ADD COLUMN marker INT NULL');
    } catch (PDOException $errorGate) {
        if ((int) ($errorGate->errorInfo[1] ?? 0) !== 1205) throw $errorGate;
        $ddlBlockedGate = true;
    }
    if (!$ddlBlockedGate) throw new RuntimeException('DDL não aguardou o metadata lock retido.');
    $pdo->rollBack();
    $blockerGate->exec('ALTER TABLE `' . $tableGate . '` ADD COLUMN marker INT NULL');
    $pdo->beginTransaction();
    ParteDocumentoLock::requireTransactionalTables($pdo, [$tableGate]);
    $ddlBlockedGate = false;
    try {
        $blockerGate->exec('ALTER TABLE `' . $tableGate . '` ADD COLUMN marker_commit INT NULL');
    } catch (PDOException $errorGate) {
        if ((int) ($errorGate->errorInfo[1] ?? 0) !== 1205) throw $errorGate;
        $ddlBlockedGate = true;
    }
    if (!$ddlBlockedGate) throw new RuntimeException('DDL não aguardou o metadata lock antes do commit.');
    $pdo->commit();
    $blockerGate->exec('ALTER TABLE `' . $tableGate . '` ADD COLUMN marker_commit INT NULL');
    if ((int) $pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== $originalTimeoutGate) {
        throw new RuntimeException('Timeout não restaurado após sucesso.');
    }

    $pdo->beginTransaction();
    $invalidRejectedGate = false;
    try {
        ParteDocumentoLock::requireTransactionalTables($pdo, ['invalida` ; SELECT 1']);
    } catch (InvalidArgumentException $errorGate) {
        $invalidRejectedGate = true;
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if (!$invalidRejectedGate) throw new RuntimeException('Nome inválido não foi recusado.');

    $pdo->exec('ALTER TABLE `' . $tableGate . '` ENGINE=MyISAM');
    $pdo->beginTransaction();
    $rejectedGate = false;
    try {
        ParteDocumentoLock::requireTransactionalTables($pdo, [$tableGate]);
    } catch (RuntimeException $errorGate) {
        if (!str_contains($errorGate->getMessage(), 'tabelas InnoDB')) throw $errorGate;
        $rejectedGate = true;
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    if (!$rejectedGate || (int) $pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== $originalTimeoutGate) {
        throw new RuntimeException('MyISAM não recusado ou timeout não restaurado.');
    }

    $pdo->exec('ALTER TABLE `' . $tableGate . '` ENGINE=InnoDB');
    $blockerGate->exec('LOCK TABLES `' . $tableGate . '` WRITE');
    $pdo->beginTransaction();
    $timedOutGate = false;
    $startedGate = hrtime(true);
    try {
        ParteDocumentoLock::requireTransactionalTables($pdo, [$tableGate]);
    } catch (PDOException $errorGate) {
        if ((int) ($errorGate->errorInfo[1] ?? 0) !== 1205) throw $errorGate;
        $timedOutGate = true;
    } finally {
        $elapsedGate = (hrtime(true) - $startedGate) / 1e9;
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    $blockerGate->exec('UNLOCK TABLES');
    if (!$timedOutGate || (int) $pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== $originalTimeoutGate
        || ParteDocumentoLock::isConnectionUnsafe($pdo) || $elapsedGate < 1.0 || $elapsedGate > 8.0) {
        throw new RuntimeException('Timeout de metadata ou restauração não comprovados.');
    }
} catch (Throwable $errorGate) {
    $failureGate = $errorGate;
} finally {
    try {
        if ($blockerGate instanceof PDO) $blockerGate->exec('UNLOCK TABLES');
    } catch (Throwable $errorGate) { $cleanupErrorsGate[] = 'UNLOCK: ' . $errorGate->getMessage(); }
    // Fechar a conexão libera seus locks mesmo se UNLOCK falhar.
    $blockerGate = null;
    try {
        if ($pdo->inTransaction()) $pdo->rollBack();
    } catch (Throwable $errorGate) { $cleanupErrorsGate[] = 'ROLLBACK: ' . $errorGate->getMessage(); }
    try {
        if ($createdGate) {
            rei152AssertCiConnection($pdo);
            $pdo->exec('DROP TABLE `' . $tableGate . '`');
        }
    } catch (Throwable $errorGate) { $cleanupErrorsGate[] = 'DROP: ' . $errorGate->getMessage(); }
    try {
        if ($savedTimeoutGate !== null) $pdo->exec('SET SESSION lock_wait_timeout = ' . $savedTimeoutGate);
    } catch (Throwable $errorGate) { $cleanupErrorsGate[] = 'timeout: ' . $errorGate->getMessage(); }
    if ($cleanupErrorsGate !== []) {
        error_log('Falhas na limpeza da fixture: ' . implode('; ', $cleanupErrorsGate));
    }
}
if ($failureGate !== null) throw $failureGate;
if ($cleanupErrorsGate !== []) throw new RuntimeException('Falha na limpeza da fixture: ' . implode('; ', $cleanupErrorsGate));
echo "PASS REI-152 gate: tabela adicional InnoDB/MyISAM e restauração após timeout\n";
