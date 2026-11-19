<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';
require_once __DIR__ . '/../../app/helpers/CnjValidator.php';

function rei100MysqlAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei100MysqlConnection(): PDO
{
    $runId = (string) getenv('REI100_RUN_ID');
    $marker = (string) getenv('REI100_MARKER');
    $dsn = (string) getenv('REI100_MYSQL_DSN');
    if (getenv('REI100_TEST_ISOLATION') !== 'disposable-rei100-mysql'
        || !preg_match('/^[a-f0-9]{32}$/D', $runId)
        || !preg_match('/^[a-f0-9]{64}$/D', $marker)
        || $dsn !== 'mysql:host=mysql;port=3306;dbname=rei100_test;charset=utf8mb4') {
        throw new RuntimeException('FAIL CLOSED: REI-100 MySQL fixture not proved');
    }
    $pdo = new PDO($dsn, 'root', (string) getenv('REI100_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $guard = $pdo->prepare('SELECT COUNT(*) FROM rei100_disposable_guard WHERE run_id = ? AND marker = ?');
    $guard->execute([$runId, $marker]);
    rei100MysqlAssert((int) $guard->fetchColumn() === 1, 'REI-100 database guard failed');
    rei100MysqlAssert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === 'rei100_test', 'Unexpected REI-100 database');
    return $pdo;
}

function rei100CreatePayload(string $key, string $process, string $notes): array
{
    return [
        'processo' => $process,
        'tipo_edital' => 'intimacao',
        'prazo_dias' => 5,
        'observacoes' => $notes,
        'idempotency_key' => $key,
    ];
}

function rei100ValidCnj(string $sequence): string
{
    $base = $sequence . '20248100001';
    for ($checkDigits = 0; $checkDigits < 100; $checkDigits++) {
        $digits = substr($base, 0, 7) . str_pad((string) $checkDigits, 2, '0', STR_PAD_LEFT) . substr($base, 7);
        if (CnjValidator::isValid($digits)) {
            return sprintf('%s-%s.%s.%s.%s.%s', substr($digits, 0, 7), substr($digits, 7, 2), substr($digits, 9, 4), substr($digits, 13, 1), substr($digits, 14, 2), substr($digits, 16, 4));
        }
    }
    throw new RuntimeException('Could not create a valid synthetic CNJ');
}

if (($argv[1] ?? '') === '--worker') {
    $pdo = rei100MysqlConnection();
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    if (fgets(STDIN) !== "GO\n") {
        throw new RuntimeException('REI-100 worker did not receive the start signal');
    }
    $payload = rei100CreatePayload('123e4567-e89b-42d3-a456-426614174001', 'SINTETICO-CONCORRENTE', 'Worker concorrente');
    $id = (new EditaisService($pdo))->create($payload);
    fwrite(STDOUT, 'PASS|' . $id . "\n");
    exit(0);
}

$pdo = rei100MysqlConnection();
$schema = [
    'CREATE TABLE classes (id VARCHAR(40) PRIMARY KEY, codigo VARCHAR(40), nome VARCHAR(255), created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB',
    'CREATE TABLE processos (id VARCHAR(40) PRIMARY KEY, numero_processo VARCHAR(40), classe_id VARCHAR(40) NULL, data_fato DATE NULL, sigiloso TINYINT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, INDEX idx_rei100_processo_numero (numero_processo)) ENGINE=InnoDB',
    'CREATE TABLE feriados (id VARCHAR(40) PRIMARY KEY, tipo_feriado VARCHAR(40), data_inicio DATE, data_fim DATE, regra_data VARCHAR(20), descricao VARCHAR(255) NULL, ativo TINYINT NOT NULL DEFAULT 1, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB',
    'CREATE TABLE editais (id VARCHAR(40) PRIMARY KEY, processo_id VARCHAR(40) NULL, status VARCHAR(50), tipo_edital VARCHAR(100), prazo_dias INT, data_publicacao DATE NULL, data_inicio_contagem DATE NULL, data_decurso_prazo DATE NULL, observacoes TEXT NULL, criado_por VARCHAR(40) NULL, atualizado_por VARCHAR(40) NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB',
    'CREATE TABLE editais_subtarefas (id VARCHAR(40) PRIMARY KEY, edital_id VARCHAR(40) NOT NULL, tipo VARCHAR(60), ordem INT, status VARCHAR(40), dados_especificos TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, INDEX idx_rei100_sub_edital_ordem (edital_id, ordem)) ENGINE=InnoDB',
];
foreach ($schema as $sql) {
    $pdo->exec($sql);
}

$rollbackKey = '123e4567-e89b-42d3-a456-426614174002';
$rollbackId = 'EDI-' . strtoupper(substr(hash('sha256', $rollbackKey), 0, 26));
$pdo->exec("CREATE TRIGGER rei100_fail_second_stage BEFORE INSERT ON editais_subtarefas FOR EACH ROW BEGIN IF NEW.edital_id = '{$rollbackId}' AND NEW.ordem = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei100 synthetic second stage failure'; END IF; END");
try {
    (new EditaisService($pdo))->create(rei100CreatePayload($rollbackKey, 'SINTETICO-ROLLBACK', 'Falha injetada'));
    throw new RuntimeException('REI-100 synthetic failure did not occur');
} catch (PDOException $exception) {
    rei100MysqlAssert(str_contains($exception->getMessage(), 'rei100 synthetic second stage failure'), 'Unexpected REI-100 injected failure: ' . $exception->getMessage());
}
rei100MysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM editais WHERE id = '{$rollbackId}'")->fetchColumn() === 0, 'MySQL rollback retained the main edital');
rei100MysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id = '{$rollbackId}'")->fetchColumn() === 0, 'MySQL rollback retained a workflow stage');
$pdo->exec('DROP TRIGGER rei100_fail_second_stage');

// An idempotent replay with a different class must conflict before ensureProcess
// can mutate the linked process row.
$classKey = '123e4567-e89b-42d3-a456-426614174003';
$classPayload = rei100CreatePayload($classKey, rei100ValidCnj('1234567'), 'Classe protegida');
$classPayload['classe_codigo'] = 'REI100-CLASS-A';
$classPayload['classe_descricao'] = 'Classe A';
$classEditalId = (new EditaisService($pdo))->create($classPayload);
$classStateBefore = $pdo->query("SELECT p.classe_id FROM processos p JOIN editais e ON e.processo_id=p.id WHERE e.id=" . $pdo->quote($classEditalId))->fetchColumn();
$changedClassPayload = $classPayload;
$changedClassPayload['classe_codigo'] = 'REI100-CLASS-B';
$changedClassPayload['classe_descricao'] = 'Classe B';
try {
    (new EditaisService($pdo))->create($changedClassPayload);
    throw new RuntimeException('Changed class payload reused an existing idempotency key');
} catch (InvalidArgumentException $exception) {
    rei100MysqlAssert(str_contains($exception->getMessage(), 'Esta tentativa de criação já foi usada com outros dados.'), 'Unexpected class idempotency conflict');
}
$classStateAfter = $pdo->query("SELECT p.classe_id FROM processos p JOIN editais e ON e.processo_id=p.id WHERE e.id=" . $pdo->quote($classEditalId))->fetchColumn();
rei100MysqlAssert((string) $classStateAfter === (string) $classStateBefore, 'Changed idempotent replay mutated the existing process class');
rei100MysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM classes WHERE codigo='REI100-CLASS-B'")->fetchColumn() === 0, 'Changed idempotent replay created the conflicting class');

$concurrentKey = '123e4567-e89b-42d3-a456-426614174001';
$concurrentId = 'EDI-' . strtoupper(substr(hash('sha256', $concurrentKey), 0, 26));
$pdo->beginTransaction();
$firstId = (new EditaisService($pdo))->create(rei100CreatePayload($concurrentKey, 'SINTETICO-CONCORRENTE', 'Worker concorrente'));
rei100MysqlAssert($firstId === $concurrentId, 'Stable ID differs for concurrent idempotency key');

$pipes = [];
$worker = proc_open([PHP_BINARY, __FILE__, '--worker'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($worker)) {
    $pdo->rollBack();
    throw new RuntimeException('Could not start REI-100 concurrent worker');
}
try {
    stream_set_blocking($pipes[1], true);
    $readyLine = fgets($pipes[1]);
    rei100MysqlAssert($readyLine === "READY\n", 'REI-100 concurrent worker did not signal readiness');
    fwrite($pipes[0], "GO\n");
    fflush($pipes[0]);
    usleep(500000);
    $workerStatus = proc_get_status($worker);
    rei100MysqlAssert($workerStatus['running'], 'Concurrent same-key create did not wait for the first transaction');
    fclose($pipes[0]);
    $pdo->commit();
    stream_set_blocking($pipes[1], true);
    $workerOutput = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $workerError = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $workerExit = proc_close($worker);
    rei100MysqlAssert($workerExit === 0 && str_contains((string) $workerOutput, 'PASS|' . $concurrentId), 'Concurrent retry failed: ' . trim((string) $workerError . ' ' . (string) $workerOutput));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
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

$retryId = (new EditaisService($pdo))->create(rei100CreatePayload($concurrentKey, 'SINTETICO-CONCORRENTE', 'Worker concorrente'));
rei100MysqlAssert($retryId === $concurrentId, 'Same-key retry returned a different edital ID');
rei100MysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM editais WHERE id = '{$concurrentId}'")->fetchColumn() === 1, 'Concurrent retry duplicated the main edital');
rei100MysqlAssert((int) $pdo->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id = '{$concurrentId}'")->fetchColumn() === 2, 'Concurrent retry duplicated or omitted workflow stages');
rei100MysqlAssert((string) $pdo->query("SELECT status FROM editais WHERE id = '{$concurrentId}'")->fetchColumn() === 'aguardando publicação', 'Concurrent create left inconsistent derived status');

echo "PASS REI-100 MySQL atomicity, retry and same-key concurrency\n";
