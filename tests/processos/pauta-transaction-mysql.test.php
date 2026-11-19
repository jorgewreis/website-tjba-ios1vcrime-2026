<?php
declare(strict_types=1);

if (getenv('REI88_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Recusado: defina REI88_TEST_DISPOSABLE=1 somente em MySQL descartável.');
}
$repoRoot = getenv('REI88_TEST_REPO_ROOT') ?: dirname(__DIR__, 2);
if (!is_dir($repoRoot . '/app') || is_file($repoRoot . '/.env')) {
    throw new RuntimeException('Recusado: raiz de código inválida ou arquivo .env presente.');
}

$dsn = getenv('REI88_MYSQL_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4';
if (!str_contains($dsn, 'unix_socket=/var/run/mysqld/mysqld.sock')
    || preg_match('/(?:^|;)host\s*=/i', $dsn) === 1) {
    throw new RuntimeException('Recusado: fixture requer socket local, sem rede TCP.');
}
$expectedMarker = getenv('REI88_TEST_DISPOSABLE_MARKER') ?: '';
if (preg_match('/^[a-f0-9]{64}$/', $expectedMarker) !== 1) {
    throw new RuntimeException('Recusado: marcador efêmero de laboratório ausente ou inválido.');
}
$user = getenv('REI88_MYSQL_USER') ?: 'root';
$password = getenv('REI88_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$pdoSecondConnection = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
if ($dbName !== 'rei88_test' || !str_contains($versionComment, 'MySQL') || !str_starts_with($version, '8.4.')) {
    throw new RuntimeException('Recusado: use somente MySQL 8.4 no banco sintético rei88_test.');
}
$tables = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $tables);
if (count($tables) !== 1
    || strtolower((string) ($tables[0]['table_name'] ?? '')) !== 'rei88_test_guard'
    || strtolower((string) ($tables[0]['engine'] ?? '')) !== 'innodb') {
    throw new RuntimeException('Recusado: banco deve conter somente a tabela sentinela InnoDB criada pelo runner efêmero.');
}
$marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
if (!is_string($marker) || !hash_equals($expectedMarker, $marker)) {
    throw new RuntimeException('Recusado: sentinela do runner não corresponde à execução corrente.');
}

$fixtureTables = ['sala_audiencias_pauta_parte', 'sala_audiencias_pauta', 'processos', 'classes'];
register_shutdown_function(static function () use ($pdo, $pdoSecondConnection, $fixtureTables, $expectedMarker): void {
    try {
        foreach ([$pdoSecondConnection, $pdo] as $connection) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $remaining = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
        if ($remaining !== ['rei88_test_guard'] || !is_string($marker) || !hash_equals($expectedMarker, $marker)) {
            throw new RuntimeException('Verificação final da sentinela ou do schema falhou.');
        }
        fwrite(STDOUT, "CLEANUP schema temporário removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Falha ao remover/verificar o schema sintético: ' . $exception->getMessage() . "\n");
        exit(1);
    }
});

$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$ddl = [
    "CREATE TABLE classes (id VARCHAR(30) PRIMARY KEY, codigo VARCHAR(80) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id VARCHAR(30) PRIMARY KEY, numero_processo VARCHAR(32) NOT NULL UNIQUE, classe_id VARCHAR(30) NULL, data_fato DATE NULL, sigiloso TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_pauta_processo_classe FOREIGN KEY (classe_id) REFERENCES classes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(30) PRIMARY KEY, numero_sequencial INT NOT NULL, ano SMALLINT NOT NULL, processo VARCHAR(30) NOT NULL, tipo_audiencia VARCHAR(120) NOT NULL, data_audiencia DATE NOT NULL, hora_audiencia TIME NOT NULL, localizacao VARCHAR(255) NOT NULL, status VARCHAR(40) NOT NULL, observacoes TEXT NULL, link_audiencia VARCHAR(1000) NULL, criado_por VARCHAR(30) NULL, atualizado_por VARCHAR(30) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, data_hora_designacao DATETIME NULL, hora_inicio TIME NULL, hora_fim TIME NULL, duracao_estimada_minutos INT NULL, data_hora_inicio_efetivo DATETIME NULL, data_hora_fim_efetivo DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_pauta_processo FOREIGN KEY (processo) REFERENCES processos(id), KEY ix_test_pauta_slot (ativo, status, data_audiencia, hora_audiencia), KEY ix_test_pauta_processo (processo, ativo, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta_parte (id_parte VARCHAR(30) PRIMARY KEY, pauta VARCHAR(30) NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, CONSTRAINT fk_test_pauta_parte_pauta FOREIGN KEY (pauta) REFERENCES sala_audiencias_pauta(id_pauta)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

require_once $repoRoot . '/app/services/SalaAudienciasPautaService.php';
require_once $repoRoot . '/app/repositories/SalaAudienciasPautaRepository.php';

$futureDate = static fn (): string => (new DateTimeImmutable('today +10 days'))->format('Y-m-d');
$basePayload = [
    'numero_processo' => '0000001-69.2020.8.05.0001',
    'tipo_audiencia' => 'Instrução',
    'data_audiencia' => $futureDate(),
    'hora_audiencia' => '10:00',
    'localizacao' => 'Sala sintética',
];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$allRows = static function (PDO $db, string $table, string $orderBy): array {
    return $db->query('SELECT * FROM `' . $table . '` ORDER BY ' . $orderBy)->fetchAll(PDO::FETCH_ASSOC);
};
$snapshot = static function (PDO $db) use ($allRows): array {
    return [
        'classes' => $allRows($db, 'classes', 'id'),
        'processos' => $allRows($db, 'processos', 'id'),
        'pauta' => $allRows($db, 'sala_audiencias_pauta', 'id_pauta'),
        'partes' => $allRows($db, 'sala_audiencias_pauta_parte', 'id_parte'),
    ];
};
$reset = static function (PDO $db): void {
    $db->exec('DROP TRIGGER IF EXISTS rei88_fail_after_pauta_insert');
    $db->exec('DROP TRIGGER IF EXISTS rei136_delay_sequence_insert');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['sala_audiencias_pauta_parte', 'sala_audiencias_pauta', 'processos', 'classes'] as $table) {
        $db->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
};
$seedExistingProcess = static function (PDO $db, string $number) use ($assert): void {
    $db->exec("INSERT INTO classes (id, codigo, nome, created_at, updated_at) VALUES ('CLA-ORIGINAL', 'ORIGINAL', 'Classe original', '2000-01-01 00:00:00', '2000-01-01 00:00:00')");
    $st = $db->prepare('INSERT INTO processos (id, numero_processo, classe_id, data_fato, sigiloso, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute(['PRC-EXISTENTE', $number, 'CLA-ORIGINAL', '2010-05-04', 1, '2000-01-01 00:00:00', '2000-01-01 00:00:00']);
    $row = $db->query("SELECT classe_id, sigiloso FROM processos WHERE id='PRC-EXISTENTE'")->fetch(PDO::FETCH_ASSOC);
    $assert($row === ['classe_id' => 'CLA-ORIGINAL', 'sigiloso' => 1], 'Fixture do processo sigiloso não foi criada corretamente.');
};

// Processo existente: agendar deve preservar sigilo e classe existentes quando payload não traz classe nova.
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$acceptedId = (new SalaAudienciasPautaService($pdo))->create($basePayload, null);
$existing = $pdo->query("SELECT classe_id, sigiloso FROM processos WHERE id='PRC-EXISTENTE'")->fetch(PDO::FETCH_ASSOC);
$accepted = $pdo->prepare('SELECT id_pauta, processo, status, ativo FROM sala_audiencias_pauta WHERE id_pauta=:id');
$accepted->execute([':id' => $acceptedId]);
$acceptedRow = $accepted->fetch(PDO::FETCH_ASSOC);
$assert($existing === ['classe_id' => 'CLA-ORIGINAL', 'sigiloso' => 1], 'Agendamento aceito alterou classe ou sigilo do processo existente.');
$assert($acceptedRow !== false && $acceptedRow['processo'] === 'PRC-EXISTENTE' && $acceptedRow['status'] === 'designada' && (int) $acceptedRow['ativo'] === 1, 'Agendamento aceito não persistiu a pauta esperada.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() === 1, 'Agendamento aceito criou ou removeu classe inesperadamente.');
fwrite(STDOUT, "PASS processo existente: pauta aceita preservou classe e sigilo.\n");

// Pauta ativa do mesmo processo deve rejeitar dentro da transação e preservar snapshot integral.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$blockingDate = $basePayload['data_audiencia'];
$st = $pdo->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, numero_sequencial, ano, processo, tipo_audiencia, data_audiencia, hora_audiencia, localizacao, status, ativo, created_at, updated_at) VALUES ('SAP-ATIVA', 1, YEAR(CURDATE()), 'PRC-EXISTENTE', 'Instrução', :data, '09:00:00', 'Sala sintética', 'designada', 1, '2000-01-01 00:00:00', '2000-01-01 00:00:00')");
$st->execute([':data' => $blockingDate]);
$before = $snapshot($pdo);
$payloadWithNewClass = $basePayload + ['classe_codigo' => 'NOVACLASSE', 'classe_descricao' => 'Classe proposta'];
try {
    (new SalaAudienciasPautaService($pdo))->create($payloadWithNewClass, null);
    throw new RuntimeException('Pauta ativa do processo deveria rejeitar o cadastro.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'Já existe audiência ativa para este número de processo')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Rejeição por pauta ativa alterou o snapshot de processo, classe ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após rejeição por pauta ativa.');
fwrite(STDOUT, "PASS pauta ativa: rejeição preservou snapshot integral.\n");

// Trigger AFTER INSERT comprova falha depois de a escrita da pauta ser iniciada; o rollback deve desfazer todos os writes.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$before = $snapshot($pdo);
$pdo->exec("CREATE TRIGGER rei88_fail_after_pauta_insert AFTER INSERT ON sala_audiencias_pauta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI88_FAIL_AFTER_Pauta_INSERT'");
try {
    (new SalaAudienciasPautaService($pdo))->create($payloadWithNewClass, null);
    throw new RuntimeException('Falha posterior ao insert da pauta não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'REI88_FAIL_AFTER_Pauta_INSERT')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Falha após insert deixou alterações de classe, processo ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após falha induzida.');
fwrite(STDOUT, "PASS falha pós-insert: rollback reverteu classe, processo e pauta.\n");

// Processo inexistente: falha após criação deve reverter processo, classe e pauta.
$reset($pdo);
$before = $snapshot($pdo);
$newProcessPayload = array_replace($basePayload, [
    'numero_processo' => '0000002-54.2020.8.05.0001',
    'classe_codigo' => 'CLASSE-NOVA',
    'classe_descricao' => 'Classe nova sintética',
]);
$pdo->exec("CREATE TRIGGER rei88_fail_after_pauta_insert AFTER INSERT ON sala_audiencias_pauta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI88_FAIL_AFTER_Pauta_INSERT'");
try {
    (new SalaAudienciasPautaService($pdo))->create($newProcessPayload, null);
    throw new RuntimeException('Falha do processo novo não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'REI88_FAIL_AFTER_Pauta_INSERT')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Rollback de processo novo deixou processo, classe ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após rollback de processo novo.');
fwrite(STDOUT, "PASS processo novo com falha: rollback removeu processo, classe e pauta.\n");
$failureReleaseProbe = new SalaAudienciasPautaSequenceLock($pdoSecondConnection);
$failureReleaseProbe->acquire((int) date('Y'), 0);
$assert($failureReleaseProbe->release(), 'Falha da transação deixou o lock anual retido.');
fwrite(STDOUT, "PASS falha transacional: segunda conexão readquiriu o lock anual.\n");

// Processo novo com fluxo aceito deve persistir o processo, sua classe e a pauta relacionada.
$reset($pdo);
$newProcessPayload['numero_processo'] = '0000002-54.2020.8.05.0001';
$newId = (new SalaAudienciasPautaService($pdo))->create($newProcessPayload, null);
$newProcess = $pdo->prepare('SELECT id, classe_id, sigiloso FROM processos WHERE numero_processo=:numero');
$newProcess->execute([':numero' => $newProcessPayload['numero_processo']]);
$newProcessRow = $newProcess->fetch(PDO::FETCH_ASSOC);
$newPauta = $pdo->prepare('SELECT processo, status FROM sala_audiencias_pauta WHERE id_pauta=:id');
$newPauta->execute([':id' => $newId]);
$newPautaRow = $newPauta->fetch(PDO::FETCH_ASSOC);
$class = $pdo->prepare('SELECT codigo, nome FROM classes WHERE id=:id');
$class->execute([':id' => $newProcessRow['classe_id'] ?? '']);
$classRow = $class->fetch(PDO::FETCH_ASSOC);
$assert($newProcessRow !== false && $newProcessRow['id'] !== '' && (int) $newProcessRow['sigiloso'] === 0, 'Fluxo aceito não criou processo novo com sigilo padrão.');
$assert($classRow === ['codigo' => 'CLASSENOVA', 'nome' => 'Classe Nova Sintética'], 'Fluxo aceito não persistiu a classe nova esperada.');
$assert($newPautaRow !== false && $newPautaRow['processo'] === $newProcessRow['id'] && $newPautaRow['status'] === 'designada', 'Pauta aceita não foi vinculada ao processo novo.');
$assert(!$pdo->inTransaction(), 'Fluxo aceito deixou transação aberta.');
fwrite(STDOUT, "PASS processo novo: fluxo aceito persistiu processo, classe e pauta.\n");

// Duas sessões independentes comprovam contenção do lock e leitura do MAX após
// o commit anterior, sem depender de uma constraint que a fixture não modela.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$year = (int) date('Y');
$firstLock = new SalaAudienciasPautaSequenceLock($pdo);
$secondLock = new SalaAudienciasPautaSequenceLock($pdoSecondConnection);
$firstLock->acquire($year, 0);
$timedOut = false;
try {
    $secondLock->acquire($year, 0);
} catch (SalaAudienciasPautaSequenceLockException $exception) {
    $timedOut = true;
}
$assert($timedOut, 'A segunda conexão não respeitou a contenção/timeout do lock anual.');

$repositoryFirst = new SalaAudienciasPautaRepository($pdo);
$pdo->beginTransaction();
$firstSequence = $repositoryFirst->nextSequencialForYear($year);
$insertFixturePauta = static function (PDO $db, int $sequence, string $id, string $time) use ($year, $futureDate): void {
    $statement = $db->prepare(
        "INSERT INTO sala_audiencias_pauta (id_pauta, numero_sequencial, ano, processo, tipo_audiencia, data_audiencia, hora_audiencia, localizacao, status, ativo) VALUES (:id, :sequence, :year, 'PRC-EXISTENTE', 'Instrucao', :date, :time, 'Sala sintetica', 'designada', 1)"
    );
    $statement->execute([
        ':id' => $id,
        ':sequence' => $sequence,
        ':year' => $year,
        ':date' => $futureDate(),
        ':time' => $time,
    ]);
};
$insertFixturePauta($pdo, $firstSequence, 'SAP-LOCK-1', '09:00:00');
$pdo->commit();
$assert($firstLock->release(), 'A primeira conexão não liberou o lock após commit.');

$secondLock->acquire($year, 0);
$pdoSecondConnection->beginTransaction();
$secondSequence = (new SalaAudienciasPautaRepository($pdoSecondConnection))->nextSequencialForYear($year);
$assert($secondSequence === $firstSequence + 1, 'A segunda conexão não observou o sequencial confirmado pela primeira.');
$insertFixturePauta($pdoSecondConnection, $secondSequence, 'SAP-LOCK-2', '09:15:00');
$pdoSecondConnection->commit();
$assert($secondLock->release(), 'A segunda conexão não liberou o lock após commit.');
$assert(
    (int) $pdo->query('SELECT COUNT(DISTINCT numero_sequencial) FROM sala_audiencias_pauta')->fetchColumn() === 2,
    'As duas sessões persistiram sequenciais repetidos.'
);
fwrite(STDOUT, "PASS duas conexões MySQL: contenção, timeout, sequência única e liberação após commit/falha.\n");

// A falha ao abrir uma transação aninhada não pode reverter a transação do chamador.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$pdo->beginTransaction();
$pdo->exec("INSERT INTO classes (id, codigo, nome) VALUES ('CLA-EXTERNA', 'EXTERNA', 'Transação externa')");
try {
    (new SalaAudienciasPautaService($pdo))->create($basePayload, null);
    throw new RuntimeException('A transação externa deveria impedir beginTransaction() do serviço.');
} catch (PDOException $exception) {
    $assert($pdo->inTransaction(), 'A falha ao iniciar transação reverteu a transação externa.');
}
$externalTransactionLockProbe = new SalaAudienciasPautaSequenceLock($pdoSecondConnection);
$externalTransactionLockProbe->acquire($year, 0);
$assert($externalTransactionLockProbe->release(), 'Falha ao iniciar transação deixou o lock anual retido.');
$pdo->commit();
$assert(
    (int) $pdo->query("SELECT COUNT(*) FROM classes WHERE id = 'CLA-EXTERNA'")->fetchColumn() === 1,
    'A transação do chamador não pôde ser confirmada após a falha do serviço.'
);
fwrite(STDOUT, "PASS transação externa: falha do serviço preservou a transação do chamador e liberou o lock.\n");

// Duas chamadas reais e simultâneas ao serviço usam processos e conexões próprias.
// O trigger amplia a janela entre MAX+1 e INSERT para tornar a corrida reproduzível
// caso a serialização seja removida; nenhum dado fora do fixture é utilizado.
$reset($pdo);
$seedExistingProcess($pdo, '0000003-39.2020.8.05.0001');
$pdo->exec(
    'CREATE TRIGGER rei136_delay_sequence_insert BEFORE INSERT ON sala_audiencias_pauta '
    . 'FOR EACH ROW BEGIN DO SLEEP(2); END'
);
$workerDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rei136-' . bin2hex(random_bytes(12));
if (!mkdir($workerDirectory, 0700)) {
    throw new RuntimeException('Não foi possível criar diretório temporário dos workers.');
}
$workerPath = $workerDirectory . DIRECTORY_SEPARATOR . 'create-pauta.php';
$startPath = $workerDirectory . DIRECTORY_SEPARATOR . 'start';
$workerScript = <<<'PHP'
<?php
declare(strict_types=1);
require_once getenv('REI88_TEST_REPO_ROOT') . '/app/services/SalaAudienciasPautaService.php';
$pdo = new PDO(
    getenv('REI88_MYSQL_DSN'),
    getenv('REI88_MYSQL_USER') ?: 'root',
    getenv('REI88_MYSQL_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
file_put_contents(getenv('REI136_READY_FILE'), 'ready', LOCK_EX);
$deadline = microtime(true) + 15;
while (!is_file(getenv('REI136_START_FILE')) && microtime(true) < $deadline) {
    usleep(10000);
}
if (!is_file(getenv('REI136_START_FILE'))) {
    fwrite(STDERR, "Timeout aguardando início simultâneo.\n");
    exit(2);
}
try {
    $id = (new SalaAudienciasPautaService($pdo))->create(
        json_decode(getenv('REI136_PAYLOAD'), true, 512, JSON_THROW_ON_ERROR),
        null
    );
    file_put_contents(getenv('REI136_RESULT_FILE'), json_encode(['id' => $id], JSON_THROW_ON_ERROR), LOCK_EX);
} catch (Throwable $exception) {
    file_put_contents(
        getenv('REI136_RESULT_FILE'),
        json_encode(['error' => get_class($exception) . ': ' . $exception->getMessage()], JSON_THROW_ON_ERROR),
        LOCK_EX
    );
    exit(1);
}
PHP;
if (file_put_contents($workerPath, $workerScript, LOCK_EX) === false) {
    throw new RuntimeException('Não foi possível gravar worker temporário.');
}
$workerPayloads = [
    array_replace($basePayload, [
        'numero_processo' => '0000003-39.2020.8.05.0001',
        'hora_audiencia' => '09:15',
    ]),
    array_replace($basePayload, [
        'numero_processo' => '0000004-20.2020.8.05.0001',
        'hora_audiencia' => '09:30',
    ]),
];
$workers = [];
$workerResults = [];
try {
    foreach ($workerPayloads as $index => $payload) {
        $readyPath = $workerDirectory . DIRECTORY_SEPARATOR . 'ready-' . $index;
        $resultPath = $workerDirectory . DIRECTORY_SEPARATOR . 'result-' . $index;
        $stdoutPath = $workerDirectory . DIRECTORY_SEPARATOR . 'stdout-' . $index;
        $stderrPath = $workerDirectory . DIRECTORY_SEPARATOR . 'stderr-' . $index;
        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        $environment += [
            'REI88_TEST_REPO_ROOT' => $repoRoot,
            'REI88_MYSQL_DSN' => $dsn,
            'REI88_MYSQL_USER' => $user,
            'REI88_MYSQL_PASSWORD' => $password,
        ];
        $environment['REI136_READY_FILE'] = $readyPath;
        $environment['REI136_START_FILE'] = $startPath;
        $environment['REI136_RESULT_FILE'] = $resultPath;
        $environment['REI136_PAYLOAD'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $process = proc_open(
            [PHP_BINARY, $workerPath],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $stdoutPath, 'a'],
                2 => ['file', $stderrPath, 'a'],
            ],
            $pipes,
            $workerDirectory,
            $environment
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar worker concorrente.');
        }
        $workers[] = ['process' => $process, 'resultPath' => $resultPath, 'stderrPath' => $stderrPath];
    }

    $readyDeadline = microtime(true) + 15;
    do {
        $allReady = true;
        foreach (array_keys($workerPayloads) as $index) {
            $allReady = $allReady && is_file($workerDirectory . DIRECTORY_SEPARATOR . 'ready-' . $index);
        }
        if (!$allReady) {
            usleep(10000);
        }
    } while (!$allReady && microtime(true) < $readyDeadline);
    $assert($allReady, 'As duas conexões concorrentes não ficaram prontas a tempo.');
    if (file_put_contents($startPath, 'start', LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível liberar a barreira dos workers.');
    }

    foreach ($workers as $worker) {
        $exitCode = proc_close($worker['process']);
        $resultText = is_file($worker['resultPath']) ? file_get_contents($worker['resultPath']) : false;
        $workerResults[] = is_string($resultText) ? json_decode($resultText, true, 512, JSON_THROW_ON_ERROR) : null;
        $stderr = is_file($worker['stderrPath']) ? trim((string) file_get_contents($worker['stderrPath'])) : '';
        $assert($exitCode === 0, 'Worker concorrente falhou: ' . $stderr);
    }
} finally {
    foreach ($workers as $worker) {
        if (is_resource($worker['process'])) {
            proc_terminate($worker['process']);
            proc_close($worker['process']);
        }
    }
    foreach (glob($workerDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path) && !is_link($path)) {
            unlink($path);
        }
    }
    if (is_dir($workerDirectory) && !is_link($workerDirectory)) {
        rmdir($workerDirectory);
    }
}
$createdIds = array_values(array_filter(array_map(
    static fn ($result): string => is_array($result) ? (string) ($result['id'] ?? '') : '',
    $workerResults
)));
$assert(count($createdIds) === 2, 'As chamadas concorrentes não retornaram dois IDs de pauta.');
$sequenceQuery = $pdo->prepare(
    'SELECT numero_sequencial FROM sala_audiencias_pauta WHERE id_pauta IN (?, ?) ORDER BY numero_sequencial'
);
$sequenceQuery->execute($createdIds);
$concurrentSequences = array_map('intval', $sequenceQuery->fetchAll(PDO::FETCH_COLUMN));
$assert($concurrentSequences === [1, 2], 'Duas chamadas concorrentes ao serviço não receberam sequenciais 1 e 2.');
fwrite(STDOUT, "PASS concorrência real: duas chamadas simultâneas a create() persistiram sequenciais distintos.\n");

echo "PASS 8 cenários SalaAudienciasPautaService::create() (MySQL/InnoDB sintético).\n";
