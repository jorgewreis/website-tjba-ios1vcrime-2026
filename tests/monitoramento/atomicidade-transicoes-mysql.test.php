<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/GabineteMonitoramentoEletronicoService.php';

$dsn = trim((string) getenv('REI120_MYSQL_DSN'));
$user = trim((string) getenv('REI120_MYSQL_USER'));
$password = (string) getenv('REI120_MYSQL_PASSWORD');
if (getenv('REI120_TEST_ISOLATION') !== 'disposable-rei120-mysql'
    || $dsn === '' || $user === '') {
    throw new RuntimeException('Runner MySQL descartável REI-120 não comprovado.');
}
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('Fixture REI-120 exige MySQL/InnoDB.');
}
$runId = (string) getenv('REI120_RUN_ID');
$marker = (string) getenv('REI120_RUN_MARKER');
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $database !== 'rei120_test'
    || !str_contains($dsn, 'unix_socket=/var/run/mysqld/mysqld.sock')
    || !str_contains($dsn, 'dbname=rei120_test')) {
    throw new RuntimeException('Alvo MySQL REI-120 não corresponde ao ambiente descartável esperado.');
}
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei120_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([$runId, $marker]);
if ((int) $guard->fetchColumn() !== 1) {
    throw new RuntimeException('Sentinela aleatória do runner descartável não foi confirmada.');
}

