<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/OficiosCartorioService.php';

$runId = (string) getenv('REI90_RUN_ID');
$marker = (string) getenv('REI90_MARKER');
$dsn = (string) getenv('REI90_MYSQL_DSN');
if (getenv('REI90_TEST_ISOLATION') !== 'disposable-rei90-mysql'
    || !preg_match('/^[a-f0-9]{32}$/D', $runId)
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)
    || $dsn !== 'mysql:host=mysql;port=3306;dbname=rei90_test;charset=utf8mb4'
    || getenv('REI90_MYSQL_USER') !== 'root') {
    throw new RuntimeException('Runner MySQL descartável REI-90 não comprovado.');
}
$pdo = new PDO($dsn, 'root', (string) getenv('REI90_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei90_test'
    || !str_starts_with((string) $pdo->query('SELECT VERSION()')->fetchColumn(), '8.4.')) {
    throw new RuntimeException('Fixture REI-90 exige MySQL 8.4 descartável.');
}
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei90_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([$runId, $marker]);
if ((int) $guard->fetchColumn() !== 1) {
    throw new RuntimeException('Sentinela aleatória do runner REI-90 não foi confirmada.');
}

foreach ([
    'CREATE TABLE processos (id VARCHAR(30) PRIMARY KEY, classe_id VARCHAR(30) NULL) ENGINE=InnoDB',
    'CREATE TABLE classes (id VARCHAR(30) PRIMARY KEY, nome VARCHAR(200) NULL) ENGINE=InnoDB',
    'CREATE TABLE oficios_cartorio (
        id VARCHAR(30) PRIMARY KEY, processo_id VARCHAR(30) NULL, numero_processo VARCHAR(64) NOT NULL,
        ano SMALLINT NOT NULL, numero_sequencial INT NOT NULL, tipo_oficio VARCHAR(255) NOT NULL,
        forma_cumprimento VARCHAR(20) NOT NULL, meio_envio VARCHAR(30) NULL, status VARCHAR(30) NOT NULL,
        criado_por VARCHAR(30) NULL, atualizado_por VARCHAR(30) NULL, created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB',
    'CREATE TABLE oficios_cartorio_subtarefas (
        id VARCHAR(30) PRIMARY KEY, oficio_cartorio_id VARCHAR(30) NOT NULL, tipo VARCHAR(50) NOT NULL,
        ordem TINYINT NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL, UNIQUE KEY uq_rei90_order (oficio_cartorio_id, ordem),
        CONSTRAINT fk_rei90_oficio FOREIGN KEY (oficio_cartorio_id) REFERENCES oficios_cartorio(id)
    ) ENGINE=InnoDB',
    'CREATE TABLE rei90_worker_ready (run_id CHAR(32) PRIMARY KEY, connection_id BIGINT NOT NULL) ENGINE=InnoDB',
 ] as $sql) {
    $pdo->exec($sql);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$seed = static function (PDO $db, string $status1 = 'em_andamento', string $status2 = 'pendente'): void {
    $db->exec("INSERT INTO oficios_cartorio (id, processo_id, numero_processo, ano, numero_sequencial, tipo_oficio, forma_cumprimento, meio_envio, status, created_at, updated_at) VALUES ('O1', NULL, '—', 2026, 1, 'Ofício Sintético', 'cartorio', 'email', 'pendente', NOW(), NOW())");
    $st = $db->prepare('INSERT INTO oficios_cartorio_subtarefas (id, oficio_cartorio_id, tipo, ordem, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $st->execute(['S1', 'O1', 'enviar_oficio', 1, $status1]);
    $st->execute(['S2', 'O1', 'juntar_termo_envio', 2, $status2]);
};
$state = static function (PDO $db): array {
    return [
        (string) $db->query("SELECT status FROM oficios_cartorio WHERE id='O1'")->fetchColumn(),
        (string) $db->query("SELECT status FROM oficios_cartorio_subtarefas WHERE id='S1'")->fetchColumn(),
        (string) $db->query("SELECT status FROM oficios_cartorio_subtarefas WHERE id='S2'")->fetchColumn(),
    ];
};
$rejectWithoutWrites = static function (PDO $db, string $id, string $message) use ($assert, $state): void {
    $before = $state($db);
    try {
        (new OficiosCartorioService($db))->concluirSubtarefa($id, 'USR-REI90');
        throw new RuntimeException('Transição inválida foi aceita: ' . $id);
    } catch (InvalidArgumentException $e) {
        $assert(str_contains($e->getMessage(), $message), 'Mensagem inesperada para ' . $id);
    }
    $assert($state($db) === $before, 'Transição rejeitada deixou estado parcial: ' . $id);
};

$seed($pdo);
$rejectWithoutWrites($pdo, 'S2', 'subtarefa anterior');
$rejectWithoutWrites($pdo, 'inexistente', 'Subtarefa não encontrada');
$pdo->exec("UPDATE oficios_cartorio_subtarefas SET ordem=3 WHERE id='S2'");
$rejectWithoutWrites($pdo, 'S2', 'Ordem de subtarefa inválida');
$pdo->exec("UPDATE oficios_cartorio_subtarefas SET ordem=2 WHERE id='S2'");

(new OficiosCartorioService($pdo))->concluirSubtarefa('S1', 'USR-REI90');
$assert($state($pdo) === ['pendente', 'concluida', 'em_andamento'], 'Conclusão da ordem 1 deve avançar apenas a subtarefa seguinte.');
$rejectWithoutWrites($pdo, 'S1', 'já foi concluída');

// Inject a deterministic failure in the parent update to prove rollback includes
// the preceding child update in this same transaction.
$pdo->exec("CREATE TRIGGER rei90_reject_finalize BEFORE UPDATE ON oficios_cartorio FOR EACH ROW BEGIN IF NEW.status = 'finalizada' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei90 synthetic failure'; END IF; END");
try {
    try {
        (new OficiosCartorioService($pdo))->concluirSubtarefa('S2', 'USR-REI90');
        throw new RuntimeException('Falha injetada não foi propagada.');
    } catch (PDOException $e) {
        $assert(str_contains($e->getMessage(), 'rei90 synthetic failure'), 'Falha inesperada na injeção do fixture.');
    }
    $assert($state($pdo) === ['pendente', 'concluida', 'em_andamento'], 'Falha da atualização do ofício deve reverter a subtarefa 2.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei90_reject_finalize');
}

(new OficiosCartorioService($pdo))->concluirSubtarefa('S2', 'USR-REI90');
$assert($state($pdo) === ['finalizada', 'concluida', 'concluida'], 'Sequência válida deve concluir ambas subtarefas e o ofício.');

// Hold the parent row while order 2 starts on a second connection. Once order
// 1 is committed, order 2 must continue from the fresh state and finalize.
$pdo->exec('DELETE FROM oficios_cartorio_subtarefas');
$pdo->exec('DELETE FROM oficios_cartorio');
$pdo->exec('DELETE FROM rei90_worker_ready');
$seed($pdo);
$pdo->beginTransaction();
$pdo->query("SELECT id FROM oficios_cartorio WHERE id='O1' FOR UPDATE")->fetchColumn();
$pipes = [];
$worker = proc_open([PHP_BINARY, '/workspace/tests/oficios/rei90-concurrency-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($worker)) {
    $pdo->rollBack();
    throw new RuntimeException('Não foi possível iniciar o worker concorrente REI-90.');
}
try {
    $readyLine = fgets($pipes[1]);
    if (!is_string($readyLine) || preg_match('/^READY\|(\d+)\s*$/', $readyLine, $matches) !== 1) {
        $workerError = stream_get_contents($pipes[2]);
        throw new RuntimeException('Worker concorrente não sinalizou início: ' . trim((string) $workerError));
    }
    fwrite($pipes[0], "GO\n");
    fflush($pipes[0]);
    usleep(500000);
    $workerStatus = proc_get_status($worker);
    $assert($workerStatus['running'], 'Ordem 2 terminou antes de a transação que conclui a ordem 1 ser liberada.');
    fclose($pipes[0]);
    (new OficiosCartorioService($pdo))->concluirSubtarefa('S1', 'USR-REI90');
    $pdo->commit();
    stream_set_blocking($pipes[1], true);
    $workerOutput = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $workerError = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $workerExit = proc_close($worker);
    $assert($workerExit === 0 && str_contains((string) $workerOutput, 'PASS|concurrent order-2'), 'Worker concorrente falhou: ' . trim((string) $workerError . ' ' . (string) $workerOutput));
    $assert($state($pdo) === ['finalizada', 'concluida', 'concluida'], 'Concorrência válida deve preservar a ordem e finalizar o ofício.');
} catch (Throwable $e) {
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
    throw $e;
}

echo "PASS REI-90 ordering and atomicity\n";
