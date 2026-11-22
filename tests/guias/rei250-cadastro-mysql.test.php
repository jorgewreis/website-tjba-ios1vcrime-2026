<?php
declare(strict_types=1);

$runId = (string) getenv('REI250_TEST_RUN_ID');
$marker = (string) getenv('REI250_TEST_DISPOSABLE_MARKER');
$dsn = (string) getenv('REI250_MYSQL_DSN');
if (getenv('REI250_TEST_DISPOSABLE') !== '1' || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei250_test;charset=utf8mb4'
    || getenv('REI250_MYSQL_USER') !== 'root') {
    fwrite(STDERR, "FAIL CLOSED: disposable MySQL fixture configuration invalid\n"); exit(90);
}
require_once '/repo/app/services/GuiasService.php';
require_once '/repo/app/helpers/GuiasIdempotenciaMigration.php';
$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$guard = $db->prepare('SELECT COUNT(*) FROM rei250_disposable_guard WHERE run_id=? AND marker=?');
$guard->execute([$runId, $marker]);
if ((string)$db->query('SELECT DATABASE()')->fetchColumn() !== 'rei250_test'
    || !str_starts_with((string)$db->query('SELECT VERSION()')->fetchColumn(), '8.4.')
    || strtoupper((string)$db->query('SELECT @@transaction_isolation')->fetchColumn()) !== 'REPEATABLE-READ'
    || (int)$guard->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL CLOSED: expected guarded MySQL 8.4 REPEATABLE READ database\n"); exit(91);
}
foreach ([
    'CREATE TABLE classes (id VARCHAR(40) PRIMARY KEY, codigo VARCHAR(80), nome VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE partes (id VARCHAR(40) PRIMARY KEY, cpf VARCHAR(20), nome_completo VARCHAR(255), sexo VARCHAR(30), data_nascimento DATE, profissao VARCHAR(120), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE partes_documentos (id VARCHAR(40) PRIMARY KEY, parte_id VARCHAR(40), tipo VARCHAR(20), numero VARCHAR(40), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE processos (id VARCHAR(40) PRIMARY KEY, numero_processo VARCHAR(32), classe_id VARCHAR(40), data_fato DATE, sigiloso TINYINT NOT NULL DEFAULT 0, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE processos_partes (id VARCHAR(40) PRIMARY KEY, processo_id VARCHAR(40), parte_id VARCHAR(40), tipo_parte VARCHAR(40), polo VARCHAR(20), prioridade_mulher TINYINT DEFAULT 0, prioridade_crianca_adolescente TINYINT DEFAULT 0, prioridade_idoso TINYINT DEFAULT 0, prioridade_pessoa_deficiencia TINYINT DEFAULT 0, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE guias (id VARCHAR(40) PRIMARY KEY, numero_processo VARCHAR(32), processo_id VARCHAR(40), denunciado VARCHAR(255), parte_id VARCHAR(40), tipo VARCHAR(100), etapa VARCHAR(100), status VARCHAR(40), regime VARCHAR(40), data_fato DATE, dias_multa INT, valor_multa DECIMAL(10,2), observacoes TEXT, criado_por VARCHAR(40), atualizado_por VARCHAR(40), created_at DATETIME, updated_at DATETIME, user_cadastro VARCHAR(40)) ENGINE=InnoDB',
    'CREATE TABLE guias_subtarefas (id VARCHAR(40) PRIMARY KEY, guia_id VARCHAR(40), tipo VARCHAR(100), ordem INT, status VARCHAR(40), dados_especificos TEXT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE guias_alocados (id VARCHAR(40) PRIMARY KEY, guia_id VARCHAR(40), usuario_id VARCHAR(40), tipo VARCHAR(40), created_at DATETIME, UNIQUE KEY uq_guia_usuario (guia_id, usuario_id)) ENGINE=InnoDB',
    'CREATE TABLE alocacoes_regras (id VARCHAR(40) PRIMARY KEY, regra VARCHAR(100), descricao VARCHAR(255), usuario_id VARCHAR(40), usuario_atualiza_id VARCHAR(40), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB',
    'CREATE TABLE salario_minimo (vigencia_inicio DATE, valor DECIMAL(10,2)) ENGINE=InnoDB',
    'CREATE TABLE usuarios (id_usuario VARCHAR(40) PRIMARY KEY, nome VARCHAR(100), sobrenome VARCHAR(100)) ENGINE=InnoDB',
    'CREATE TABLE usuario_perfil (usuario VARCHAR(40), perfil VARCHAR(50)) ENGINE=InnoDB',
] as $sql) $db->exec($sql);
// The deploy helper must refuse an incompatible pre-existing object, then
// create and recognize the exact valid schema without destructive repair.
$db->exec('CREATE TABLE guia_criacao_idempotencia (id INT PRIMARY KEY) ENGINE=MyISAM');
$migrationRejected = false;
try {
    GuiasIdempotenciaMigration::apply($db, '/test/rei250-migration.sql');
} catch (RuntimeException $e) {
    $migrationRejected = str_contains($e->getMessage(), 'precisa ser InnoDB');
}
if (!$migrationRejected) throw new RuntimeException('Migration aceitou objeto pré-existente incompatível.');
$db->exec('DROP TABLE guia_criacao_idempotencia');
if (!GuiasIdempotenciaMigration::apply($db, '/test/rei250-migration.sql')
    || GuiasIdempotenciaMigration::apply($db, '/test/rei250-migration.sql')) {
    throw new RuntimeException('Migration de guias não criou e validou o schema de forma idempotente.');
}
$db->exec("INSERT INTO usuarios (id_usuario) VALUES ('USR-ACTOR')");
$db->exec("INSERT INTO usuario_perfil (usuario, perfil) VALUES ('USR-ACTOR','ADMIN')");
for ($i = 0; $i < 7; $i++) {
    $target = 'USR-RACE-' . $i;
    $stmt = $db->prepare('INSERT INTO usuarios (id_usuario) VALUES (?)'); $stmt->execute([$target]);
    $stmt = $db->prepare("INSERT INTO usuario_perfil (usuario, perfil) VALUES (?, 'SERVIDOR')"); $stmt->execute([$target]);
}
$reflection = new ReflectionClass(Conection::class);
$singleton = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('pdo')->setValue($singleton, $db);
$reflection->getProperty('instance')->setValue(null, $singleton);
$input = ['numero_processo' => '0000001-69.2020.8.05.0001', 'confirmar_nova_pessoa' => true, 'denunciado' => 'Pessoa Sintetica Tardia', 'classe_codigo' => '01', 'classe_descricao' => 'Classe Sintetica', 'regime' => 'Aberto'];
foreach (['partes','classes','processos','processos_partes','guias'] as $table) {
    $db->exec("CREATE TRIGGER rei250_fail_point BEFORE INSERT ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_POINT'");
    try {
        $failed = false;
        try { (new GuiasService($db))->create($input); }
        catch (PDOException $e) { $failed = str_contains($e->getMessage(), 'REI250_POINT'); }
        if (!$failed || $db->inTransaction()) throw new RuntimeException('Falha de escrita não foi propagada com rollback.');
        foreach (['partes','classes','processos','processos_partes','guias','guias_subtarefas'] as $written) {
            if ((int)$db->query("SELECT COUNT(*) FROM `$written`")->fetchColumn() !== 0) throw new RuntimeException('Ponto de falha deixou dados parciais.');
        }
    } finally { $db->exec('DROP TRIGGER rei250_fail_point'); }
}
$db->exec("CREATE TRIGGER rei250_fail_subtask BEFORE INSERT ON guias_subtarefas FOR EACH ROW BEGIN IF NEW.ordem=4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_FAIL'; END IF; END");
try {
    (new GuiasService($db))->create($input);
    throw new RuntimeException('Falha tardia foi ignorada.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'REI250_FAIL')) throw $e;
}
foreach (['partes','classes','processos','processos_partes','guias','guias_subtarefas'] as $table) {
    if ((int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn() !== 0) throw new RuntimeException('Rollback deixou dados parciais.');
}
if ($db->inTransaction()) throw new RuntimeException('Transação permaneceu aberta.');
$db->exec('DROP TRIGGER rei250_fail_subtask');
$id = (new GuiasService($db))->create($input);
if ($id === '' || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== 1
    || (int)$db->query('SELECT COUNT(*) FROM guias_subtarefas')->fetchColumn() !== 8
    || (int)$db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() !== 1) throw new RuntimeException('Retentativa não gravou conjunto esperado.');
echo "PASS REI-250: rollback MySQL/InnoDB após falha tardia e retentativa.\n";

// Freeze SQL NOW() so an identical UPDATE changes no value and MySQL reports
// rowCount=0. A new allocation must still commit for an existing guide.
$db->exec("SET timestamp = UNIX_TIMESTAMP('2026-01-01 00:00:00')");
$db->exec('UPDATE guias SET updated_at=NOW() WHERE id=' . $db->quote($id));
$same = (new GuiasService($db))->update($id, ['usuario_id' => 'USR-SINTETICO', 'regime' => 'Aberto']);
if (!$same || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Edição idêntica perdeu alocação ou retorno.');
$missing = (new GuiasService($db))->update('GUI-INEXISTENTE', ['usuario_id' => 'USR-OUTRO', 'regime' => 'Aberto']);
if ($missing || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Edição inexistente alocou usuário ou retornou sucesso.');
$sameAgain = (new GuiasService($db))->update($id, ['usuario_id' => 'USR-SINTETICO', 'regime' => 'Aberto']);
if (!$sameAgain || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Retentativa duplicou alocação.');
$empty = (new GuiasService($db))->update($id, ['usuario_id' => 'USR-VAZIO']);
if ($empty || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Payload vazio alterou alocação.');
$db->exec("UPDATE guias SET data_fato='2020-01-01', dias_multa=1, updated_at=NOW() WHERE id=" . $db->quote($id));
$sameDetails = (new GuiasService($db))->update($id, ['data_fato' => '2020-01-01', 'dias_multa' => 1]);
$firstStatus = $db->query('SELECT status FROM guias_subtarefas WHERE guia_id=' . $db->quote($id) . ' AND ordem=1')->fetchColumn();
if (!$sameDetails || $firstStatus !== 'concluida') throw new RuntimeException('Retentativa idêntica não reconciliou detalhamento.');
$db->exec('SET timestamp = DEFAULT');
echo "PASS REI-250: edição idêntica, guia ausente, payload vazio e reconciliação.\n";

// Failure after guide fields and an allocation have been written must restore
// the previous guide, allocation, and task state as one edit transaction.
$db->exec("UPDATE guias_subtarefas SET status='pendente', dados_especificos='antes' WHERE guia_id=" . $db->quote($id) . ' AND ordem=1');
$beforeEdit = $db->query('SELECT regime,data_fato,dias_multa,valor_multa FROM guias WHERE id=' . $db->quote($id))->fetch(PDO::FETCH_ASSOC);
$beforeTask = $db->query('SELECT status,dados_especificos FROM guias_subtarefas WHERE guia_id=' . $db->quote($id) . ' AND ordem=1')->fetch(PDO::FETCH_ASSOC);
$db->exec("CREATE TRIGGER rei250_edit_fail BEFORE UPDATE ON guias_subtarefas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_EDIT_FAIL'");
try {
    $editFailed = false;
    try {
        (new GuiasService($db))->update($id, [
            'usuario_id' => 'USR-EDIT-FAIL',
            'regime' => 'Fechado',
            'data_fato' => '2021-02-03',
            'dias_multa' => 2,
        ]);
    } catch (PDOException $e) {
        $editFailed = str_contains($e->getMessage(), 'REI250_EDIT_FAIL');
    }
    $afterEdit = $db->query('SELECT regime,data_fato,dias_multa,valor_multa FROM guias WHERE id=' . $db->quote($id))->fetch(PDO::FETCH_ASSOC);
    $afterTask = $db->query('SELECT status,dados_especificos FROM guias_subtarefas WHERE guia_id=' . $db->quote($id) . ' AND ordem=1')->fetch(PDO::FETCH_ASSOC);
    $failedAllocation = $db->prepare('SELECT COUNT(*) FROM guias_alocados WHERE guia_id=? AND usuario_id=?');
    $failedAllocation->execute([$id, 'USR-EDIT-FAIL']);
    if (!$editFailed || $db->inTransaction() || $beforeEdit !== $afterEdit || $beforeTask !== $afterTask
        || (int)$failedAllocation->fetchColumn() !== 0) {
        throw new RuntimeException('Falha de edição deixou campos, alocação ou subtarefa parcialmente persistidos.');
    }
} finally {
    $db->exec('DROP TRIGGER rei250_edit_fail');
}
echo "PASS REI-250: falha tardia de edição reverte guia, alocação e subtarefa.\n";

$denied = false;
try { (new GuiasService($db))->alocarUsuarioDirecionado($id, 'USR-RACE-1', 'USR-RACE-0'); }
catch (InvalidArgumentException $e) { $denied = str_contains($e->getMessage(), 'Sem permissão'); }
if (!$denied || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Alocação sem permissão foi aceita.');
$db->exec("CREATE TRIGGER rei250_fail_allocation BEFORE INSERT ON guias_alocados FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_ALOC'");
try {
    $allocationFailed = false;
    try { (new GuiasService($db))->alocarUsuarioDirecionado($id, 'USR-RACE-1', 'USR-ACTOR'); }
    catch (PDOException $e) { $allocationFailed = str_contains($e->getMessage(), 'REI250_ALOC'); }
    if (!$allocationFailed || $db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 1) throw new RuntimeException('Falha de alocação foi ocultada ou persistiu escrita.');
} finally { $db->exec('DROP TRIGGER rei250_fail_allocation'); }
echo "PASS REI-250: autorização e falha de banco em alocação direcionada.\n";

$workerCode = <<<'PHP'
if (getenv('REI250_TEST_DISPOSABLE') !== '1' || !preg_match('/^[a-f0-9]{32}$/', (string)getenv('REI250_TEST_RUN_ID')) || !preg_match('/^[a-f0-9]{64}$/', (string)getenv('REI250_TEST_DISPOSABLE_MARKER'))) exit(90);
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei250_test;charset=utf8mb4';
if (getenv('REI250_MYSQL_DSN') !== $dsn || getenv('REI250_MYSQL_USER') !== 'root') exit(90);
require_once '/repo/app/services/GuiasService.php';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM rei250_disposable_guard WHERE run_id=? AND marker=?');
$stmt->execute([getenv('REI250_TEST_RUN_ID'), getenv('REI250_TEST_DISPOSABLE_MARKER')]);
if ((int)$stmt->fetchColumn() !== 1 || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei250_test') exit(91);
$reflection = new ReflectionClass(Conection::class);
$singleton = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('pdo')->setValue($singleton, $pdo);
$reflection->getProperty('instance')->setValue(null, $singleton);
while (hrtime(true) < (int)getenv('REI250_WORKER_START')) usleep(1000);
try {
    $service = new GuiasService($pdo);
    if (getenv('REI250_WORKER_MODE') === 'directed') {
        $service->alocarUsuarioDirecionado((string)getenv('REI250_WORKER_GUIDE'), (string)getenv('REI250_WORKER_USER'), 'USR-ACTOR');
        $ok = true;
    } else {
        $ok = $service->update((string)getenv('REI250_WORKER_GUIDE'), ['usuario_id' => (string)getenv('REI250_WORKER_USER'), 'regime' => 'Aberto']);
    }
    echo $ok ? 'PASS' : 'FAIL';
} catch (InvalidArgumentException $e) {
    if (str_starts_with($e->getMessage(), 'Limite de alocados por guia atingido')) echo 'PASS';
    else { echo 'FAIL'; exit(1); }
} catch (Throwable $e) { echo 'FAIL'; exit(1); }
PHP;
$workers = [];
$workerStart = hrtime(true) + 700_000_000;
putenv('REI250_WORKER_START=' . $workerStart);
putenv('REI250_WORKER_GUIDE=' . $id);
$db->beginTransaction();
$parentLock = $db->prepare('SELECT id FROM guias WHERE id=? FOR UPDATE');
$parentLock->execute([$id]);
$parentLock->fetchAll();
try {
    for ($i = 0; $i < 7; $i++) {
        putenv('REI250_WORKER_USER=USR-RACE-' . $i);
        putenv('REI250_WORKER_MODE=' . ($i % 2 === 0 ? 'directed' : 'update'));
        $process = proc_open([PHP_BINARY, '-r', $workerCode], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar concorrência sintética.');
        fclose($pipes[0]);
        $workers[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }
    $waitObserved = false;
    $deadline = microtime(true) + 8;
    do {
        if ((int)$db->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits')->fetchColumn() > 0) { $waitObserved = true; break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    if (!$waitObserved) {
        $states = [];
        foreach ($workers as $worker) {
            $status = proc_get_status($worker['process']);
            $states[] = $status['running'] ? 'running' : 'exit-' . (int)$status['exitcode'];
        }
        throw new RuntimeException('Espera concorrente pela guia não foi observada: ' . implode(',', $states));
    }
    $db->commit();
    foreach ($workers as $index => $worker) {
        $output = stream_get_contents($worker['stdout']);
        stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']);
        $code = proc_close($worker['process']);
        $workers[$index]['process'] = null;
        if ($code !== 0 || $output !== 'PASS') throw new RuntimeException('Atualização concorrente sintética falhou.');
    }
} finally {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($workers as $worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            foreach (['stdout','stderr'] as $name) if (is_resource($worker[$name])) fclose($worker[$name]);
            proc_close($worker['process']);
        }
    }
}
$counts = $db->query('SELECT COUNT(*), COUNT(DISTINCT usuario_id) FROM guias_alocados')->fetch(PDO::FETCH_NUM);
if ((int)$counts[0] !== 5 || (int)$counts[1] !== 5) throw new RuntimeException('Concorrência violou limite ou unicidade da fixture: ' . (int)$counts[0] . '/' . (int)$counts[1]);
echo "PASS REI-250: update e alocação direcionada concorrentes respeitam limite e unicidade sintética.\n";

$holder = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$holder->beginTransaction();
$hold = $holder->prepare('SELECT id FROM guias WHERE id=? FOR UPDATE'); $hold->execute([$id]); $hold->fetchAll();
$db->exec('SET SESSION innodb_lock_wait_timeout = 7');
$lockStart = microtime(true); $rowTimeout = false;
try {
    try { (new GuiasService($db))->update($id, ['regime' => 'Aberto']); }
    catch (PDOException $e) { $rowTimeout = (int)($e->errorInfo[1] ?? 0) === 1205; }
    $elapsed = microtime(true) - $lockStart;
    if (!$rowTimeout || $elapsed < 4 || $elapsed > 7 || $db->inTransaction()
        || (int)$db->query('SELECT @@SESSION.innodb_lock_wait_timeout')->fetchColumn() !== 7) {
        throw new RuntimeException('Espera de linha não foi limitada e restaurada.');
    }
} finally { if ($holder->inTransaction()) $holder->rollBack(); }
echo "PASS REI-250: timeout InnoDB de linha limitado e restaurado.\n";

$key = str_repeat('a', 64);
$keyed = $input + ['usuario_cadastro_id' => 'USR-ACTOR', 'idempotency_key' => $key];
$firstKeyService = new GuiasService($db);
$firstId = $firstKeyService->create($keyed);
if ($firstId === '' || $firstKeyService->wasIdempotencyReplay()) throw new RuntimeException('Reserva inicial não criou guia.');
// Simulates a response lost after an acknowledged server-side commit.
$retryService = new GuiasService($db);
if ($retryService->create($keyed) !== $firstId || !$retryService->wasIdempotencyReplay()) throw new RuntimeException('Retentativa não retornou guia confirmada.');
$db->exec("INSERT INTO salario_minimo (vigencia_inicio,valor) VALUES ('2019-01-01', 1000)");
$db->exec("UPDATE guias SET data_fato='2020-01-01', dias_multa=1, valor_multa=NULL WHERE id=" . $db->quote($firstId));
$replayedRow = $retryService->findForCreateResponse($firstId);
$storedValue = $db->query('SELECT valor_multa FROM guias WHERE id=' . $db->quote($firstId))->fetchColumn();
if (!is_array($replayedRow) || $storedValue !== null) throw new RuntimeException('Leitura de replay alterou valor da guia.');
$changed = $keyed; $changed['regime'] = 'Fechado';
$conflict = false;
try { (new GuiasService($db))->create($changed); } catch (DomainException) { $conflict = true; }
if (!$conflict) throw new RuntimeException('Mesma chave aceitou payload diferente.');
$differentKey = $keyed; $differentKey['idempotency_key'] = str_repeat('b', 64);
if ((new GuiasService($db))->create($differentKey) === $firstId) throw new RuntimeException('Nova chave não permitiu duplicata deliberada.');
$differentActor = $keyed; $differentActor['usuario_cadastro_id'] = 'USR-RACE-0';
if ((new GuiasService($db))->create($differentActor) === $firstId) throw new RuntimeException('Chave não foi isolada por ator.');
$expire = $db->prepare('UPDATE guia_criacao_idempotencia SET criado_utc=UTC_TIMESTAMP(6)-INTERVAL 31 DAY WHERE ator_hash=? AND chave=?');
$expire->execute([hash('sha256', 'USR-ACTOR'), $key]);
if ((new GuiasService($db))->create($keyed) === $firstId) throw new RuntimeException('Chave expirada não permitiu novo envio.');
echo "PASS REI-250: retry, conflito, ator, duplicata deliberada e expiração de 30 dias.\n";

$boundaryPayload = $keyed; $boundaryPayload['idempotency_key'] = str_repeat('f', 64);
$boundaryId = (new GuiasService($db))->create($boundaryPayload);
$db->exec('SET timestamp = 1800000000');
try {
    $setBoundary = $db->prepare('UPDATE guia_criacao_idempotencia SET criado_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY+INTERVAL 1 MICROSECOND WHERE ator_hash=? AND chave=?');
    $setBoundary->execute([hash('sha256','USR-ACTOR'), str_repeat('f',64)]);
    $service = new GuiasService($db);
    if ($service->create($boundaryPayload) !== $boundaryId || !$service->wasIdempotencyReplay()) throw new RuntimeException('Chave expirou antes dos 30 dias.');
    $setBoundary = $db->prepare('UPDATE guia_criacao_idempotencia SET criado_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY WHERE ator_hash=? AND chave=?');
    $setBoundary->execute([hash('sha256','USR-ACTOR'), str_repeat('f',64)]);
    $atBoundary = (new GuiasService($db))->create($boundaryPayload);
    if ($atBoundary === $boundaryId) throw new RuntimeException('Chave não expirou no limite exato.');
    $setBoundary = $db->prepare('UPDATE guia_criacao_idempotencia SET criado_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY-INTERVAL 1 MICROSECOND WHERE ator_hash=? AND chave=?');
    $setBoundary->execute([hash('sha256','USR-ACTOR'), str_repeat('f',64)]);
    if ((new GuiasService($db))->create($boundaryPayload) === $atBoundary) throw new RuntimeException('Chave expirada continuou bloqueada.');
} finally { $db->exec('SET timestamp = DEFAULT'); }
echo "PASS REI-250: antes, exatamente e após 30 dias (relógio MySQL congelado).\n";

$createWorker = <<<'PHP'
if (getenv('REI250_TEST_DISPOSABLE') !== '1' || !preg_match('/^[a-f0-9]{32}$/', (string)getenv('REI250_TEST_RUN_ID')) || !preg_match('/^[a-f0-9]{64}$/', (string)getenv('REI250_TEST_DISPOSABLE_MARKER'))) exit(90);
$dsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei250_test;charset=utf8mb4';
if (getenv('REI250_MYSQL_DSN') !== $dsn || getenv('REI250_MYSQL_USER') !== 'root') exit(90);
require_once '/repo/app/services/GuiasService.php';
$pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$clock = (string)getenv('REI250_CREATE_MYSQL_TIME');
if ($clock !== '') {
    if (!preg_match('/^[0-9]{10}$/D', $clock)) exit(90);
    $pdo->exec('SET timestamp = ' . $clock);
}
$stmt = $pdo->prepare('SELECT COUNT(*) FROM rei250_disposable_guard WHERE run_id=? AND marker=?');
$stmt->execute([getenv('REI250_TEST_RUN_ID'), getenv('REI250_TEST_DISPOSABLE_MARKER')]);
if ((int)$stmt->fetchColumn() !== 1 || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei250_test') exit(91);
$reflection = new ReflectionClass(Conection::class); $singleton = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('pdo')->setValue($singleton, $pdo); $reflection->getProperty('instance')->setValue(null, $singleton);
$payload = json_decode(base64_decode((string)getenv('REI250_CREATE_PAYLOAD')), true, flags: JSON_THROW_ON_ERROR);
while (hrtime(true) < (int)getenv('REI250_CREATE_START')) usleep(1000);
try { echo (new GuiasService($pdo))->create($payload); } catch (Throwable $e) { echo $e instanceof PDOException ? 'FAIL-PDO-' . (int)($e->errorInfo[1] ?? 0) : 'FAIL-' . get_class($e); exit(1); }
PHP;
$concurrentPayload = $keyed; $concurrentPayload['idempotency_key'] = str_repeat('c', 64);
putenv('REI250_CREATE_PAYLOAD=' . base64_encode(json_encode($concurrentPayload, JSON_THROW_ON_ERROR)));
putenv('REI250_CREATE_START=' . (hrtime(true) + 700_000_000));
$beforeCount = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
$db->exec('CREATE TRIGGER rei250_slow_create BEFORE INSERT ON guias FOR EACH ROW DO SLEEP(2)');
$createWorkers = [];
try {
    for ($i = 0; $i < 5; $i++) {
        $process = proc_open([PHP_BINARY, '-r', $createWorker], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar criação concorrente.');
        fclose($pipes[0]);
        $createWorkers[] = ['process'=>$process, 'stdout'=>$pipes[1], 'stderr'=>$pipes[2]];
    }
    $waitObserved = false; $deadline = microtime(true) + 8;
    do {
        if ((int)$db->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits')->fetchColumn() > 0) { $waitObserved = true; break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    if (!$waitObserved) throw new RuntimeException('Espera concorrente pela chave não observada.');
    $ids = [];
    foreach ($createWorkers as $index => $worker) {
        $out = stream_get_contents($worker['stdout']); stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']);
        $code = proc_close($worker['process']); $createWorkers[$index]['process'] = null;
        if ($code !== 0 || !preg_match('/^GUI-[A-Z0-9]{26}$/D', $out)) throw new RuntimeException('Envio concorrente falhou: ' . (preg_match('/^FAIL-[A-Za-z0-9-]+$/D', $out) ? $out : 'resultado inválido'));
        $ids[] = $out;
    }
    if (count(array_unique($ids)) !== 1 || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $beforeCount + 1) throw new RuntimeException('Chave concorrente produziu duplicatas.');
} finally {
    foreach ($createWorkers as $worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            foreach (['stdout','stderr'] as $name) if (is_resource($worker[$name])) fclose($worker[$name]);
            proc_close($worker['process']);
        }
    }
    $db->exec('DROP TRIGGER rei250_slow_create');
}
echo "PASS REI-250: cinco envios concorrentes com mesma chave retornam uma guia.\n";

$expiredPayload = $keyed; $expiredPayload['idempotency_key'] = str_repeat('9', 64);
$oldId = (new GuiasService($db))->create($expiredPayload);
$expire->execute([hash('sha256','USR-ACTOR'), str_repeat('9',64)]);
putenv('REI250_CREATE_PAYLOAD=' . base64_encode(json_encode($expiredPayload, JSON_THROW_ON_ERROR)));
putenv('REI250_CREATE_START=' . (hrtime(true) + 700_000_000));
$beforeExpired = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
$db->exec('CREATE TRIGGER rei250_slow_expired BEFORE INSERT ON guias FOR EACH ROW DO SLEEP(2)');
$expiredWorkers = [];
try {
    for ($i = 0; $i < 5; $i++) {
        $process = proc_open([PHP_BINARY, '-r', $createWorker], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar reuso concorrente.');
        fclose($pipes[0]);
        $expiredWorkers[] = ['process'=>$process, 'stdout'=>$pipes[1], 'stderr'=>$pipes[2]];
    }
    $waitObserved = false; $deadline = microtime(true) + 8;
    do {
        if ((int)$db->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits')->fetchColumn() > 0) { $waitObserved = true; break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    if (!$waitObserved) throw new RuntimeException('Espera concorrente pelo reuso expirado não observada.');
    $ids = [];
    foreach ($expiredWorkers as $index => $worker) {
        $out = stream_get_contents($worker['stdout']); stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']);
        $code = proc_close($worker['process']); $expiredWorkers[$index]['process'] = null;
        if ($code !== 0 || !preg_match('/^GUI-[A-Z0-9]{26}$/D', $out)) throw new RuntimeException('Reuso concorrente falhou: ' . (preg_match('/^FAIL-[A-Za-z0-9-]+$/D', $out) ? $out : 'resultado inválido'));
        $ids[] = $out;
    }
    if (count(array_unique($ids)) !== 1 || $ids[0] === $oldId || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $beforeExpired + 1) throw new RuntimeException('Reuso expirado produziu resultado incorreto.');
} finally {
    foreach ($expiredWorkers as $worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            foreach (['stdout','stderr'] as $name) if (is_resource($worker[$name])) fclose($worker[$name]);
            proc_close($worker['process']);
        }
    }
    $db->exec('DROP TRIGGER rei250_slow_expired');
}
echo "PASS REI-250: cinco reusos concorrentes de chave expirada criam uma nova guia.\n";

$crossingPayload = $keyed; $crossingPayload['idempotency_key'] = str_repeat('8', 64);
$crossingOld = (new GuiasService($db))->create($crossingPayload);
$db->exec('SET timestamp = 1800000000');
$crossingSet = $db->prepare('UPDATE guia_criacao_idempotencia SET criado_utc=UTC_TIMESTAMP(6)-INTERVAL 30 DAY+INTERVAL 1 SECOND WHERE ator_hash=? AND chave=?');
$crossingSet->execute([hash('sha256','USR-ACTOR'), str_repeat('8',64)]);
$db->exec('SET timestamp = DEFAULT');
$countBeforeCrossing = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
putenv('REI250_CREATE_PAYLOAD=' . base64_encode(json_encode($crossingPayload, JSON_THROW_ON_ERROR)));
putenv('REI250_CREATE_START=' . (hrtime(true) + 700_000_000));
$db->beginTransaction();
$crossingLock = $db->prepare('SELECT guia_id FROM guia_criacao_idempotencia WHERE ator_hash=? AND chave=? FOR UPDATE');
$crossingLock->execute([hash('sha256','USR-ACTOR'), str_repeat('8',64)]); $crossingLock->fetchAll();
$crossingWorkers = [];
try {
    foreach (['1800000000','1800000002'] as $time) {
        putenv('REI250_CREATE_MYSQL_TIME=' . $time);
        $process = proc_open([PHP_BINARY, '-r', $createWorker], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar corrida de vencimento.');
        fclose($pipes[0]);
        $crossingWorkers[] = ['process'=>$process, 'stdout'=>$pipes[1], 'stderr'=>$pipes[2]];
    }
    $waitObserved = false; $deadline = microtime(true) + 8;
    do {
        if ((int)$db->query('SELECT COUNT(*) FROM performance_schema.data_lock_waits')->fetchColumn() >= 2) { $waitObserved = true; break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    if (!$waitObserved) throw new RuntimeException('Duas esperas na fronteira não foram observadas.');
    $db->commit();
    $ids = [];
    foreach ($crossingWorkers as $index => $worker) {
        $out = stream_get_contents($worker['stdout']); stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']);
        $code = proc_close($worker['process']); $crossingWorkers[$index]['process'] = null;
        if ($code !== 0 || !preg_match('/^GUI-[A-Z0-9]{26}$/D', $out)) throw new RuntimeException('Corrida de vencimento falhou: ' . (preg_match('/^FAIL-[A-Za-z0-9-]+$/D', $out) ? $out : 'resultado inválido'));
        $ids[] = $out;
    }
    // The pre-expiry request may observe the old or newly reserved ID,
    // depending on lock order; post-expiry must produce the new generation.
    if ($ids[1] === $crossingOld || ($ids[0] !== $crossingOld && $ids[0] !== $ids[1])
        || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $countBeforeCrossing + 1) throw new RuntimeException('Fronteira concorrente produziu geração incorreta.');
} finally {
    putenv('REI250_CREATE_MYSQL_TIME');
    if ($db->inTransaction()) $db->rollBack();
    foreach ($crossingWorkers as $worker) {
        if (is_resource($worker['process'] ?? null)) {
            proc_terminate($worker['process']);
            foreach (['stdout','stderr'] as $name) if (is_resource($worker[$name])) fclose($worker[$name]);
            proc_close($worker['process']);
        }
    }
}
echo "PASS REI-250: transações antes/depois do vencimento criam no máximo uma nova geração.\n";

$retryKeyPayload = $keyed; $retryKeyPayload['idempotency_key'] = str_repeat('d', 64);
$ledgerCount = (int)$db->query('SELECT COUNT(*) FROM guia_criacao_idempotencia')->fetchColumn();
$guideCount = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
$db->exec("CREATE TRIGGER rei250_idempotency_fail BEFORE INSERT ON guias_subtarefas FOR EACH ROW BEGIN IF NEW.ordem=4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_RETRY'; END IF; END");
try {
    $failed = false;
    try { (new GuiasService($db))->create($retryKeyPayload); }
    catch (PDOException $e) { $failed = str_contains($e->getMessage(), 'REI250_RETRY'); }
    if (!$failed || (int)$db->query('SELECT COUNT(*) FROM guia_criacao_idempotencia')->fetchColumn() !== $ledgerCount
        || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $guideCount) throw new RuntimeException('Falha parcial deixou reserva ou guia.');
} finally { $db->exec('DROP TRIGGER rei250_idempotency_fail'); }
$afterFailure = (new GuiasService($db))->create($retryKeyPayload);
if ($afterFailure === '' || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $guideCount + 1) throw new RuntimeException('Retentativa da chave após rollback falhou.');
echo "PASS REI-250: reserva e guia revertem juntas; retry após falha cria uma guia.\n";

$unsafePayload = $keyed; $unsafePayload['idempotency_key'] = str_repeat('e', 64);
$db->exec('ALTER TABLE guia_criacao_idempotencia DROP PRIMARY KEY');
try {
    $rejected = false;
    try { (new GuiasService($db))->create($unsafePayload); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected || $db->inTransaction()) throw new RuntimeException('Schema sem chave única não foi recusado.');
} finally { $db->exec('ALTER TABLE guia_criacao_idempotencia ADD PRIMARY KEY (ator_hash,chave)'); }
echo "PASS REI-250: schema idempotente sem chave primária é recusado.\n";

$db->exec('ALTER TABLE guia_criacao_idempotencia ADD UNIQUE KEY rei250_extra_unique (guia_id)');
try {
    $rejected = false;
    try { (new GuiasService($db))->create($unsafePayload); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected || $db->inTransaction()) throw new RuntimeException('UNIQUE adicional não foi recusado.');
} finally { $db->exec('ALTER TABLE guia_criacao_idempotencia DROP INDEX rei250_extra_unique'); }
$db->exec('CREATE TRIGGER rei250_ledger_trigger BEFORE UPDATE ON guia_criacao_idempotencia FOR EACH ROW SET NEW.guia_id=NEW.guia_id');
try {
    $rejected = false;
    try { (new GuiasService($db))->create($unsafePayload); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected || $db->inTransaction()) throw new RuntimeException('Trigger inesperado não foi recusado.');
} finally { $db->exec('DROP TRIGGER rei250_ledger_trigger'); }
$setVersion = $db->prepare('UPDATE guia_criacao_idempotencia SET hash_versao=? WHERE ator_hash=? AND chave=?');
$setVersion->execute([2, hash('sha256','USR-ACTOR'), str_repeat('b',64)]);
try {
    $rejected = false;
    try { (new GuiasService($db))->create($differentKey); } catch (RuntimeException) { $rejected = true; }
    if (!$rejected || $db->inTransaction()) throw new RuntimeException('Versão desconhecida não foi recusada.');
} finally { $setVersion->execute([1, hash('sha256','USR-ACTOR'), str_repeat('b',64)]); }
echo "PASS REI-250: UNIQUE extra, trigger e versão de hash desconhecida recusados.\n";

$db->exec("CREATE USER 'rei250_fixture'@'localhost' IDENTIFIED BY ''");
try {
    $db->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON rei250_test.* TO 'rei250_fixture'@'localhost'");
    $restricted = new PDO($dsn, 'rei250_fixture', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $restricted->beginTransaction(); $rejected = false;
    try { GuiasCadastroTransactionalGuard::requireTransactionalTables($restricted, true); }
    catch (RuntimeException $e) { $rejected = $e->getMessage() === 'Privilégio para inspecionar triggers não comprovado.'; }
    finally { if ($restricted->inTransaction()) $restricted->rollBack(); }
    if (!$rejected) throw new RuntimeException('Usuário sem privilégio de trigger não foi recusado.');
    $db->exec("GRANT TRIGGER ON rei250_test.guia_criacao_idempotencia TO 'rei250_fixture'@'localhost'");
    $restricted = new PDO($dsn, 'rei250_fixture', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $restricted->beginTransaction();
    try { GuiasCadastroTransactionalGuard::requireTransactionalTables($restricted, true); }
    finally { if ($restricted->inTransaction()) $restricted->rollBack(); }
} finally { $db->exec("DROP USER 'rei250_fixture'@'localhost'"); }
echo "PASS REI-250: inspeção de triggers exige privilégio comprovável.\n";

$existingGuides = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
foreach (['classes','guias','guias_alocados','guias_subtarefas','partes','processos','processos_partes'] as $table) {
    $db->exec("ALTER TABLE `$table` ENGINE=MyISAM");
    try {
        $rejected = false;
        try { (new GuiasService($db))->create($input); }
        catch (RuntimeException $e) { $rejected = $e->getMessage() === 'Operação de guia requer tabelas InnoDB.'; }
        if (!$rejected || $db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $existingGuides) {
            throw new RuntimeException('Tabela não transacional não foi recusada antes das escritas.');
        }
    } finally {
        $db->exec("ALTER TABLE `$table` ENGINE=InnoDB");
    }
}
echo "PASS REI-250: sete tabelas diretamente gravadas recusam MyISAM.\n";

// Identidades são sintéticas; nenhuma constraint de unicidade é presumida.
$db->exec("INSERT INTO partes (id,nome_completo) VALUES ('PRT-HOMONIMO-A','Pessoa Homonima'),('PRT-HOMONIMO-B','Pessoa Homonima')");
$identity = $input;
unset($identity['confirmar_nova_pessoa']);
$identity['denunciado'] = 'Pessoa Homonima';
try { (new GuiasService($db))->create($identity); throw new RuntimeException('Cadastro sem confirmação foi aceito.'); }
catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), 'confirme')) throw $e; }
$identity['reu_id'] = 'PRT-INEXISTENTE';
try { (new GuiasService($db))->create($identity); throw new RuntimeException('ID inexistente foi aceito.'); }
catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), 'não encontrada')) throw $e; }
$identity['reu_id'] = 'PRT-HOMONIMO-B';
$identity['denunciado'] = 'Nome Divergente';
$guide = (new GuiasService($db))->create($identity);
$stmt = $db->prepare('SELECT parte_id, denunciado FROM guias WHERE id=?'); $stmt->execute([$guide]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
if ($row['parte_id'] !== 'PRT-HOMONIMO-B' || $row['denunciado'] !== 'Pessoa Homonima') throw new RuntimeException('Seleção de homônimo não respeitada.');
unset($identity['reu_id']); $identity['denunciado'] = 'Pessoa Homonima'; $identity['confirmar_nova_pessoa'] = true;
$guide = (new GuiasService($db))->create($identity);
$stmt->execute([$guide]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
if (in_array($row['parte_id'], ['PRT-HOMONIMO-A','PRT-HOMONIMO-B'], true)) throw new RuntimeException('Pessoa nova foi fundida por nome.');
$db->exec("INSERT INTO partes_documentos (id,parte_id,tipo,numero) VALUES ('DOC-AMB-A','PRT-HOMONIMO-A','CPF','52998224725'),('DOC-AMB-B','PRT-HOMONIMO-B','CPF','52998224725')");
$identity['cpf'] = '52998224725';
try { (new GuiasService($db))->create($identity); throw new RuntimeException('CPF ambíguo aceito.'); }
catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), 'mais de uma pessoa')) throw $e; }
$db->exec("DELETE FROM partes_documentos WHERE id IN ('DOC-AMB-A','DOC-AMB-B')");
$db->exec("CREATE TRIGGER rei250_doc_fail BEFORE INSERT ON partes_documentos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='REI250_DOC_FAIL'");
$count=(int)$db->query('SELECT COUNT(*) FROM partes')->fetchColumn();
try {
    try { (new GuiasService($db))->create($identity); throw new RuntimeException('Falha de documento ignorada.'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Persistência do documento não confirmada.') throw $e; }
    if ((int)$db->query('SELECT COUNT(*) FROM partes')->fetchColumn() !== $count) throw new RuntimeException('Falha do documento não reverteu a parte.');
} finally { $db->exec('DROP TRIGGER rei250_doc_fail'); }
echo "PASS REI-250: escolha de homônimos, CPF ambíguo e rollback de documento.\n";

// Ambos os escritores cooperantes tentam o mesmo CPF com chaves distintas.
$lockName = 'rei250_' . substr(hash('sha256', 'rei250_test:52998224725'), 0, 52);
$lock = $db->prepare('SELECT GET_LOCK(?, 5)'); $lock->execute([$lockName]);
if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Fixture não obteve trava CPF.');
$workers = []; $countBefore = (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn();
try {
    putenv('REI250_CREATE_START=' . (hrtime(true) + 700_000_000));
    for ($i=0; $i<2; $i++) {
        $cpfPayload = $keyed; $cpfPayload['cpf']='52998224725';
        $cpfPayload['idempotency_key']=str_repeat($i === 0 ? '1' : '2', 64);
        putenv('REI250_CREATE_PAYLOAD=' . base64_encode(json_encode($cpfPayload, JSON_THROW_ON_ERROR)));
        $process = proc_open([PHP_BINARY, '-r', $createWorker], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar corrida CPF.');
        fclose($pipes[0]); $workers[]=['process'=>$process,'stdout'=>$pipes[1],'stderr'=>$pipes[2]];
    }
    usleep(1300000);
    if ((int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== $countBefore) throw new RuntimeException('Criação ultrapassou trava CPF externa.');
    $release=$db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
    if ((int)$release->fetchColumn() !== 1) throw new RuntimeException('Fixture não liberou trava CPF.');
    $ids=[];
    foreach ($workers as $index=>$worker) {
        $out=stream_get_contents($worker['stdout']); stream_get_contents($worker['stderr']);
        fclose($worker['stdout']); fclose($worker['stderr']); $code=proc_close($worker['process']); $workers[$index]['process']=null;
        if ($code !== 0 || !preg_match('/^GUI-[A-Z0-9]{26}$/D', $out)) throw new RuntimeException('Corrida CPF falhou: ' . $out);
        $ids[]=$out;
    }
    $query=$db->prepare('SELECT COUNT(DISTINCT parte_id) FROM guias WHERE id IN (?, ?)'); $query->execute($ids);
    if ($ids[0] === $ids[1] || (int)$query->fetchColumn() !== 1 || (int)$db->query("SELECT COUNT(*) FROM partes_documentos WHERE numero='52998224725'")->fetchColumn() !== 1) throw new RuntimeException('Concorrência CPF criou identidades duplicadas.');
    $check=$db->prepare('SELECT IS_FREE_LOCK(?)'); $check->execute([$lockName]);
    if ((int)$check->fetchColumn() !== 1) throw new RuntimeException('Trava CPF não foi liberada após sucesso.');
} finally {
    $release=$db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
    foreach ($workers as $worker) if (is_resource($worker['process'] ?? null)) { proc_terminate($worker['process']); foreach (['stdout','stderr'] as $k) if (is_resource($worker[$k])) fclose($worker[$k]); proc_close($worker['process']); }
}
echo "PASS REI-250: duas criações cooperantes com mesmo CPF e chaves distintas usam a mesma pessoa.\n";