$pdo->exec('CREATE TABLE processos (
    id VARCHAR(30) NOT NULL PRIMARY KEY, numero_processo VARCHAR(64) NULL,
    classe_id VARCHAR(30) NULL, data_fato DATE NULL, sigiloso TINYINT NULL,
    created_at DATETIME NULL, updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE classes (
    id VARCHAR(30) NOT NULL PRIMARY KEY, nome VARCHAR(200) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE gabinete_monitoramento_eletronico (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    processo_id VARCHAR(30) NULL,
    numero_processo VARCHAR(30) NOT NULL,
    ano SMALLINT NOT NULL,
    numero_sequencial INT NOT NULL,
    processo_parte_id VARCHAR(30) NULL,
    reu_nome_exibicao VARCHAR(500) NOT NULL,
    reu_complemento VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL,
    data_inicio DATE NULL,
    data_encerramento DATE NULL,
    criado_por VARCHAR(30) NULL,
    atualizado_por VARCHAR(30) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rei120_ano_sequencial (ano, numero_sequencial),
    KEY ix_rei120_processo (processo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE gabinete_monitoramento_eletronico_subtarefas (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    monitoramento_id VARCHAR(30) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    ordem TINYINT NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rei120_monitoramento_ordem (monitoramento_id, ordem),
    CONSTRAINT fk_rei120_subtarefa_monitoramento FOREIGN KEY (monitoramento_id)
        REFERENCES gabinete_monitoramento_eletronico (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE rei120_probe (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');

function rei120Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei120ValidCnj(): string
{
    $base = '000000120248100001';
    for ($i = 0; $i < 100; $i++) {
        $digits = substr($base, 0, 7) . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . substr($base, 7);
        if (CnjValidator::isValid($digits)) {
            return $digits;
        }
    }
    throw new RuntimeException('Não foi possível gerar CNJ sintético válido.');
}

function rei120Reset(PDO $pdo): void
{
    $pdo->exec('DELETE FROM gabinete_monitoramento_eletronico_subtarefas');
    $pdo->exec('DELETE FROM gabinete_monitoramento_eletronico');
    $pdo->exec('DELETE FROM processos');
    $pdo->exec('DELETE FROM rei120_probe');
    $pdo->exec("INSERT INTO processos (id, numero_processo, classe_id, created_at, updated_at) VALUES ('P1', '" . rei120ValidCnj() . "', NULL, NOW(), NOW())");
    $ano = (int) date('Y');
    $parents = [
        ['M1', 1, 'pendente', null, null],
        ['M2', 2, 'em_andamento', date('Y-m-d', strtotime('-3 days')), null],
        ['M3', 3, 'finalizada', date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('-1 day'))],
    ];
    $insertParent = $pdo->prepare('INSERT INTO gabinete_monitoramento_eletronico (
        id, processo_id, numero_processo, ano, numero_sequencial, reu_nome_exibicao,
        status, data_inicio, data_encerramento, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    foreach ($parents as [$id, $seq, $status, $start, $end]) {
        $insertParent->execute([$id, 'P1', '1234567-89.0123.4567.8.90', $ano, $seq, 'Parte sintética', $status, $start, $end]);
    }
    $insertSubtask = $pdo->prepare('INSERT INTO gabinete_monitoramento_eletronico_subtarefas (
        id, monitoramento_id, tipo, ordem, status, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $tasks = [
        ['S1', 'M1', 'iniciar_monitoramento', 1, 'em_andamento'],
        ['S2', 'M1', 'encerrar_monitoramento', 2, 'pendente'],
        ['S3', 'M2', 'iniciar_monitoramento', 1, 'concluida'],
        ['S4', 'M2', 'encerrar_monitoramento', 2, 'em_andamento'],
        ['S5', 'M3', 'iniciar_monitoramento', 1, 'concluida'],
        ['S6', 'M3', 'encerrar_monitoramento', 2, 'concluida'],
    ];
    foreach ($tasks as $task) {
        $insertSubtask->execute($task);
    }
}

function rei120Snapshot(PDO $pdo): array
{
    return [
        $pdo->query('SELECT * FROM gabinete_monitoramento_eletronico ORDER BY id')->fetchAll(),
        $pdo->query('SELECT * FROM gabinete_monitoramento_eletronico_subtarefas ORDER BY id')->fetchAll(),
    ];
}

function rei120FailTrigger(PDO $pdo, string $name, string $table, string $body): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Nome de fixture inválido.');
    }
    $pdo->exec("CREATE TRIGGER `$name` BEFORE UPDATE ON `$table` FOR EACH ROW BEGIN IF $body THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei120 synthetic failure'; END IF; END");
}

function rei120DeleteFailTrigger(PDO $pdo, string $name, string $table, string $body): void
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Nome de fixture inválido.');
    }
    $pdo->exec("CREATE TRIGGER `$name` BEFORE DELETE ON `$table` FOR EACH ROW BEGIN IF $body THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei120 synthetic failure'; END IF; END");
}

function rei120AssertFailureRollsBack(PDO $pdo, callable $operation, string $trigger, string $table, string $body): void
{
    rei120Reset($pdo);
    $before = rei120Snapshot($pdo);
    rei120FailTrigger($pdo, $trigger, $table, $body);
    try {
        $operation();
        throw new RuntimeException('A falha injetada não interrompeu a operação.');
    } catch (PDOException $exception) {
        rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'A operação falhou por motivo diferente do gatilho sintético.');
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS `$trigger`");
    }
    rei120Check(!$pdo->inTransaction(), 'Operação com falha deixou transação própria aberta.');
    rei120Check(rei120Snapshot($pdo) === $before, 'Falha intermediária deixou estado parcial no InnoDB.');
}

$service = new GabineteMonitoramentoEletronicoService($pdo);
$startDate = date('Y-m-d', strtotime('+10 days'));

// Failure after each write in the start and close transitions rolls back parent and subtasks.
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S1', $startDate), 'rei120_fail_start_task', 'gabinete_monitoramento_eletronico_subtarefas', "NEW.id = 'S1' AND NEW.status = 'concluida'");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S1', $startDate), 'rei120_fail_next_task', 'gabinete_monitoramento_eletronico_subtarefas', "NEW.id = 'S2' AND NEW.status = 'em_andamento'");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S1', $startDate), 'rei120_fail_start_dates', 'gabinete_monitoramento_eletronico', "OLD.id = 'M1' AND NEW.data_inicio IS NOT NULL");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S1', $startDate), 'rei120_fail_start_status', 'gabinete_monitoramento_eletronico', "OLD.id = 'M1' AND NEW.status = 'em_andamento'");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S4', null), 'rei120_fail_end_task', 'gabinete_monitoramento_eletronico_subtarefas', "NEW.id = 'S4' AND NEW.status = 'concluida'");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S4', null), 'rei120_fail_end_dates', 'gabinete_monitoramento_eletronico', "OLD.id = 'M2' AND NEW.data_encerramento IS NOT NULL");
rei120AssertFailureRollsBack($pdo, fn () => (new GabineteMonitoramentoEletronicoService($pdo))->concluirSubtarefa('S4', null), 'rei120_fail_end_status', 'gabinete_monitoramento_eletronico', "OLD.id = 'M2' AND NEW.status = 'finalizada'");

