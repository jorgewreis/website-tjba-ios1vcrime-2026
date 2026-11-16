<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';

function expectConcurrency(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dsn = getenv('FERIADOS_TEST_DSN');
if (getenv('FERIADOS_TEST_ISOLATION') !== 'isolated-feriados-test'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=127\.0\.0\.1;dbname=feriados_[a-f0-9]{32};charset=utf8mb4$/', $dsn)
    || getenv('FERIADOS_TEST_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{32}$/', (string) getenv('FERIADOS_TEST_PASSWORD'))) {
    throw new RuntimeException('Teste concorrente permitido somente pelo runner MySQL descartável de feriados.');
}

$pdo = new PDO($dsn, (string) getenv('FERIADOS_TEST_USER'), (string) getenv('FERIADOS_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$pdo->exec('DROP TABLE IF EXISTS editais_subtarefas, editais, processos, classes, feriados');
$pdo->exec('CREATE TABLE feriados (id VARCHAR(64) PRIMARY KEY, tipo_feriado VARCHAR(32), data_inicio DATE, data_fim DATE, regra_data VARCHAR(32), descricao VARCHAR(255), ativo INTEGER, created_at DATETIME NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE editais (id VARCHAR(64) PRIMARY KEY, processo_id VARCHAR(64) NULL, status VARCHAR(64), tipo_edital VARCHAR(64), prazo_dias INTEGER, data_publicacao DATE NULL, data_inicio_contagem DATE NULL, data_decurso_prazo DATE NULL, observacoes TEXT NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE editais_subtarefas (id VARCHAR(64) PRIMARY KEY, edital_id VARCHAR(64), tipo VARCHAR(64), ordem INTEGER, status VARCHAR(32), dados_especificos TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE processos (id VARCHAR(64), classe_id VARCHAR(64), numero_processo VARCHAR(64))' . $engine);
$pdo->exec('CREATE TABLE classes (id VARCHAR(64), nome VARCHAR(128), codigo VARCHAR(32))' . $engine);
$today = new DateTimeImmutable('today');
$publicationDate = $today->modify('-6 days');
$holidayStart = $publicationDate->modify('+1 day');
$holidayEnd = $today->modify('+5 days');
$publicationIso = $publicationDate->format('Y-m-d');
$pdo->prepare('INSERT INTO editais VALUES (?, NULL, ?, ?, 2, ?, ?, ?, NULL, NULL)')->execute([
    'REI114-RACE',
    'pendente',
    'comum',
    $publicationIso,
    $holidayStart->format('Y-m-d'),
    $publicationDate->modify('+3 days')->format('Y-m-d'),
]);
$publication = json_encode(['data_publicacao' => $publicationIso], JSON_THROW_ON_ERROR);
$insertSubtask = $pdo->prepare('INSERT INTO editais_subtarefas VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
$insertSubtask->execute(['PUB-RACE', 'REI114-RACE', 'certificacao_publicacao', 1, 'concluida', $publication]);
$insertSubtask->execute(['DEC-RACE', 'REI114-RACE', 'certificacao_decurso_prazo', 2, 'pendente', null]);

$lockName = 'fokus-law-feriados-recalc';
expectConcurrency((int) $pdo->query("SELECT GET_LOCK('{$lockName}', 0)")->fetchColumn() === 1, 'A conexão de calendário deve obter o mutex.');
$readyPath = sys_get_temp_dir() . '/rei114-calendar-worker-' . bin2hex(random_bytes(8));
$worker = proc_open(
    [PHP_BINARY, __DIR__ . '/rei114-calendar-reconcile-worker.php', $readyPath, 'REI114-RACE'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    [
        'FERIADOS_TEST_ISOLATION' => 'isolated-feriados-test',
        'FERIADOS_TEST_DSN' => $dsn,
        'FERIADOS_TEST_USER' => (string) getenv('FERIADOS_TEST_USER'),
        'FERIADOS_TEST_PASSWORD' => (string) getenv('FERIADOS_TEST_PASSWORD'),
    ]
);
if (!is_resource($worker)) {
    $pdo->query("SELECT RELEASE_LOCK('{$lockName}')");
    throw new RuntimeException('Não foi possível iniciar a reconciliação concorrente.');
}
fclose($pipes[0]);
try {
    $deadline = microtime(true) + 5.0;
    while (!is_file($readyPath) && microtime(true) < $deadline) {
        usleep(10000);
    }
    expectConcurrency(is_file($readyPath), 'O worker não sinalizou início dentro do limite.');
    $workerInfo = json_decode((string) file_get_contents($readyPath), true, 512, JSON_THROW_ON_ERROR);
    $pendingLock = $pdo->prepare("SELECT COUNT(*) FROM performance_schema.metadata_locks AS ml JOIN performance_schema.threads AS th ON th.THREAD_ID = ml.OWNER_THREAD_ID WHERE ml.OBJECT_TYPE = 'USER LEVEL LOCK' AND ml.OBJECT_NAME = ? AND ml.LOCK_STATUS = 'PENDING' AND th.PROCESSLIST_ID = ?");
    $waitingDeadline = microtime(true) + 5.0;
    $isWaiting = false;
    do {
        $pendingLock->execute([$lockName, (int) ($workerInfo['connection_id'] ?? 0)]);
        $isWaiting = (int) $pendingLock->fetchColumn() > 0;
        if (!$isWaiting) {
            usleep(20000);
        }
    } while (!$isWaiting && microtime(true) < $waitingDeadline);
    expectConcurrency($isWaiting, 'MySQL não mostrou a conexão do worker aguardando o named lock do calendário.');

    $pdo->beginTransaction();
    $holiday = $pdo->prepare('INSERT INTO feriados VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())');
    $holiday->execute(['FER-RACE', 'municipal', $holidayStart->format('Y-m-d'), $holidayEnd->format('Y-m-d'), 'unica', 'race fixture']);
    (new EditaisService($pdo))->recalcularDecursosPorIntervalosFeriado([[$holidayStart->format('Y-m-d'), $holidayEnd->format('Y-m-d')]]);
    $pdo->commit();
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->query("SELECT RELEASE_LOCK('{$lockName}')");
}

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($worker);
@unlink($readyPath);
expectConcurrency($exitCode === 0, 'Worker falhou: ' . trim((string) $stderr));
$workerResult = json_decode(trim((string) $stdout), true, 512, JSON_THROW_ON_ERROR);
$result = $workerResult['result'] ?? [];
$row = $pdo->query("SELECT status, data_decurso_prazo FROM editais WHERE id = 'REI114-RACE'")->fetch(PDO::FETCH_ASSOC);
$deadline = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($row['data_decurso_prazo'] ?? ''));
expectConcurrency(($result['finalizados'] ?? null) === 0, 'Reconciliação não pode finalizar com o calendário atualizado.');
expectConcurrency(($row['status'] ?? null) === 'pendente' && $deadline instanceof DateTimeImmutable && $deadline > $today, 'Edital deve refletir o feriado e permanecer pendente.');
expectConcurrency(array_key_exists('lock_owner_after', $workerResult) && $workerResult['lock_owner_after'] === null, 'A conexão de reconciliação deve liberar o mutex ao retornar.');

echo "PASS REI-114: reconciliação aguardou mutação concorrente do calendário e leu o prazo recalculado em MySQL/InnoDB.\n";
