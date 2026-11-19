<?php
declare(strict_types=1);

if (getenv('REI104_TEST_ISOLATION') !== 'disposable-rei104-mysql'
    || getenv('REI104_TEST_RUN_ID') === false
    || getenv('REI104_TEST_MARKER') === false) {
    throw new RuntimeException('Teste recusado fora do runner MySQL descartável REI-104.');
}

$dsn = (string) getenv('REI104_TEST_DSN');
if (!str_starts_with($dsn, 'mysql:')) {
    throw new RuntimeException('DSN REI-104 deve apontar para MySQL descartável.');
}

require_once __DIR__ . '/../../app/services/CartasRecebidasService.php';
$_SESSION = [];
$db = new PDO($dsn, (string) getenv('REI104_TEST_USER'), (string) getenv('REI104_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE cartas_recebidas (
    id VARCHAR(64) PRIMARY KEY,
    numero_processo_origem VARCHAR(64) NOT NULL,
    finalidade VARCHAR(255) NOT NULL,
    cidade_origem VARCHAR(120) NOT NULL,
    uf_origem CHAR(2) NOT NULL,
    status VARCHAR(64) NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB');
$db->exec('CREATE TABLE cartas_recebidas_subtarefas (
    id VARCHAR(64) PRIMARY KEY,
    carta_recebida_id VARCHAR(64) NOT NULL,
    tipo VARCHAR(32) NOT NULL,
    ordem INT NOT NULL,
    status VARCHAR(32) NOT NULL,
    dados_especificos TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY carta_ordem (carta_recebida_id, ordem)
) ENGINE=InnoDB');

$seed = static function (PDO $db, string $cartaId): void {
    $stmt = $db->prepare('INSERT INTO cartas_recebidas (id, numero_processo_origem, finalidade, cidade_origem, uf_origem, status) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$cartaId, '00000000000000000000', 'cumprimento', 'Ilhéus', 'BA', 'aguardando cumprimento']);
    $task = $db->prepare('INSERT INTO cartas_recebidas_subtarefas (id, carta_recebida_id, tipo, ordem, status) VALUES (?, ?, ?, ?, ?)');
    $task->execute(['CUM-' . $cartaId, $cartaId, 'cumprimento', 1, 'em_andamento']);
    $task->execute(['DEV-' . $cartaId, $cartaId, 'devolucao', 2, 'pendente']);
};
$snapshot = static function (PDO $db, string $cartaId): string {
    $carta = $db->prepare('SELECT id, status FROM cartas_recebidas WHERE id = ?');
    $carta->execute([$cartaId]);
    $tasks = $db->prepare('SELECT id, ordem, status, dados_especificos FROM cartas_recebidas_subtarefas WHERE carta_recebida_id = ? ORDER BY ordem');
    $tasks->execute([$cartaId]);
    return json_encode([$carta->fetchAll(), $tasks->fetchAll()], JSON_THROW_ON_ERROR);
};

$seed($db, 'EARLY');
$before = $snapshot($db, 'EARLY');
try {
    (new CartasRecebidasService($db))->concluirSubtarefa('DEV-EARLY', '{}');
    throw new RuntimeException('A devolução antecipada deveria ter sido recusada.');
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction() || $snapshot($db, 'EARLY') !== $before) {
        throw new RuntimeException('A recusa da devolução antecipada deixou escrita parcial.', 0, $e);
    }
}

$service = new CartasRecebidasService($db);
$first = $service->concluirSubtarefa('CUM-EARLY', '{"etapa":"cumprimento"}');
if (($first['tarefa_principal_concluida'] ?? true) !== false
    || $db->query("SELECT status FROM cartas_recebidas_subtarefas WHERE id='CUM-EARLY'")->fetchColumn() !== 'concluida'
    || $db->query("SELECT status FROM cartas_recebidas_subtarefas WHERE id='DEV-EARLY'")->fetchColumn() !== 'em_andamento'
    || $db->query("SELECT status FROM cartas_recebidas WHERE id='EARLY'")->fetchColumn() !== CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO) {
    throw new RuntimeException('Cumprimento não avançou atomicamente para devolução.');
}
$afterFirst = $snapshot($db, 'EARLY');
$service->concluirSubtarefa('CUM-EARLY', '{"etapa":"retentativa"}');
if ($snapshot($db, 'EARLY') !== $afterFirst) {
    throw new RuntimeException('Retentativa do cumprimento não foi idempotente.');
}

$final = $service->concluirSubtarefa('DEV-EARLY', '{"etapa":"devolucao"}');
if (($final['tarefa_principal_concluida'] ?? false) !== true
    || $db->query("SELECT status FROM cartas_recebidas_subtarefas WHERE id='DEV-EARLY'")->fetchColumn() !== 'concluida'
    || $db->query("SELECT status FROM cartas_recebidas WHERE id='EARLY'")->fetchColumn() !== 'concluída') {
    throw new RuntimeException('Devolução válida não concluiu carta e etapa.');
}
$afterFinal = $snapshot($db, 'EARLY');
$retry = $service->concluirSubtarefa('DEV-EARLY', '{"etapa":"duplicada"}');
if (($retry['tarefa_principal_concluida'] ?? false) !== true || $snapshot($db, 'EARLY') !== $afterFinal) {
    throw new RuntimeException('Retentativa da devolução não foi idempotente.');
}

// Competing order-2 completion must wait for the in-flight order-1 commit and
// re-read state after acquiring the parent-row lock.
$seed($db, 'CONCURRENT');
$db->beginTransaction();
$parentLock = $db->prepare('SELECT id FROM cartas_recebidas WHERE id = ? FOR UPDATE');
$parentLock->execute(['CONCURRENT']);
$workerEnv = [
    'REI104_TEST_ISOLATION' => 'disposable-rei104-mysql',
    'REI104_TEST_RUN_ID' => (string) getenv('REI104_TEST_RUN_ID'),
    'REI104_TEST_MARKER' => (string) getenv('REI104_TEST_MARKER'),
    'REI104_TEST_DSN' => $dsn,
    'REI104_TEST_USER' => (string) getenv('REI104_TEST_USER'),
    'REI104_TEST_PASSWORD' => (string) getenv('REI104_TEST_PASSWORD'),
];
$pipes = [];
$worker = proc_open([PHP_BINARY, __DIR__ . '/rei104-concurrency-worker.php'], [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
], $pipes, __DIR__, $workerEnv);
if (!is_resource($worker)) {
    $db->rollBack();
    throw new RuntimeException('Não foi possível iniciar worker concorrente REI-104.');
}
try {
    stream_set_timeout($pipes[1], 10);
    if (fgets($pipes[1]) !== "READY\n") {
        throw new RuntimeException('Worker concorrente não confirmou conexão MySQL.');
    }
    fwrite($pipes[0], "GO\n");
    fflush($pipes[0]);
    if (fgets($pipes[1]) !== "LOCKING\n") {
        throw new RuntimeException('Worker concorrente não iniciou a conclusão da devolução.');
    }
    usleep(300000);
    $workerStatus = proc_get_status($worker);
    if (!$workerStatus['running']) {
        $error = stream_get_contents($pipes[2]);
        throw new RuntimeException('A devolução concorrente terminou antes do commit do cumprimento: ' . trim($error));
    }

    (new CartasRecebidasService($db))->concluirSubtarefa('CUM-CONCURRENT', '{"etapa":"cumprimento"}');
    $db->commit();
    stream_set_blocking($pipes[1], true);
    $workerOutput = stream_get_contents($pipes[1]);
    $workerError = stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $workerExit = proc_close($worker);
    if ($workerExit !== 0 || !str_contains((string) $workerOutput, 'PASS|concurrent order-2')) {
        throw new RuntimeException('Worker concorrente falhou: ' . trim((string) $workerError . ' ' . (string) $workerOutput));
    }
    if ($db->query("SELECT status FROM cartas_recebidas WHERE id='CONCURRENT'")->fetchColumn() !== 'concluída'
        || $db->query("SELECT status FROM cartas_recebidas_subtarefas WHERE id='DEV-CONCURRENT'")->fetchColumn() !== 'concluida') {
        throw new RuntimeException('Concorrência válida não concluiu a carta e sua devolução.');
    }
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
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

$seed($db, 'ROLLBACK');
$db->exec("CREATE TRIGGER rei104_fail_status BEFORE UPDATE ON cartas_recebidas FOR EACH ROW BEGIN IF NEW.id='ROLLBACK' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='falha induzida REI-104'; END IF; END");
$beforeFailure = $snapshot($db, 'ROLLBACK');
try {
    (new CartasRecebidasService($db))->concluirSubtarefa('CUM-ROLLBACK', '{}');
    throw new RuntimeException('Falha induzida deveria interromper a transação.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'falha induzida REI-104')
        || $db->inTransaction()
        || $snapshot($db, 'ROLLBACK') !== $beforeFailure) {
        throw new RuntimeException('Falha não reverteu todas as escritas do fluxo.', 0, $e);
    }
}

echo "PASS REI-104 MySQL/InnoDB sequência, retry, rollback e concorrência\n";