// Cancel/delete failures after child deletion restore both child and parent state.
rei120Reset($pdo);
$before = rei120Snapshot($pdo);
rei120FailTrigger($pdo, 'rei120_fail_cancel_status', 'gabinete_monitoramento_eletronico', "OLD.id = 'M1' AND NEW.status = 'cancelada'");
try {
    (new GabineteMonitoramentoEletronicoService($pdo))->cancel('M1');
    throw new RuntimeException('Cancel deveria falhar no update final.');
} catch (PDOException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'Falha inesperada no cenário de cancelamento.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei120_fail_cancel_status');
}
rei120Check(rei120Snapshot($pdo) === $before && !$pdo->inTransaction(), 'Cancel parcial não foi revertido.');

rei120Reset($pdo);
$before = rei120Snapshot($pdo);
rei120DeleteFailTrigger($pdo, 'rei120_fail_cancel_child', 'gabinete_monitoramento_eletronico_subtarefas', "OLD.id = 'S2'");
try {
    (new GabineteMonitoramentoEletronicoService($pdo))->cancel('M1');
    throw new RuntimeException('Cancel deveria falhar ao remover uma subtarefa.');
} catch (PDOException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'Falha inesperada na remoção das subtarefas do cancelamento.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei120_fail_cancel_child');
}
rei120Check(rei120Snapshot($pdo) === $before && !$pdo->inTransaction(), 'Falha na remoção deixou cancelamento parcial.');

rei120Reset($pdo);
$before = rei120Snapshot($pdo);
$pdo->exec("CREATE TRIGGER rei120_fail_delete_parent BEFORE DELETE ON gabinete_monitoramento_eletronico FOR EACH ROW BEGIN IF OLD.id = 'M3' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei120 synthetic failure'; END IF; END");
try {
    (new GabineteMonitoramentoEletronicoService($pdo))->delete('M3');
    throw new RuntimeException('Delete deveria falhar no parent depois de remover as subtarefas.');
} catch (PDOException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'Falha inesperada no cenário de exclusão.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei120_fail_delete_parent');
}
rei120Check(rei120Snapshot($pdo) === $before && !$pdo->inTransaction(), 'Exclusão parcial não foi revertida.');

rei120Reset($pdo);
$before = rei120Snapshot($pdo);
rei120DeleteFailTrigger($pdo, 'rei120_fail_delete_child', 'gabinete_monitoramento_eletronico_subtarefas', "OLD.id = 'S6'");
try {
    (new GabineteMonitoramentoEletronicoService($pdo))->delete('M3');
    throw new RuntimeException('Delete deveria falhar ao remover uma subtarefa.');
} catch (PDOException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'Falha inesperada na remoção das subtarefas da exclusão.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS rei120_fail_delete_child');
}
rei120Check(rei120Snapshot($pdo) === $before && !$pdo->inTransaction(), 'Falha na subtarefa deixou exclusão parcial.');

// Valid transitions, idempotent cancellation, legacy timing rule, and sequence restrictions.
rei120Reset($pdo);
$service = new GabineteMonitoramentoEletronicoService($pdo);
$result = $service->concluirSubtarefa('S1', $startDate, 'USR-TEST');
rei120Check(($result['tarefa_principal_concluida'] ?? null) === false, 'Início válido deveria concluir só a primeira etapa.');
$row = $pdo->query("SELECT status, data_inicio, data_encerramento, atualizado_por FROM gabinete_monitoramento_eletronico WHERE id='M1'")->fetch();
rei120Check($row['status'] === 'em_andamento' && $row['data_inicio'] === $startDate && $row['data_encerramento'] === null && $row['atualizado_por'] === 'USR-TEST', 'Início não persistiu parent consistente.');
rei120Check($pdo->query("SELECT status FROM gabinete_monitoramento_eletronico_subtarefas WHERE id='S1'")->fetchColumn() === 'concluida'
    && $pdo->query("SELECT status FROM gabinete_monitoramento_eletronico_subtarefas WHERE id='S2'")->fetchColumn() === 'em_andamento', 'Início não persistiu as duas etapas de forma consistente.');

