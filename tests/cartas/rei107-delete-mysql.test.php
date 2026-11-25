<?php
declare(strict_types=1);

$runId = (string) getenv('REI107_TEST_RUN_ID');
$marker = (string) getenv('REI107_TEST_MARKER');
$dsn = (string) getenv('REI107_MYSQL_DSN');
if (getenv('REI107_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei107_test;charset=utf8mb4'
    || getenv('REI107_MYSQL_USER') !== 'root') {
    fwrite(STDERR, "FAIL CLOSED: disposable MySQL fixture configuration invalid\n");
    exit(90);
}

require_once '/repo/app/services/CartasExpedidasService.php';
require_once '/repo/app/services/CartasRecebidasService.php';
require_once '/repo/app/services/EditaisService.php';
require_once '/repo/app/services/GuiasService.php';

$db = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$guard = $db->prepare('SELECT COUNT(*) FROM rei107_disposable_guard WHERE run_id=:run_id AND marker=:marker');
$guard->execute([':run_id' => $runId, ':marker' => $marker]);
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei107_test'
    || !str_starts_with((string) $db->query('SELECT VERSION()')->fetchColumn(), '8.4.')
    || (int) $guard->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL CLOSED: expected guarded disposable MySQL 8.4 database\n");
    exit(91);
}
$db->exec('CREATE TABLE rei107_worker_runs (slot CHAR(32) NOT NULL PRIMARY KEY, status VARCHAR(16) NOT NULL, connection_id BIGINT NULL, message TEXT NULL) ENGINE=InnoDB');

$modules = [
    'expedidas' => [CartasExpedidasService::class, 'cartas_expedidas', 'cartas_expedidas_subtarefas', 'carta_expedida_id', 'cartas_expedidas_alocados'],
    'recebidas' => [CartasRecebidasService::class, 'cartas_recebidas', 'cartas_recebidas_subtarefas', 'carta_recebida_id', 'cartas_recebidas_alocados'],
    'editais' => [EditaisService::class, 'editais', 'editais_subtarefas', 'edital_id', 'editais_alocados'],
    'guias' => [GuiasService::class, 'guias', 'guias_subtarefas', 'guia_id', 'guias_alocados'],
];
$races = [
    'expedidas' => [CartasExpedidasRepository::class, CartasExpedidasSubtarefasRepository::class, CartasExpedidasAlocadosRepository::class, CartasExpedidasSubtarefasRepository::TIPO_ENVIO, CartasExpedidasSubtarefasRepository::ORDEM_ENVIO],
    'recebidas' => [CartasRecebidasRepository::class, CartasRecebidasSubtarefasRepository::class, CartasRecebidasAlocadosRepository::class, CartasRecebidasSubtarefasRepository::TIPO_CUMPRIMENTO, CartasRecebidasSubtarefasRepository::ORDEM_CUMPRIMENTO],
    'editais' => [EditaisRepository::class, EditaisSubtarefasRepository::class, EditaisAlocadosRepository::class, EditaisSubtarefasRepository::TIPO_PUBLICACAO, EditaisSubtarefasRepository::ORDEM_PUBLICACAO],
    'guias' => [GuiasRepository::class, GuiasSubtarefasRepository::class, GuiasAlocadosRepository::class, GuiasSubtarefasRepository::TIPO_ORDEM_1, 1],
];

foreach ($modules as [, $parent, $child, $foreignKey, $allocation]) {
    $db->exec("CREATE TABLE `$parent` (id VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `$child` (id VARCHAR(64) NOT NULL PRIMARY KEY, `$foreignKey` VARCHAR(64) NOT NULL, tipo VARCHAR(64) NOT NULL, ordem INT NOT NULL DEFAULT 1, status VARCHAR(32) NULL, dados_especificos JSON NULL, created_at DATETIME NULL, updated_at DATETIME NULL, KEY ix_parent (`$foreignKey`)) ENGINE=InnoDB");
    $db->exec("CREATE TABLE `$allocation` (id VARCHAR(64) NOT NULL PRIMARY KEY, `$foreignKey` VARCHAR(64) NOT NULL, usuario_id VARCHAR(64) NULL, tipo VARCHAR(32) NULL, created_at DATETIME NULL, KEY ix_parent (`$foreignKey`)) ENGINE=InnoDB");
}

