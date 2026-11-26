<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/PessoasService.php';

$runId = (string) getenv('REI152_RUN_ID');
$marker = (string) getenv('REI152_MARKER');
$dsn = (string) getenv('REI152_MYSQL_DSN');
if (getenv('REI152_TEST_ISOLATION') !== 'disposable-rei152-mysql'
    || !preg_match('/^[a-f0-9]{32}$/D', $runId)
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)
    || $dsn !== 'mysql:host=mysql;port=3306;dbname=rei152_test;charset=utf8mb4'
    || getenv('REI152_MYSQL_USER') !== 'root') {
    throw new RuntimeException('Runner MySQL descartável REI-152 não comprovado.');
}
$pdo = new PDO($dsn, 'root', (string) getenv('REI152_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei152_test'
    || !str_starts_with((string) $pdo->query('SELECT VERSION()')->fetchColumn(), '8.4.')) {
    throw new RuntimeException('Fixture REI-152 exige MySQL 8.4 descartável.');
}
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei152_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([$runId, $marker]);
if ((int) $guard->fetchColumn() !== 1) {
    throw new RuntimeException('Sentinela aleatória do runner REI-152 não foi confirmada.');
}

$pdo->exec('CREATE TABLE partes (id VARCHAR(30) PRIMARY KEY, cpf VARCHAR(20) NULL, nome_completo VARCHAR(255) NOT NULL, sexo VARCHAR(20) NULL, data_nascimento DATE NULL, profissao VARCHAR(255) NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE partes_documentos (id VARCHAR(30) PRIMARY KEY, parte_id VARCHAR(30) NOT NULL, tipo VARCHAR(20) NOT NULL, numero VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, KEY idx_rei152_parte_tipo (parte_id, tipo), KEY idx_rei152_tipo_numero (tipo, numero)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('P1', NULL, 'Pessoa sintética')");
$service = new PessoasService($pdo);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

// A failure in the legacy CPF sync must roll back the document insert.
$pdo->exec("CREATE TRIGGER rei152_fail_cpf BEFORE UPDATE ON partes FOR EACH ROW BEGIN IF NOT (NEW.cpf <=> OLD.cpf) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='rei152 synthetic CPF failure'; END IF; END");
try {
    try {
        $service->addDocumento('P1', ['tipo' => 'CPF', 'numero' => '52998224725']);
        throw new RuntimeException('Falha de sincronização injetada não ocorreu.');
    } catch (PDOException $exception) {
        $assert(str_contains($exception->getMessage(), 'rei152 synthetic CPF failure'), 'Falha inesperada no teste de rollback da inclusão.');
    }
    $assert((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='P1'")->fetchColumn() === 0, 'Documento permaneceu após rollback da inclusão.');
    $assert($pdo->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() === null, 'CPF legado mudou após rollback da inclusão.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei152_fail_cpf');
}

$documentId = $service->addDocumento('P1', ['tipo' => 'CPF', 'numero' => '52998224725']);
$assert($pdo->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() === '52998224725', 'Inclusão não sincronizou CPF legado.');

// A deletion and its CPF sync are also one transaction.
$pdo->exec("CREATE TRIGGER rei152_fail_delete_cpf BEFORE UPDATE ON partes FOR EACH ROW BEGIN IF OLD.cpf IS NOT NULL AND NEW.cpf IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='rei152 synthetic delete failure'; END IF; END");
try {
    try {
        $service->deleteDocumento($documentId);
        throw new RuntimeException('Falha de sincronização da exclusão não ocorreu.');
    } catch (PDOException $exception) {
        $assert(str_contains($exception->getMessage(), 'rei152 synthetic delete failure'), 'Falha inesperada no teste de rollback da exclusão.');
    }
    $assert((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id=" . $pdo->quote($documentId))->fetchColumn() === 1, 'Documento foi removido apesar do rollback da exclusão.');
    $assert($pdo->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() === '52998224725', 'CPF legado foi limpo apesar do rollback da exclusão.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei152_fail_delete_cpf');
}
$assert($service->deleteDocumento($documentId), 'Exclusão válida falhou.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() === 0, 'Documento permaneceu após exclusão válida.');
$assert($pdo->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() === null, 'CPF legado não foi limpo após exclusão válida.');

// Two simultaneous service requests for the same document serialize by the
// document key, including the check and commit, even when they target different
// parent rows. The manual lock lets the test prove the second request is queued.
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('P2', NULL, 'Pessoa sintética concorrente')");
$runConcurrentDuplicate = static function (string $workerPartyId, string $case) use ($pdo, $service, $assert): void {
    $lockName = 'pd_' . substr(hash('sha256', 'CPF' . "\0" . '52998224725'), 0, 60);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    $assert((int) $lock->fetchColumn() === 1, 'Não foi possível reservar lock do fixture ' . $case . '.');
    $manualLockHeld = true;
    $pipes = [];
    putenv('REI152_PARTY_ID=' . $workerPartyId);
    $worker = proc_open([PHP_BINARY, '/workspace/tests/pessoas/rei152-concurrency-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    putenv('REI152_PARTY_ID');
    if (!is_resource($worker)) {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
        throw new RuntimeException('Não foi possível iniciar worker ' . $case . '.');
    }
    try {
        $readyLine = fgets($pipes[1]);
        if (!is_string($readyLine) || trim($readyLine) !== 'READY') {
            throw new RuntimeException('Worker ' . $case . ' não sinalizou início.');
        }
        fwrite($pipes[0], "GO\n");
        fflush($pipes[0]);
        usleep(500000);
        $workerStatus = proc_get_status($worker);
        $assert($workerStatus['running'], 'Requisição ' . $case . ' não aguardou o lock do número do documento.');

        $service->addDocumento('P1', ['tipo' => 'CPF', 'numero' => '52998224725']);
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
        $manualLockHeld = false;
        $assert((int) $release->fetchColumn() === 1, 'Lock do fixture não foi liberado após o commit do serviço.');

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], true);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($worker);
        $assert($exit === 0 && str_contains((string) $output, 'PASS|duplicate rejected'), 'Worker ' . $case . ' não rejeitou documento repetido: ' . trim((string) $error . ' ' . (string) $output));
        $assert((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='52998224725'")->fetchColumn() === 1, 'Concorrência ' . $case . ' duplicou o número do documento.');
    } catch (Throwable $exception) {
        if ($manualLockHeld) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
        foreach ([0, 1, 2] as $index) {
            if (isset($pipes[$index]) && is_resource($pipes[$index])) {
                fclose($pipes[$index]);
            }
        }
        if (is_resource($worker)) {
            proc_terminate($worker);
            proc_close($worker);
        }
        throw $exception;
    }
};

$runConcurrentDuplicate('P1', 'same-party');
echo "PASS REI-152 same-person concurrency\n";
$assert($service->deleteDocumento((string) $pdo->query("SELECT id FROM partes_documentos WHERE parte_id='P1' AND tipo='CPF'")->fetchColumn()), 'Não foi possível preparar o caso cross-party.');
$runConcurrentDuplicate('P2', 'cross-party');
echo "PASS REI-152 cross-person document uniqueness\n";