$today = date('Y-m-d');
$result = $service->concluirSubtarefa('S4', null, 'USR-TEST');
rei120Check(($result['tarefa_principal_concluida'] ?? null) === true, 'Encerramento válido não concluiu o monitoramento.');
$end = $pdo->query("SELECT status, data_inicio, data_encerramento FROM gabinete_monitoramento_eletronico WHERE id='M2'")->fetch();
$expectedEnd = max((string) $end['data_inicio'], $today);
rei120Check($end['status'] === 'finalizada' && $end['data_encerramento'] === $expectedEnd, 'Regra REI-119 de encerramento antecipado/futuro regrediu.');

rei120Check($service->cancel('M1') === true, 'Cancelamento permitido falhou.');
$cancelled = $pdo->query("SELECT status, data_inicio, data_encerramento FROM gabinete_monitoramento_eletronico WHERE id='M1'")->fetch();
rei120Check($cancelled['status'] === 'cancelada' && $cancelled['data_inicio'] === $startDate && $cancelled['data_encerramento'] === null
    && (int) $pdo->query("SELECT COUNT(*) FROM gabinete_monitoramento_eletronico_subtarefas WHERE monitoramento_id='M1'")->fetchColumn() === 0, 'Cancelamento não preservou datas ou não removeu todas as subtarefas.');
$cancelledBeforeRetry = rei120Snapshot($pdo);
rei120Check($service->cancel('M1') === true && rei120Snapshot($pdo) === $cancelledBeforeRetry, 'Cancelamento repetido deixou de ser idempotente.');

$beforeFinalCancel = rei120Snapshot($pdo);
try {
    $service->cancel('M2');
    throw new RuntimeException('Não se deve cancelar monitoramento finalizado.');
} catch (InvalidArgumentException $exception) {
    rei120Check(rei120Snapshot($pdo) === $beforeFinalCancel, 'Rejeição de cancelamento finalizado alterou dados.');
}

$beforeNonLastDelete = rei120Snapshot($pdo);
try {
    $service->delete('M1');
    throw new RuntimeException('Não se deve excluir um registro que não é o último sequencial do ano.');
} catch (InvalidArgumentException $exception) {
    rei120Check(rei120Snapshot($pdo) === $beforeNonLastDelete, 'Rejeição de exclusão não-last alterou dados.');
}
$deleted = $service->delete('M3');
rei120Check(($deleted['deleted'] ?? null) === true
    && (int) $pdo->query("SELECT COUNT(*) FROM gabinete_monitoramento_eletronico WHERE id='M3'")->fetchColumn() === 0
    && (int) $pdo->query("SELECT COUNT(*) FROM gabinete_monitoramento_eletronico_subtarefas WHERE monitoramento_id='M3'")->fetchColumn() === 0, 'Exclusão do último registro não removeu parent e filhos.');

// The annual sequence mutex is shared with create(); a concurrent delete waits and rechecks max.
rei120Reset($pdo);
$year = (int) date('Y');
$secondForSequence = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sequenceRepository = new GabineteMonitoramentoEletronicoRepository($pdo);
$sequenceRepository->acquireYearSequenceLock($year);
$beforeSequenceRace = rei120Snapshot($pdo);
try {
    (new GabineteMonitoramentoEletronicoService($secondForSequence))->delete('M3');
    throw new RuntimeException('Exclusão concorrente deveria aguardar o lock anual.');
} catch (RuntimeException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'bloqueio do sequencial anual'), 'A contenção do sequencial falhou por motivo inesperado.');
}
rei120Check(rei120Snapshot($pdo) === $beforeSequenceRace, 'Timeout do lock anual deixou exclusão parcial.');
rei120Check($sequenceRepository->releaseYearSequenceLock($year), 'Lock anual da conexão principal não foi liberado.');
$createdId = (new GabineteMonitoramentoEletronicoService($secondForSequence))->create([
    'numero_processo' => rei120ValidCnj(),
    'reu_complemento' => 'Parte sintética de teste',
]);
$createdSequence = $secondForSequence->prepare('SELECT numero_sequencial FROM gabinete_monitoramento_eletronico WHERE id = ?');
$createdSequence->execute([$createdId]);
rei120Check((int) $createdSequence->fetchColumn() === 4, 'Criação concorrente não alocou o próximo sequencial após obter o lock.');
$beforeRejectedDelete = rei120Snapshot($secondForSequence);
try {
    (new GabineteMonitoramentoEletronicoService($secondForSequence))->delete('M3');
    throw new RuntimeException('O registro anterior deixou de ser o último após criar o próximo sequencial.');
} catch (InvalidArgumentException $exception) {
    rei120Check(rei120Snapshot($secondForSequence) === $beforeRejectedDelete, 'Rejeição após criação concorrente alterou dados.');
}
$secondService = new GabineteMonitoramentoEletronicoService($secondForSequence);
rei120Check(($secondService->delete($createdId)['deleted'] ?? null) === true, 'Exclusão do novo último sequencial falhou.');
$deletedAfterSequenceRetry = (new GabineteMonitoramentoEletronicoService($secondForSequence))->delete('M3');
rei120Check(($deletedAfterSequenceRetry['deleted'] ?? null) === true, 'Retry de exclusão após liberar lock anual falhou.');

