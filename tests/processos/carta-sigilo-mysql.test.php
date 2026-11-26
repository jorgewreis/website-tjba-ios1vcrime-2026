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
$fixtureTables = ['processo_datajud_sync_queue', 'cartas_expedidas_subtarefas', 'cartas_expedidas', 'varas', 'comarcas', 'tribunais', 'processos', 'classes'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $expectedMarker): void {
    try {
        $guard = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei88_test' || !is_string($guard) || !hash_equals($expectedMarker, $guard)) { throw new RuntimeException('Sentinela não conferiu na limpeza.'); }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        fwrite(STDOUT, "CLEANUP schema temporário removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "Falha ao remover o schema sintético: " . $exception->getMessage() . "\n");
        exit(1);
    }
});
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

$ddl = [
    "CREATE TABLE classes (id VARCHAR(30) PRIMARY KEY, codigo VARCHAR(80) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id VARCHAR(30) PRIMARY KEY, numero_processo VARCHAR(32) NOT NULL UNIQUE, classe_id VARCHAR(30) NULL, data_fato DATE NULL, sigiloso TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_processo_classe FOREIGN KEY (classe_id) REFERENCES classes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processo_datajud_sync_queue (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, processo_id VARCHAR(30) NOT NULL UNIQUE, requested_by CHAR(30) NULL, status VARCHAR(16) NOT NULL DEFAULT 'pendente', tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0, proxima_tentativa_em DATETIME NOT NULL, solicitado_em DATETIME(6) NOT NULL, processando_em DATETIME(6) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, atualizado_em DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE tribunais (id VARCHAR(30) PRIMARY KEY, sigla VARCHAR(16) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE comarcas (id VARCHAR(30) PRIMARY KEY, tribunal_id VARCHAR(30) NOT NULL, uf CHAR(2) NOT NULL, nome VARCHAR(200) NOT NULL, cidade VARCHAR(200) NULL, status_cidade VARCHAR(32) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY uq_test_comarca (tribunal_id, uf, nome), CONSTRAINT fk_test_comarca_tribunal FOREIGN KEY (tribunal_id) REFERENCES tribunais(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE varas (id VARCHAR(30) PRIMARY KEY, comarca_id VARCHAR(30) NOT NULL, codigo VARCHAR(80) NOT NULL DEFAULT '', nome VARCHAR(200) NOT NULL, email VARCHAR(200) NOT NULL DEFAULT '', telefone VARCHAR(80) NOT NULL DEFAULT '', created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_vara_comarca FOREIGN KEY (comarca_id) REFERENCES comarcas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE cartas_expedidas (id VARCHAR(30) PRIMARY KEY, processo_origem_id VARCHAR(30) NOT NULL, numero_processo_origem VARCHAR(32) NOT NULL, finalidade VARCHAR(255) NOT NULL, prazo_dias INT NOT NULL, comarca_destino_id VARCHAR(30) NOT NULL, cidade_destino VARCHAR(200) NOT NULL, uf_destino CHAR(2) NOT NULL, numero_processo_destino VARCHAR(32) NULL, vara_destino_id VARCHAR(30) NULL, vara_destino_nome VARCHAR(200) NULL, status VARCHAR(40) NOT NULL, observacoes TEXT NULL, criado_por VARCHAR(30) NULL, atualizado_por VARCHAR(30) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_carta_processo FOREIGN KEY (processo_origem_id) REFERENCES processos(id), CONSTRAINT fk_test_carta_comarca FOREIGN KEY (comarca_destino_id) REFERENCES comarcas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE cartas_expedidas_subtarefas (id VARCHAR(30) PRIMARY KEY, carta_expedida_id VARCHAR(30) NOT NULL, tipo VARCHAR(40) NOT NULL, ordem INT NOT NULL, status VARCHAR(32) NOT NULL, dados_especificos JSON NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_carta_subtarefa FOREIGN KEY (carta_expedida_id) REFERENCES cartas_expedidas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}
$migrationSql = file_get_contents($repoRoot . '/database/migrations/20261116_add_cartas_expedidas_idempotency.sql');
if (!is_string($migrationSql) || trim($migrationSql) === '') {
    throw new RuntimeException('Migração sintética de idempotência ausente ou vazia.');
}
$pdo->exec($migrationSql);

require_once $repoRoot . '/app/services/CartasExpedidasService.php';
require_once $repoRoot . '/app/repositories/ProcessosRepository.php';
require_once $repoRoot . '/app/helpers/CnjValidator.php';

$existingCnj = '0000001-69.2020.8.05.0001';
$pdo->exec("INSERT INTO classes (id, codigo, nome) VALUES ('CLA-OLD', 'OLD', 'Classe anterior')");
$stmt = $pdo->prepare('INSERT INTO processos (id, numero_processo, classe_id, sigiloso) VALUES (?, ?, ?, 1)');
$stmt->execute(['PRC-EXISTING', $existingCnj, 'CLA-OLD']);
$pdo->exec("INSERT INTO processos (id, numero_processo, classe_id, sigiloso) VALUES ('PRC-EXPLICIT', '0000004-24.2020.8.05.0001', 'CLA-OLD', 0)");
$base = [
    'numero_processo' => $existingCnj,
    'estado' => 'SP',
    'cidade' => 'Campinas',
    'classe_codigo' => 'CARTA-TESTE',
    'classe_descricao' => 'Classe sintética',
    'finalidade' => 'Carta precatória sintética',
];
$inputs = [
    'ausente' => [],
    'nulo' => ['nivel_sigilo' => null],
    'false' => ['nivel_sigilo' => false],
    'zero' => ['nivel_sigilo' => 0],
    'zero-string' => ['nivel_sigilo' => '0'],
    'true' => ['nivel_sigilo' => true],
];
$service = new CartasExpedidasService($pdo);
$expectedClassId = (new ClassesRepository($pdo))->ensureClass('CARTA-TESTE', 'Classe sintética');
$repository = new CartasExpedidasRepository($pdo);
$pdo->exec('ALTER TABLE cartas_expedidas DROP INDEX uq_cartas_expedidas_idempotency');
$beforePartialSchema = [
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn(),
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn(),
];
try {
    $service->create(array_merge($base, [
        'usuario_cadastro_id' => 'USER-REI78-PARTIAL',
        'idempotency_key' => str_repeat('c', 64),
    ]));
    throw new RuntimeException('Schema parcial sem índice único deveria bloquear o envio idempotente.');
} catch (IdempotencySchemaUnavailableException $expected) {
    if (!str_contains($expected->getPrevious()?->getMessage() ?? '', 'Índice único')) {
        throw new RuntimeException('Falha inesperada ao detectar schema parcial.', 0, $expected);
    }
}
if ($beforePartialSchema !== [
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn(),
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn(),
]) {
    throw new RuntimeException('Schema parcial permitiu gravação antes de bloquear a idempotência.');
}
$pdo->exec('ALTER TABLE cartas_expedidas ADD UNIQUE KEY uq_cartas_expedidas_idempotency (criado_por, idempotency_key)');
$repository->assertIdempotencyColumnsAvailable();
foreach ($inputs as $case => $extra) {
    $service->create($base + $extra);
    $row = $pdo->query("SELECT sigiloso, classe_id FROM processos WHERE id = 'PRC-EXISTING'")->fetch(PDO::FETCH_ASSOC);
    if ($row === false || (int) $row['sigiloso'] !== 1 || $row['classe_id'] !== $expectedClassId) {
        throw new RuntimeException("Processo existente perdeu sigilo ou não recebeu a classe esperada no caso: $case");
    }
}
$letterCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$subtaskCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
if ($letterCount !== count($inputs) || $subtaskCount !== count($inputs) * 2) {
    throw new RuntimeException('Criação completa de cartas/subtarefas não persistiu as quantidades esperadas.');
}

$idempotentPayload = array_merge($base, [
    'usuario_cadastro_id' => 'USER-REI78-TEST',
    'idempotency_key' => str_repeat('a', 64),
]);
$idempotentId = $service->create($idempotentPayload);
$afterFirstIdempotentCreate = [
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn(),
    (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn(),
];
if ($service->create($idempotentPayload) !== $idempotentId
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $afterFirstIdempotentCreate[0]
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $afterFirstIdempotentCreate[1]) {
    throw new RuntimeException('Retry idempotente criou registros duplicados ou retornou outra carta.');
}
try {
    $service->create(array_merge($idempotentPayload, ['finalidade' => 'Finalidade diferente']));
    throw new RuntimeException('Reutilização da chave idempotente com outro payload deveria ser recusada.');
} catch (DomainException) {
    // A chave fica vinculada ao conteúdo original.
}
if ((int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $afterFirstIdempotentCreate[0]
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $afterFirstIdempotentCreate[1]) {
    throw new RuntimeException('Payload divergente com a mesma chave alterou registros.');
}

$concurrentKey = str_repeat('c', 64);
$concurrentPayload = array_merge($base, [
    'usuario_cadastro_id' => 'USER-REI78-TEST',
    'idempotency_key' => $concurrentKey,
]);
$raceLetterCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$raceSubtaskCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
$pdo->exec("CREATE TRIGGER rei78_delay_idempotent BEFORE INSERT ON cartas_expedidas FOR EACH ROW BEGIN IF NEW.idempotency_key = '" . $concurrentKey . "' THEN DO GET_LOCK('rei78-idempotency-race', 10); DO SLEEP(1); DO RELEASE_LOCK('rei78-idempotency-race'); END IF; END");
$childCode = <<<'PHP'
require getenv('REI88_TEST_REPO_ROOT') . '/app/services/CartasExpedidasService.php';
$db = new PDO(getenv('REI88_MYSQL_DSN'), getenv('REI88_MYSQL_USER'), getenv('REI88_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$guard = $db->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'rei88_test' || !is_string($guard) || !hash_equals((string) getenv('REI88_TEST_DISPOSABLE_MARKER'), $guard)) { throw new RuntimeException('Worker recusado: banco ou sentinela não conferem.'); }
$payload = json_decode(base64_decode(getenv('REI78_RACE_PAYLOAD'), true), true, 512, JSON_THROW_ON_ERROR);
echo (new CartasExpedidasService($db))->create($payload);
PHP;
$childEnvironment = [
    'REI88_TEST_REPO_ROOT' => $repoRoot,
    'REI88_MYSQL_DSN' => $dsn,
    'REI88_MYSQL_USER' => $user,
    'REI88_MYSQL_PASSWORD' => $password,
    'REI88_TEST_DISPOSABLE_MARKER' => $expectedMarker,
    'REI78_RACE_PAYLOAD' => base64_encode(json_encode($concurrentPayload, JSON_THROW_ON_ERROR)),
];
$childPipes = [];
$child = proc_open([PHP_BINARY, '-r', $childCode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $childPipes, null, $childEnvironment);
if (!is_resource($child)) {
    $pdo->exec('DROP TRIGGER rei78_delay_idempotent');
    throw new RuntimeException('Não foi possível iniciar a requisição concorrente sintética.');
}
fclose($childPipes[0]);
$lockObserved = false;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $lockOwner = $pdo->query("SELECT IS_USED_LOCK('rei78-idempotency-race')")->fetchColumn();
    if ($lockOwner !== false && $lockOwner !== null) {
        $lockObserved = true;
        break;
    }
    usleep(20000);
}
if (!$lockObserved) {
    proc_terminate($child);
    stream_get_contents($childPipes[1]);
    stream_get_contents($childPipes[2]);
    fclose($childPipes[1]);
    fclose($childPipes[2]);
    proc_close($child);
    $pdo->exec('DROP TRIGGER rei78_delay_idempotent');
    throw new RuntimeException('A fixture não observou a primeira inserção dentro da janela de sincronização.');
}
$concurrentId = $service->create($concurrentPayload);
$childId = stream_get_contents($childPipes[1]);
$childError = stream_get_contents($childPipes[2]);
fclose($childPipes[1]);
fclose($childPipes[2]);
$childExit = proc_close($child);
$pdo->exec('DROP TRIGGER rei78_delay_idempotent');
if ($childExit !== 0 || trim($childId) !== $concurrentId || $childError !== ''
    || (int) $pdo->query("SELECT COUNT(*) FROM cartas_expedidas WHERE idempotency_key = '" . $concurrentKey . "'")->fetchColumn() !== 1
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $raceLetterCount + 1
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $raceSubtaskCount + 2) {
    throw new RuntimeException('Duas requisições simultâneas com a mesma chave não retornaram uma única carta: ' . trim($childError));
}

$newCnj = '0000002-54.2020.8.05.0001';
$service->create(array_merge($base, ['numero_processo' => $newCnj]));
$newProcess = (new ProcessosRepository($pdo))->findByNumero($newCnj);
if ($newProcess === null || (int) $newProcess['sigiloso'] !== 0 || $newProcess['classe_id'] !== $expectedClassId) {
    throw new RuntimeException('Processo novo com classe conhecida não recebeu estado não sigiloso esperado.');
}

$classlessCnj = '0000003-39.2020.8.05.0001';
$classlessPayload = $base;
unset($classlessPayload['classe_codigo'], $classlessPayload['classe_descricao']);
$classlessPayload['numero_processo'] = $classlessCnj;
$service->create($classlessPayload);
$classless = (new ProcessosRepository($pdo))->findByNumero($classlessCnj);
if ($classless === null || (int) $classless['sigiloso'] !== 1 || $classless['classe_id'] !== null) {
    throw new RuntimeException('Processo novo sem classe não foi marcado sigiloso como esperado.');
}

$explicitPayload = array_merge($base, [
    'numero_processo' => '0000004-24.2020.8.05.0001',
    'nivel_sigilo' => 1,
]);
$service->create($explicitPayload);
$explicit = (new ProcessosRepository($pdo))->findByNumero('0000004-24.2020.8.05.0001');
if ($explicit === null || (int) $explicit['sigiloso'] !== 0 || $pdo->inTransaction()) {
    throw new RuntimeException('Carta alterou sigilo de processo existente ou não confirmou a transação.');
}

// Sucesso com classe ausente deve preservar ambos os valores existentes.
foreach ([[$existingCnj, 1], ['0000004-24.2020.8.05.0001', 0]] as [$cnj, $expectedSigilo]) {
    $beforeLetters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
    $beforeSubtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
    $payload = array_merge($base, ['numero_processo' => $cnj, 'nivel_sigilo' => 1]);
    unset($payload['classe_codigo'], $payload['classe_descricao']);
    $letterId = $service->create($payload);
    $process = (new ProcessosRepository($pdo))->findByNumero($cnj);
    $letter = $pdo->prepare('SELECT processo_origem_id FROM cartas_expedidas WHERE id = ?');
    $letter->execute([$letterId]);
    if ($process === null || (int) $process['sigiloso'] !== $expectedSigilo
        || $letter->fetchColumn() !== $process['id'] || $pdo->inTransaction()
        || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeLetters + 1
        || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeSubtasks + 2) {
        throw new RuntimeException('Sucesso com classe ausente não preservou sigilo e gravações da carta.');
    }
}

// Processo novo continua aceitando o sigilo solicitado, com classe informada.
$newRestrictedCnj = '0000005-09.2020.8.05.0001';
$beforeLetters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$beforeSubtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
$newRestrictedLetter = $service->create(array_merge($base, ['numero_processo' => $newRestrictedCnj, 'nivel_sigilo' => 1]));
$newRestricted = (new ProcessosRepository($pdo))->findByNumero($newRestrictedCnj);
$letter = $pdo->prepare('SELECT processo_origem_id FROM cartas_expedidas WHERE id = ?');
$letter->execute([$newRestrictedLetter]);
if ($newRestricted === null || (int) $newRestricted['sigiloso'] !== 1
    || $newRestricted['classe_id'] !== $expectedClassId || $letter->fetchColumn() !== $newRestricted['id']
    || $pdo->inTransaction() || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeLetters + 1
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeSubtasks + 2) {
    throw new RuntimeException('Processo novo com sigilo solicitado ou carta não foi confirmado.');
}

$matrix = static function (bool $failure) use ($pdo, $service, $base, $existingCnj): void {
    $repository = new ProcessosRepository($pdo);
    foreach ([0, 1] as $initial) foreach ([null, 0, 1] as $level) foreach ([true, false] as $withClass) {
        $pdo->exec("UPDATE processos SET sigiloso = $initial WHERE id = 'PRC-EXISTING'");
        $before = $repository->findByNumero($existingCnj);
        $letters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
        $subtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
        $payload = $base;
        if ($level !== null) { $payload['nivel_sigilo'] = $level; }
        if (!$withClass) { unset($payload['classe_codigo'], $payload['classe_descricao']); }
        try {
            $id = $service->create($payload);
            if ($failure) { throw new RuntimeException('Falha induzida não ocorreu.'); }
        } catch (PDOException $exception) {
            if (!$failure || !str_contains($exception->getMessage(), 'rei88 falha induzida na segunda subtarefa')) { throw $exception; }
        }
        $after = $repository->findByNumero($existingCnj);
        if ($before === null || $after === null || (int) $after['sigiloso'] !== $initial || $pdo->inTransaction()
            || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $letters + ($failure ? 0 : 1)
            || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $subtasks + ($failure ? 0 : 2)
            || ($failure && $before !== $after)) { throw new RuntimeException('Matriz de sigilo, transação ou rollback falhou.'); }
        if (!$failure) {
            $stmt = $pdo->prepare('SELECT processo_origem_id FROM cartas_expedidas WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() !== $after['id']) { throw new RuntimeException('Carta vinculada a processo incorreto.'); }
        }
    }
};
$matrix(false);
$beforeFailureProcess = (new ProcessosRepository($pdo))->findByNumero($existingCnj);
$beforeFailureLetters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$beforeFailureSubtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
$beforeFailureClasses = (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$pdo->exec("CREATE TRIGGER rei88_fail_subtask BEFORE INSERT ON cartas_expedidas_subtarefas FOR EACH ROW BEGIN IF NEW.tipo = 'aguardando_devolucao' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei88 falha induzida na segunda subtarefa'; END IF; END");
$matrix(true);
$failurePayload = array_merge($base, [
    'classe_codigo' => 'REI88-ROLLBACK-CLASS',
    'classe_descricao' => 'Classe sintética para rollback',
    'usuario_cadastro_id' => 'USER-REI78-TEST',
    'idempotency_key' => str_repeat('b', 64),
]);
try {
    $service->create($failurePayload);
    throw new RuntimeException('Falha induzida na subtarefa não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'rei88 falha induzida na segunda subtarefa')) {
        throw $exception;
    }
}
$afterFailureProcess = (new ProcessosRepository($pdo))->findByNumero($existingCnj);
if ($beforeFailureProcess === null || $afterFailureProcess === null
    || $afterFailureProcess['classe_id'] !== $beforeFailureProcess['classe_id']
    || (int) $afterFailureProcess['sigiloso'] !== (int) $beforeFailureProcess['sigiloso']
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeFailureLetters
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeFailureSubtasks
    || (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() !== $beforeFailureClasses
    || (int) $pdo->query("SELECT COUNT(*) FROM cartas_expedidas WHERE idempotency_key = '" . str_repeat('b', 64) . "'")->fetchColumn() !== 0
    || $pdo->inTransaction()) {
    throw new RuntimeException('Falha na criação deixou processo, classe, carta, subtarefas ou transação parcial.');
}
$pdo->exec('DROP TRIGGER rei88_fail_subtask');
$retryAfterRollbackId = $service->create($failurePayload);
if ($service->create($failurePayload) !== $retryAfterRollbackId
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeFailureLetters + 1
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeFailureSubtasks + 2) {
    throw new RuntimeException('Retry após rollback não foi idempotente ou criou registros duplicados.');
}

// Duas conexões independentes exercitam somente a persistência. A operação de
// edição é SQL sintético: não demonstra autorização nem o endpoint de edição.
$raceCode = <<<'PHP'
require getenv('REI88_TEST_REPO_ROOT') . '/app/services/CartasExpedidasService.php';
$db = new PDO(getenv('REI88_MYSQL_DSN'), getenv('REI88_MYSQL_USER'), getenv('REI88_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$guard = $db->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'rei88_test' || !is_string($guard) || !hash_equals((string) getenv('REI88_TEST_DISPOSABLE_MARKER'), $guard)) { throw new RuntimeException('Worker recusado: sentinela ou banco divergentes.'); }
$id = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();
echo "READY $id\n"; flush();
if (trim((string) fgets(STDIN)) !== 'GO') { throw new RuntimeException('Barreira inicial inválida.'); }
$db->beginTransaction();
if (getenv('REI88_RACE_ROLE') === 'editor') {
    $target = getenv('REI88_RACE_TARGET');
    if ($target !== '0' && $target !== '1') { throw new RuntimeException('Valor sintético inválido.'); }
    $update = $db->prepare('UPDATE processos SET sigiloso = ? WHERE id = ?');
    $update->execute([(int) $target, 'PRC-EXPLICIT']);
    if ($update->rowCount() !== 1) { throw new RuntimeException('Edição sintética não alterou o processo.'); }
} elseif (getenv('REI88_RACE_ROLE') === 'carta') {
    $payload = json_decode(base64_decode(getenv('REI88_RACE_PAYLOAD'), true), true, 512, JSON_THROW_ON_ERROR);
    $carta = (new CartasExpedidasService($db))->create($payload);
} else { throw new RuntimeException('Papel inválido.'); }
echo "LOCKED\n"; flush();
if (trim((string) fgets(STDIN)) !== 'COMMIT') { throw new RuntimeException('Barreira de commit inválida.'); }
$db->commit();
echo 'DONE ' . ($carta ?? '-') . "\n"; flush();
PHP;
$racePayload = array_merge($base, ['numero_processo' => '0000004-24.2020.8.05.0001', 'nivel_sigilo' => 1]);
$raceEnv = [
    'REI88_TEST_REPO_ROOT' => $repoRoot, 'REI88_MYSQL_DSN' => $dsn,
    'REI88_MYSQL_USER' => $user, 'REI88_MYSQL_PASSWORD' => $password,
    'REI88_TEST_DISPOSABLE_MARKER' => $expectedMarker,
    'REI88_RACE_PAYLOAD' => base64_encode(json_encode($racePayload, JSON_THROW_ON_ERROR)),
];
$readLine = static function (array &$workers, int $index): string {
    $deadline = hrtime(true) + 20_000_000_000;
    while (hrtime(true) < $deadline) {
        $newline = strpos($workers[$index]['stdout'], "\n");
        if ($newline !== false) {
            $line = substr($workers[$index]['stdout'], 0, $newline);
            $workers[$index]['stdout'] = substr($workers[$index]['stdout'], $newline + 1);
            return trim($line);
        }
        $read = [];
        foreach ($workers as $worker) foreach ([1, 2] as $fd) {
            if (is_resource($worker['pipes'][$fd]) && !feof($worker['pipes'][$fd])) { $read[] = $worker['pipes'][$fd]; }
        }
        if (!$read) { throw new RuntimeException('Workers encerraram antes da barreira.'); }
        $write = null; $except = null;
        if (stream_select($read, $write, $except, 0, 200000) === false) { throw new RuntimeException('Erro ao observar workers.'); }
        foreach ($workers as &$worker) foreach ([1 => 'stdout', 2 => 'stderr'] as $fd => $key) {
            if (!in_array($worker['pipes'][$fd], $read, true)) { continue; }
            $chunk = fread($worker['pipes'][$fd], 8192);
            if ($chunk === false) { throw new RuntimeException('Erro ao ler worker.'); }
            $worker[$key] .= $chunk;
            if (strlen($worker[$key]) > 65536) { throw new RuntimeException('Saída excessiva de worker.'); }
        }
        unset($worker);
    }
    throw new RuntimeException('Timeout aguardando linha completa de worker.');
};
foreach ([0, 1] as $targetSigilo) foreach ([['editor', 'carta'], ['carta', 'editor']] as [$firstRole, $secondRole]) {
    $initialSigilo = 1 - $targetSigilo;
    $pdo->exec("UPDATE processos SET sigiloso = $initialSigilo WHERE id = 'PRC-EXPLICIT'");
    $beforeLetters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
    $beforeSubtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
    $workers = [];
    try {
        foreach ([$firstRole, $secondRole] as $role) {
            $pipes = [];
            $proc = proc_open([PHP_BINARY, '-r', $raceCode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $raceEnv + ['REI88_RACE_ROLE' => $role, 'REI88_RACE_TARGET' => (string) $targetSigilo]);
            if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar worker concorrente.'); }
            stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
            $workers[] = ['proc' => $proc, 'pipes' => $pipes, 'stdout' => '', 'stderr' => ''];
            $ready = $readLine($workers, count($workers) - 1);
            if (!preg_match('/^READY ([0-9]+)$/D', $ready, $matches)) { throw new RuntimeException('Worker sem identidade de conexão.'); }
            $workers[count($workers) - 1]['connection'] = (int) $matches[1];
        }
        if ($workers[0]['connection'] === $workers[1]['connection']) { throw new RuntimeException('Conexões não são independentes.'); }
        fwrite($workers[0]['pipes'][0], "GO\n"); fflush($workers[0]['pipes'][0]);
        if ($readLine($workers, 0) !== 'LOCKED') { throw new RuntimeException('Primeiro worker não concluiu escrita.'); }
        fwrite($workers[1]['pipes'][0], "GO\n"); fflush($workers[1]['pipes'][0]);
        $waitQuery = $pdo->prepare("SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads requesting ON requesting.THREAD_ID = w.REQUESTING_THREAD_ID JOIN performance_schema.threads blocking ON blocking.THREAD_ID = w.BLOCKING_THREAD_ID JOIN performance_schema.data_locks locked ON locked.ENGINE = w.ENGINE AND locked.ENGINE_LOCK_ID = w.BLOCKING_ENGINE_LOCK_ID WHERE requesting.PROCESSLIST_ID = ? AND blocking.PROCESSLIST_ID = ? AND locked.OBJECT_SCHEMA = 'rei88_test' AND locked.OBJECT_NAME = 'processos' AND locked.INDEX_NAME = 'PRIMARY' AND locked.LOCK_TYPE = 'RECORD' AND locked.LOCK_DATA = ?");
        $waitObserved = false;
        for ($attempt = 0; $attempt < 200; $attempt++) {
            foreach ($workers as &$worker) {
                $stderr = fread($worker['pipes'][2], 8192);
                if ($stderr === false) { throw new RuntimeException('Erro ao drenar stderr.'); }
                $worker['stderr'] .= $stderr;
                if (strlen($worker['stderr']) > 65536) { throw new RuntimeException('Stderr excessivo de worker.'); }
            }
            unset($worker);
            $waitQuery->execute([$workers[1]['connection'], $workers[0]['connection'], "'PRC-EXPLICIT'"]);
            if ((int) $waitQuery->fetchColumn() > 0) { $waitObserved = true; break; }
            usleep(25000);
        }
        if (!$waitObserved) { throw new RuntimeException('Espera InnoDB entre as conexões não foi observada.'); }
        fwrite($workers[0]['pipes'][0], "COMMIT\n"); fflush($workers[0]['pipes'][0]);
        if (!str_starts_with($readLine($workers, 0), 'DONE ')) { throw new RuntimeException('Primeiro commit não confirmado.'); }
        if ($readLine($workers, 1) !== 'LOCKED') { throw new RuntimeException('Segundo worker não avançou após commit.'); }
        fwrite($workers[1]['pipes'][0], "COMMIT\n"); fflush($workers[1]['pipes'][0]);
        if (!str_starts_with($readLine($workers, 1), 'DONE ')) { throw new RuntimeException('Segundo commit não confirmado.'); }
        $final = (new ProcessosRepository($pdo))->findByNumero('0000004-24.2020.8.05.0001');
        if ($final === null || (int) $final['sigiloso'] !== $targetSigilo
            || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeLetters + 1
            || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeSubtasks + 2) {
            throw new RuntimeException('Estado final da disputa sintética divergente.');
        }
    } finally {
        $cleanupFailed = false;
        foreach ($workers as $worker) {
            fclose($worker['pipes'][0]);
            $status = proc_get_status($worker['proc']);
            if ($status['running']) { proc_terminate($worker['proc']); }
            $deadline = hrtime(true) + 2_000_000_000;
            do { $status = proc_get_status($worker['proc']); if (!$status['running']) { break; } usleep(20000); } while (hrtime(true) < $deadline);
            if ($status['running']) {
                proc_terminate($worker['proc'], 9);
                $deadline = hrtime(true) + 2_000_000_000;
                do { $status = proc_get_status($worker['proc']); if (!$status['running']) { break; } usleep(20000); } while (hrtime(true) < $deadline);
            }
            foreach ([1, 2] as $fd) { fclose($worker['pipes'][$fd]); }
            if ($status['running']) { $cleanupFailed = true; continue; }
            proc_close($worker['proc']);
        }
        if ($cleanupFailed) { throw new RuntimeException('Encerramento dos workers não confirmado.'); }
    }
}

echo 'PASS CartasExpedidasService completo em MySQL/InnoDB ' . $version
    . ': migração e retry idempotente (concorrência simultânea, payload conflitante e retry após rollback), sigilo e classe existentes preservados (' . count($inputs) . ' entradas), criação completa, processo novo/classe ausente e rollback após falha na segunda subtarefa verificados.' . PHP_EOL;