$clear = static function (PDO $pdo) use ($modules): void {
    foreach ($modules as [, $parent, $child, , $allocation]) {
        $pdo->exec("DROP TRIGGER IF EXISTS `rei107_fail_{$parent}`");
        $pdo->exec("DROP TRIGGER IF EXISTS `rei107_fail_{$child}`");
        $pdo->exec("DROP TRIGGER IF EXISTS `rei107_fail_{$allocation}`");
        $pdo->exec("DELETE FROM `$allocation`");
        $pdo->exec("DELETE FROM `$child`");
        $pdo->exec("DELETE FROM `$parent`");
    }
};
$seed = static function (PDO $pdo) use ($modules): void {
    foreach ($modules as $key => [, $parent, $child, $foreignKey, $allocation]) {
        $pdo->exec("INSERT INTO `$parent` (id) VALUES ('TARGET'), ('OTHER')");
        $insertChild = $pdo->prepare("INSERT INTO `$child` (id, `$foreignKey`, tipo, ordem, status) VALUES (:id, :parent, 'sintetica', 1, 'pendente')");
        $insertAlloc = $pdo->prepare("INSERT INTO `$allocation` (id, `$foreignKey`) VALUES (:id, :parent)");
        foreach ([['TARGET-'.$key, 'TARGET'], ['ORPHAN-'.$key, 'MISSING'], ['OTHER-'.$key, 'OTHER']] as [$id, $parentId]) {
            $insertChild->execute([':id' => $id, ':parent' => $parentId]);
            $insertAlloc->execute([':id' => $id, ':parent' => $parentId]);
        }
    }
};
$snapshot = static function (PDO $pdo) use ($modules): string {
    $rows = [];
    foreach ($modules as $key => [, $parent, $child, , $allocation]) {
        foreach ([$parent, $child, $allocation] as $table) {
            $rows[$key . '.' . $table] = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    return json_encode($rows, JSON_THROW_ON_ERROR);
};
$assertCount = static function (PDO $pdo, string $table, string $id, int $expected): void {
    $statement = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE id=?");
    $statement->execute([$id]);
    if ((int) $statement->fetchColumn() !== $expected) throw new RuntimeException("Estado inesperado em $table para $id");
};

foreach ($modules as $key => [$service, $parent, $child, , $allocation]) {
    $clear($db);
    $seed($db);
    $result = (new $service($db))->delete('TARGET');
    if (($result['deleted'] ?? null) !== true
        || ($result['subtarefas_orfas_removidas'] ?? null) !== 0
        || count($result['subtarefas_toasts'] ?? []) !== 1) {
        throw new RuntimeException("Contrato de sucesso incorreto em $service");
    }
    $assertCount($db, $parent, 'TARGET', 0);
    $assertCount($db, $child, 'TARGET-'.$key, 0);
    $assertCount($db, $allocation, 'TARGET-'.$key, 0);
    foreach ($modules as $otherKey => [, $otherParent, $otherChild, , $otherAllocation]) {
        $assertCount($db, $otherChild, 'ORPHAN-'.$otherKey, 1);
        $assertCount($db, $otherAllocation, 'ORPHAN-'.$otherKey, 1);
        $assertCount($db, $otherParent, 'OTHER', 1);
        $assertCount($db, $otherChild, 'OTHER-'.$otherKey, 1);
        $assertCount($db, $otherAllocation, 'OTHER-'.$otherKey, 1);
        if ($otherKey !== $key) {
            $assertCount($db, $otherParent, 'TARGET', 1);
            $assertCount($db, $otherChild, 'TARGET-'.$otherKey, 1);
            $assertCount($db, $otherAllocation, 'TARGET-'.$otherKey, 1);
        }
    }

    // A missing parent must not authorize deletion of an orphan sharing its ID.
    $result = (new $service($db))->delete('MISSING');
    if (($result['deleted'] ?? null) !== false) throw new RuntimeException("Registro inexistente aceito em $service");
    $assertCount($db, $child, 'ORPHAN-'.$key, 1);
    $assertCount($db, $allocation, 'ORPHAN-'.$key, 1);

    // Each mutating stage can fail without leaving a partially deleted record.
    foreach ([$child, $allocation, $parent] as $failTable) {
        $clear($db);
        $seed($db);
        $before = $snapshot($db);
        $trigger = 'rei107_fail_' . $failTable;
        $db->exec("CREATE TRIGGER `$trigger` BEFORE DELETE ON `$failTable` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic delete failure'");
        try {
            (new $service($db))->delete('TARGET');
            throw new RuntimeException("Falha não propagada em $service / $failTable");
        } catch (PDOException $exception) {
            if (!str_contains($exception->getMessage(), 'synthetic delete failure')) throw $exception;
        }
        if ($snapshot($db) !== $before || $db->inTransaction()) {
            throw new RuntimeException("Rollback/integridade falhou em $service / $failTable");
        }
    }

    $clear($db);
    $seed($db);
    $db->beginTransaction();
    try {
        (new $service($db))->delete('TARGET');
        throw new RuntimeException("Transação externa aceita em $service");
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'transação anterior')) throw $exception;
    }
    $db->rollBack();
    $assertCount($db, $parent, 'TARGET', 1);
}