// Nested calls use a savepoint: failure rolls back only this operation and preserves outer work.
rei120Reset($pdo);
$beforeNested = rei120Snapshot($pdo);
$pdo->exec("CREATE TRIGGER rei120_fail_nested BEFORE UPDATE ON gabinete_monitoramento_eletronico FOR EACH ROW BEGIN IF OLD.id = 'M1' AND NEW.status = 'cancelada' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei120 synthetic failure'; END IF; END");
$pdo->beginTransaction();
$pdo->exec('INSERT INTO rei120_probe (id) VALUES (1)');
try {
    (new GabineteMonitoramentoEletronicoService($pdo))->cancel('M1');
    throw new RuntimeException('Falha de savepoint deveria ser propagada.');
} catch (PDOException $exception) {
    rei120Check(str_contains($exception->getMessage(), 'rei120 synthetic failure'), 'Erro de savepoint inesperado.');
}
rei120Check($pdo->inTransaction() && rei120Snapshot($pdo) === $beforeNested
    && (int) $pdo->query('SELECT COUNT(*) FROM rei120_probe')->fetchColumn() === 1, 'Rollback interno alterou a transação externa.');
$pdo->commit();
$pdo->exec('DROP TRIGGER IF EXISTS rei120_fail_nested');
rei120Check(rei120Snapshot($pdo) === $beforeNested
    && (int) $pdo->query('SELECT COUNT(*) FROM rei120_probe')->fetchColumn() === 1, 'Commit externo não preservou o trabalho anterior ao savepoint.');

// A row lock serializes competing mutations against the same monitoramento.
rei120Reset($pdo);
$dsnSecond = trim((string) getenv('REI120_MYSQL_DSN_SECOND'));
if ($dsnSecond !== $dsn) {
    throw new RuntimeException('A segunda conexão precisa apontar para a mesma fixture descartável.');
}
$second = new PDO($dsnSecond !== '' ? $dsnSecond : $dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$second->exec('SET SESSION innodb_lock_wait_timeout = 1');
$pdo->beginTransaction();
$lock = $pdo->prepare('SELECT id FROM gabinete_monitoramento_eletronico WHERE id = ? FOR UPDATE');
$lock->execute(['M1']);
$beforeRace = rei120Snapshot($pdo);
try {
    (new GabineteMonitoramentoEletronicoService($second))->cancel('M1');
    throw new RuntimeException('A mutação concorrente não aguardou o lock do parent.');
} catch (PDOException $exception) {
    $info = $exception->errorInfo ?? [];
    rei120Check(($info[1] ?? null) === 1205, 'A contenção concorrente falhou por motivo diferente de lock wait timeout.');
}
rei120Check($pdo->inTransaction() && rei120Snapshot($pdo) === $beforeRace, 'Tentativa concorrente deixou escrita parcial.');
$pdo->commit();
rei120Check((new GabineteMonitoramentoEletronicoService($second))->cancel('M1') === true, 'Retry após liberação do lock falhou.');
rei120Check($second->query("SELECT status FROM gabinete_monitoramento_eletronico WHERE id='M1'")->fetchColumn() === 'cancelada', 'Retry concorrente não chegou ao estado final esperado.');

echo "PASS REI-120: rollback MySQL/InnoDB, transições, savepoint externo, locks de concorrência e regras de cancelamento/exclusão.\n";
