<?php
declare(strict_types=1);

$runId = (string) getenv('REI105_TEST_RUN_ID');
$marker = (string) getenv('REI105_TEST_DISPOSABLE_MARKER');
$dsn = (string) getenv('REI105_MYSQL_DSN');
if (getenv('REI105_TEST_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei105_test;charset=utf8mb4'
    || getenv('REI105_MYSQL_USER') !== 'root') {
    fwrite(STDERR, "FAIL CLOSED: disposable MySQL fixture configuration invalid\n");
    exit(90);
}

require_once '/repo/app/services/GuiasService.php';
$connect = static fn (): PDO => new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 120]);
$db = $connect();
$guard = $db->prepare('SELECT COUNT(*) FROM rei105_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei105_test'
    || !str_starts_with((string) $db->query('SELECT VERSION()')->fetchColumn(), '8.4.')
    || strtoupper((string) $db->query('SELECT @@transaction_isolation')->fetchColumn()) !== 'REPEATABLE-READ'
    || (int) $guard->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL CLOSED: expected guarded MySQL 8.4 REPEATABLE READ database\n");
    exit(91);
}

foreach ([
    'CREATE TABLE guias (id VARCHAR(64) PRIMARY KEY, numero_processo VARCHAR(64), processo_id VARCHAR(64) NULL, parte_id VARCHAR(64) NULL, denunciado VARCHAR(100), tipo VARCHAR(100), status VARCHAR(32), data_fato DATE NULL, dias_multa INT NULL, valor_multa DECIMAL(10,2) NULL, updated_at DATETIME NULL) ENGINE=InnoDB',
    'CREATE TABLE guias_subtarefas (id VARCHAR(64) PRIMARY KEY, guia_id VARCHAR(64) NOT NULL, tipo VARCHAR(64) NOT NULL, ordem INT NOT NULL, status VARCHAR(32) NOT NULL, dados_especificos JSON NULL, created_at DATETIME NULL, updated_at DATETIME NULL, KEY (guia_id, ordem)) ENGINE=InnoDB',
    'CREATE TABLE dashboard_conclusoes (id CHAR(36) PRIMARY KEY, usuario_id VARCHAR(64), modulo VARCHAR(64), area VARCHAR(32), referencia_interna VARCHAR(64), concluido_em DATETIME) ENGINE=InnoDB',
    'CREATE TABLE guias_alocados (id VARCHAR(30) PRIMARY KEY, guia_id VARCHAR(64), usuario_id VARCHAR(64), tipo VARCHAR(32), created_at DATETIME, UNIQUE KEY (guia_id, usuario_id)) ENGINE=InnoDB',
] as $sql) $db->exec($sql);

$names = ['detalhamento_informacoes', 'calculo_pena_multa', 'expedicao_guia', 'aguardando_assinatura', 'juntada_automatica', 'gerar_pdf', 'envio_guia', 'juntada_termo_envio'];
$seed = static function (PDO $pdo, int $current) use ($names): void {
    $pdo->exec('DELETE FROM dashboard_conclusoes');
    $pdo->exec('DELETE FROM guias_alocados');
    $pdo->exec('DELETE FROM guias_subtarefas');
    $pdo->exec('DELETE FROM guias');
    $pdo->exec("INSERT INTO guias (id,numero_processo,denunciado,tipo,status) VALUES ('G1','synthetic','fixture','Guia','em_andamento')");
    $st = $pdo->prepare('INSERT INTO guias_subtarefas (id,guia_id,tipo,ordem,status) VALUES (:id,\'G1\',:tipo,:ordem,:status)');
    foreach ($names as $i => $name) {
        $order = $i + 1;
        $status = $order < $current ? 'concluida' : ($order === $current ? 'em_andamento' : 'pendente');
        if ($current === 1 && $order === 2) $status = 'em_andamento';
        $st->execute([':id' => 'ST' . $order, ':tipo' => $name, ':ordem' => $order, ':status' => $status]);
    }
};
$snapshot = static fn (PDO $pdo): string => json_encode([
    $pdo->query('SELECT * FROM guias ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM guias_subtarefas ORDER BY ordem')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM dashboard_conclusoes ORDER BY referencia_interna')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM guias_alocados ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
], JSON_THROW_ON_ERROR);
$reject = static function (PDO $pdo, string $id, ?string $payload) use ($snapshot): void {
    $before = $snapshot($pdo);
    try { (new GuiasService($pdo))->concluirSubtarefa($id, $payload); }
    catch (InvalidArgumentException) {
        if ($pdo->inTransaction() || $snapshot($pdo) !== $before) throw new RuntimeException('Rejected transition wrote partial state: ' . $id);
        return;
    }
    throw new RuntimeException('Transition should have been rejected: ' . $id);
};