$clearRace = static function (PDO $pdo) use ($modules): void {
    foreach ($modules as [, $parent, $child, $foreignKey, $allocation]) {
        $pdo->exec("DELETE FROM `$allocation` WHERE `$foreignKey`='RACE'");
        $pdo->exec("DELETE FROM `$child` WHERE `$foreignKey`='RACE'");
        $pdo->exec("DELETE FROM `$parent` WHERE id='RACE'");
    }
    $pdo->exec('DELETE FROM rei107_worker_runs');
};
$launchWorker = static function (string $mode, string $module, string $parentId, string $slot): array {
    $command = [PHP_BINARY, '/test/rei107-delete-mysql-worker.php', $mode, $module, $slot, $parentId];
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar worker MySQL descartável.');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    return ['process' => $process, 'pipes' => $pipes, 'slot' => $slot];
};
$workerState = static function (PDO $pdo, string $slot): ?array {
    $statement = $pdo->prepare('SELECT status, connection_id, message FROM rei107_worker_runs WHERE slot=:slot');
    $statement->execute([':slot' => $slot]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
};
$waitForLock = static function (array $worker, int $blockingConnection, string $parentTable) use ($db, $workerState): int {
    $deadline = microtime(true) + 10;
    while (microtime(true) < $deadline) {
        $state = $workerState($db, $worker['slot']);
        $wait = $db->prepare('SELECT r.PROCESSLIST_ID FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID JOIN performance_schema.data_locks rl ON rl.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID JOIN performance_schema.data_locks bl ON bl.ENGINE_LOCK_ID=w.BLOCKING_ENGINE_LOCK_ID WHERE b.PROCESSLIST_ID=:blocker AND rl.OBJECT_SCHEMA=DATABASE() AND rl.OBJECT_NAME=:request_table AND bl.OBJECT_SCHEMA=DATABASE() AND bl.OBJECT_NAME=:block_table LIMIT 1');
        $wait->execute([':blocker' => $blockingConnection, ':request_table' => $parentTable, ':block_table' => $parentTable]);
        $requester = $wait->fetchColumn();
        if ($requester !== false) {
            if ($state === null || (int) $state['connection_id'] !== (int) $requester) {
                throw new RuntimeException('Lock wait InnoDB não pertence à conexão do worker esperado.');
            }
            return (int) $requester;
        }
        if ($state !== null) {
            if (!in_array($state['status'], ['starting', 'running'], true)) {
                throw new RuntimeException('Worker terminou antes de aguardar o lock: ' . (string) $state['message']);
            }
        }
        usleep(20000);
    }
    $state = $workerState($db, $worker['slot']);
    $stdout = stream_get_contents($worker['pipes'][1]);
    $stderr = stream_get_contents($worker['pipes'][2]);
    throw new RuntimeException('A corrida sintética não produziu lock wait InnoDB observável. Worker=' . json_encode($state) . '; stdout=' . $stdout . '; stderr=' . $stderr);
};
$finishWorker = static function (array $worker) use ($db, $workerState): array {
    $deadline = microtime(true) + 10;
    $state = null;
    while (microtime(true) < $deadline) {
        $state = $workerState($db, $worker['slot']);
        if ($state !== null && in_array($state['status'], ['done', 'error'], true)) break;
        usleep(20000);
    }
    if ($state === null || !in_array($state['status'], ['done', 'error'], true)) {
        $status = proc_get_status($worker['process']);
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        proc_terminate($worker['process']);
        proc_close($worker['process']);
        throw new RuntimeException('Worker não terminou: ' . ($stderr ?: $stdout) . ' (running=' . json_encode($status['running']) . ')');
    }
    stream_get_contents($worker['pipes'][1]);
    stream_get_contents($worker['pipes'][2]);
    fclose($worker['pipes'][1]);
    fclose($worker['pipes'][2]);
    proc_close($worker['process']);
    return $state;
};

foreach ($races as $key => [$parentRepoClass, $subtaskRepoClass, $allocationRepoClass, $subtaskType, $subtaskOrder]) {
    [, $parent, $child, $foreignKey, $allocation] = $modules[$key];
    foreach (['subtask', 'allocation'] as $recordKind) {
      foreach (['writer_first', 'delete_first'] as $ordering) {
        $clearRace($db);
        $db->prepare("INSERT INTO `$parent` (id) VALUES ('RACE')")->execute();
        $slot = bin2hex(random_bytes(16));
        $insert = $db->prepare("INSERT INTO rei107_worker_runs (slot,status) VALUES (:slot,'starting')");
        $insert->execute([':slot' => $slot]);
        $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->beginTransaction();
        $lock = $db->prepare("SELECT id FROM `$parent` WHERE id='RACE' FOR UPDATE");
        $lock->execute();
        $lock->fetchColumn();
        $blockerId = (int) $db->query('SELECT CONNECTION_ID()')->fetchColumn();

        $workerMode = $ordering === 'writer_first' ? 'delete' : ($recordKind === 'subtask' ? 'create' : 'allocate');
        $worker = $launchWorker($workerMode, $key, 'RACE', $slot);
        $waitForLock($worker, $blockerId, $parent);

        if ($ordering === 'writer_first') {
            if ($recordKind === 'subtask') {
                (new $subtaskRepoClass($db))->create('RACE', $subtaskType, $subtaskOrder, 'pendente', null);
            } else {
                (new $allocationRepoClass($db))->addAlocado('RACE', 'RACE-USER-' . $slot);
            }
            $db->commit();
            $state = $finishWorker($worker);
            if ($state['status'] !== 'done') throw new RuntimeException("Delete concorrente falhou após criação em $key: {$state['message']}");
        } else {
            $db->prepare("DELETE FROM `$child` WHERE `$foreignKey`='RACE'")->execute();
            $db->prepare("DELETE FROM `$allocation` WHERE `$foreignKey`='RACE'")->execute();
            if (!(new $parentRepoClass($db))->delete('RACE')) throw new RuntimeException("Pai RACE não removido na fixture $key");
            $db->commit();
            $state = $finishWorker($worker);
            if ($state['status'] !== 'error' || !str_contains((string) $state['message'], 'Registro principal não encontrado')) {
                throw new RuntimeException("Writer concorrente não foi recusado após exclusão em $key: " . (string) $state['message']);
            }
        }

        $assertCount($db, $parent, 'RACE', 0);
        $remaining = $db->prepare("SELECT COUNT(*) FROM `$child` WHERE `$foreignKey`='RACE'");
        $remaining->execute();
        if ((int) $remaining->fetchColumn() !== 0) throw new RuntimeException("Subtarefa órfã surgiu na corrida $ordering em $key");
        $remaining = $db->prepare("SELECT COUNT(*) FROM `$allocation` WHERE `$foreignKey`='RACE'");
        $remaining->execute();
        if ((int) $remaining->fetchColumn() !== 0) throw new RuntimeException("Alocação órfã surgiu na corrida $ordering em $key");
      }
    }
}
$clearRace($db);

$clear($db);
echo "PASS REI-107: exclusão por registro preserva órfãos/módulos, faz rollback e serializa criação concorrente de subtarefas/alocações em MySQL 8.4/InnoDB isolado.\n";