$_SESSION = [];
$seed($db, 1); $reject($db, 'ST1', '{"data_fato":"2999-01-01","dias_multa":3}');
$seed($db, 1); $reject($db, 'ST1', '{"data_fato":"2025-02-30","dias_multa":3}');
$seed($db, 3); $reject($db, 'ST3', '{}');
$seed($db, 3); $reject($db, 'ST3', '{"tipo_guia":"invalid"}');
$seed($db, 5); $reject($db, 'ST5', '{}');
$seed($db, 5); $reject($db, 'ST5', '{"tipo_juntada":"invalid"}');
$seed($db, 6); $reject($db, 'ST6', '{}');
$seed($db, 6); $reject($db, 'ST6', '{"qtd_docs":3}');
$seed($db, 7); $reject($db, 'ST7', '{}');
$seed($db, 7); $reject($db, 'ST7', '{"tipo_envio":"invalid","destinatario":"invalid"}');
$seed($db, 1); $reject($db, 'ST8', '{}');

$seed($db, 6);
(new GuiasService($db))->concluirSubtarefa('ST6', '{"qtd_docs":4}');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST6'")->fetchColumn() !== 'concluida'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST7'")->fetchColumn() !== 'em_andamento') throw new RuntimeException('Valid document count did not advance the guide.');
$seed($db, 7);
(new GuiasService($db))->concluirSubtarefa('ST7', '{"tipo_envio":"PJe","destinatario":"VEP"}');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST7'")->fetchColumn() !== 'concluida'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST8'")->fetchColumn() !== 'em_andamento') throw new RuntimeException('Valid dispatch details did not advance the guide.');

$seed($db, 3);
$beforeAllocationFailure = $snapshot($db);
$db->exec("CREATE TRIGGER fail_allocation BEFORE INSERT ON guias_alocados FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='allocation failure fixture'");
try {
    (new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva","usuario_id":"USR-TESTE"}');
    throw new RuntimeException('Expected the synthetic allocation insert failure.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'allocation failure fixture') || $db->inTransaction() || $snapshot($db) !== $beforeAllocationFailure) {
        throw new RuntimeException('Allocation failure did not roll back the guide transition.', 0, $exception);
    }
}
$db->exec('DROP TRIGGER fail_allocation');

$seed($db, 3);
(new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva"}', 'USR-TESTE');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST4'")->fetchColumn() !== 'em_andamento'
    || $db->query("SELECT tipo FROM guias WHERE id='G1'")->fetchColumn() !== 'Guia de Execução Definitiva') throw new RuntimeException('Valid transition did not advance stage/type.');
$beforeRetry = $snapshot($db);
(new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Recolhimento Definitiva"}', 'USR-TESTE');
if ($snapshot($db) !== $beforeRetry) throw new RuntimeException('Retry changed an already completed stage.');

$seed($db, 1);
(new GuiasService($db))->concluirSubtarefa('ST1', '{}');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST2'")->fetchColumn() !== 'pulada'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST3'")->fetchColumn() !== 'em_andamento') throw new RuntimeException('Optional detail stage did not advance safely.');

$seed($db, 8);
(new GuiasService($db))->concluirSubtarefa('ST8', '{}', 'USR-TESTE');
$beforeRetry = $snapshot($db);
$retry = (new GuiasService($db))->concluirSubtarefa('ST8', '{}', 'USR-TESTE');
if (($retry['tarefa_principal_concluida'] ?? false) !== true || $snapshot($db) !== $beforeRetry) throw new RuntimeException('Final-stage retry was not idempotent.');

$seed($db, 1);
$barrier = '/tmp/rei105-' . $runId . '-' . bin2hex(random_bytes(8));
if (!mkdir($barrier, 0700)) throw new RuntimeException('Could not create race barrier.');
$db->beginTransaction();
$db->query("SELECT id FROM guias WHERE id='G1' FOR UPDATE")->fetchColumn();
$workers = [];
$workerResults = [];
try {
    for ($i = 0; $i < 2; $i++) {
        $operation = $i === 0 ? 'cancel' : 'complete';
        $subtask = 'ST1';
        $command = [PHP_BINARY, '/test/rei105-conclusao-mysql-worker.php', $operation, $subtask, $barrier, (string) $i, $runId, $marker];
        $pipes = [];
        $proc = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) throw new RuntimeException('Could not start an independent completion worker.');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $workers[] = ['proc' => $proc, 'pipes' => $pipes];
    }
    $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline && (!is_file($barrier . '/ready-0') || !is_file($barrier . '/ready-1'))) usleep(10000);
    if (!is_file($barrier . '/ready-0') || !is_file($barrier . '/ready-1')) throw new RuntimeException('Workers did not reach race barrier.');
    file_put_contents($barrier . '/go', $runId . ':' . $marker, LOCK_EX);
    $threadIds = [];
    while (microtime(true) < $deadline && count($threadIds) < 2) {
        foreach ([0, 1] as $i) {
            $threadFile = $barrier . '/thread-' . $i;
            if (is_file($threadFile)) $threadIds[$i] = (int) file_get_contents($threadFile);
        }
        usleep(10000);
    }
    if (count($threadIds) !== 2 || in_array(0, $threadIds, true)) throw new RuntimeException('Workers did not announce two MySQL sessions.');
    $locked = false;
    while (microtime(true) < $deadline) {
        $ids = implode(',', array_map('intval', array_values($threadIds)));
        $waiting = (int) $db->query("SELECT COUNT(*) FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN ($ids) AND trx_state='LOCK WAIT'")->fetchColumn();
        if ($waiting === 2) { $locked = true; break; }
        usleep(20000);
    }
    if (!$locked) throw new RuntimeException('Both completion workers were not observed waiting on the InnoDB parent lock.');
    $db->commit();
    foreach ($workers as $index => &$worker) {
        $output = '';
        $error = '';
        $end = microtime(true) + 30;
        do {
            $output .= stream_get_contents($worker['pipes'][1]);
            $error .= stream_get_contents($worker['pipes'][2]);
            $status = proc_get_status($worker['proc']);
            if (!$status['running']) break;
            usleep(20000);
        } while (microtime(true) < $end);
        if (($status['running'] ?? true) === true) { proc_terminate($worker['proc']); throw new RuntimeException('Completion worker timed out: ' . $error); }
        fclose($worker['pipes'][1]); fclose($worker['pipes'][2]);
        $exit = (int) ($status['exitcode'] ?? -1);
        proc_close($worker['proc']);
        $worker['proc'] = null;
        $record = json_decode(trim($output), true);
        if ($exit !== 0 || !in_array($record['result'] ?? '', ['completed', 'cancelled', 'refused'], true)) throw new RuntimeException('Transition worker failed: ' . trim($error . ' ' . $output));
        $workerResults[] = $record['result'];
    }
    unset($worker);
    $stepOne = $db->query("SELECT status FROM guias_subtarefas WHERE id='ST1'")->fetchColumn();
    $expectedResults = ['refused', $stepOne === 'cancelada' ? 'cancelled' : 'completed'];
    sort($expectedResults);
    sort($workerResults);
    if ($workerResults !== $expectedResults
        || !in_array($stepOne, ['cancelada', 'concluida'], true)
        || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST2'")->fetchColumn() !== 'pulada'
        || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST3'")->fetchColumn() !== 'em_andamento'
        || $db->query("SELECT status FROM guias WHERE id='G1'")->fetchColumn() !== 'em_andamento') {
        throw new RuntimeException('Concurrent cancellation/completion race produced a partial or invalid sequence state.');
    }
} finally {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) {
        if (is_resource($worker['proc'] ?? null)) { proc_terminate($worker['proc']); proc_close($worker['proc']); }
    }
    foreach (glob($barrier . '/*') ?: [] as $path) @unlink($path);
    @rmdir($barrier);
}

echo "PASS MySQL/InnoDB REI-105 validation and retry cases\n";
