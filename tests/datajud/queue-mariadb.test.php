<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncQueue.php';
require_once __DIR__ . '/../../app/services/ProcessoDataJudBackgroundWorker.php';
require_once __DIR__ . '/../../app/helpers/ProcessoSetorAuthorization.php';

$dsn = trim((string) getenv('DATAJUD_TEST_DSN'));
if ($dsn === '') throw new RuntimeException('DATAJUD_TEST_DSN is required for the isolated MariaDB test.');
$db = new PDO($dsn, (string) getenv('DATAJUD_TEST_USER'), (string) getenv('DATAJUD_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('DROP TABLE IF EXISTS processo_datajud_sync_queue');
$_SESSION = ['id_usuario' => 'USR-00000000000000000000000000'];
$db->exec('DROP TABLE IF EXISTS processos');
$db->exec("CREATE TABLE processos (
    id CHAR(30) NOT NULL PRIMARY KEY,
    numero_processo VARCHAR(64) NOT NULL,
    classe_id CHAR(30) NULL,
    id_setor_responsavel CHAR(30) NULL,
    data_fato DATE NULL,
    sigiloso TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE setores (id_setor CHAR(30) PRIMARY KEY, ativo TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB");
$db->exec("CREATE TABLE usuario_setor (usuario CHAR(30) NOT NULL, setor CHAR(30) NOT NULL, PRIMARY KEY (usuario, setor)) ENGINE=InnoDB");
$db->exec("INSERT INTO setores (id_setor, ativo) VALUES ('SET-1', 1), ('SET-2', 1)");
$db->exec("INSERT INTO usuario_setor (usuario, setor) VALUES ('USR-00000000000000000000000000', 'SET-1')");
$db->exec("INSERT INTO processos (id, numero_processo, id_setor_responsavel, sigiloso) VALUES ('PRC-00000000000000000000000000', '0000001-69.2020.8.05.0001', 'SET-1', 0)");
$migration = file_get_contents(__DIR__ . '/../../database/migrations/20261126_create_processo_datajud_sync_queue.sql');
if (!is_string($migration) || trim($migration) === '') throw new RuntimeException('Queue migration is missing.');
$db->exec($migration);

$processId = 'PRC-00000000000000000000000000';
require_once __DIR__ . '/../processos/vara-migration-contract.php';
testVaraMigrationContract($db, $processId);
testNivelSigiloMigrationContract($db, $processId);
$queueRow = static fn(string $id): array|false => $db->query('SELECT status, requested_by, tentativas, solicitado_em, processando_em FROM processo_datajud_sync_queue WHERE processo_id = ' . $db->quote($id))->fetch();
$repo = new ProcessosRepository($db);
$_SESSION = [];
ProcessoDataJudSyncQueue::enqueueFirstRegistration($db, $processId);
if ($queueRow($processId) !== false) throw new RuntimeException('An unattributed request created a job.');
$_SESSION = ['id_usuario' => 'USR-00000000000000000000000000'];
$repo->ensureProcess('0000001-69.2020.8.05.0001');
if ($queueRow($processId) !== false) throw new RuntimeException('Reusing an already registered process queued a first registration.');
$db->beginTransaction();
$authorized = ProcessoSetorAuthorization::canModifyProcess($db, $_SESSION['id_usuario'], $processId, true);
$db->rollBack();
if (!$authorized) throw new RuntimeException('The assigned process sector was not authorized.');
$db->beginTransaction();
$denied = ProcessoSetorAuthorization::canModifyProcess($db, 'USR-OUTRO-SETOR', $processId, true);
$db->rollBack();
if ($denied) throw new RuntimeException('A user outside the responsible sector was authorized.');

$id = $repo->ensureProcess('0000002-54.2020.8.05.0001');
$row = $queueRow($id);
if (($row['status'] ?? null) !== 'cadastro_inicial' || ($row['requested_by'] ?? null) !== $_SESSION['id_usuario'] || (int) ($row['tentativas'] ?? -1) !== 0) {
    throw new RuntimeException('The first new registration did not create one attributed job.');
}
$repo->ensureProcess('0000002-54.2020.8.05.0001');
ProcessoDataJudSyncQueue::enqueueFirstRegistration($db, $id);
if ($queueRow($id) !== $row) throw new RuntimeException('Reuse or a duplicated enqueue changed the first job.');
$restricted = $repo->ensureProcess('0000003-39.2020.8.05.0001', null, null, true);
if ($queueRow($restricted) !== false) throw new RuntimeException('A restricted registration created an API job.');

$db->exec("UPDATE processos SET id_setor_responsavel='SET-1' WHERE id=" . $db->quote($id));
$db->exec("INSERT INTO processo_datajud_sync_queue (processo_id, requested_by, status, tentativas, proxima_tentativa_em, solicitado_em, created_at, updated_at)
 VALUES (" . $db->quote($processId) . ", 'USR-00000000000000000000000000', 'pendente', 0, DATE_SUB(NOW(), INTERVAL 1 MINUTE), NOW(6), NOW(), NOW())");
$legacy = $queueRow($processId);
$id2 = $repo->ensureProcess('0000004-24.2020.8.05.0001');
$db->exec("UPDATE processos SET id_setor_responsavel='SET-1' WHERE id=" . $db->quote($id2));
$db->exec("UPDATE processo_datajud_sync_queue SET proxima_tentativa_em=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE processo_id=" . $db->quote($id));
$empty = ['timed_out' => false, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'skipped' => 0], 'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]];
$partial = $empty;
$partial['_shards'] = ['total' => 3, 'successful' => 1, 'failed' => 2, 'skipped' => 0];
$callsPartial = 0;
$partialClient = new DataJudService([], static function (string $url, array $body) use ($partial, &$callsPartial): array {
    $callsPartial++;
    return ['http_code'=>200, 'decoded'=>$partial, 'curl_error'=>'', 'raw'=>''];
});
$otherWorker = new PDO($dsn, (string) getenv('DATAJUD_TEST_USER'), (string) getenv('DATAJUD_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$batchLock = "CONCAT('ios1vcrime-dj-worker:', LEFT(SHA2(DATABASE(), 256), 40))";
if ((int) $otherWorker->query("SELECT GET_LOCK($batchLock, 0)")->fetchColumn() !== 1) throw new RuntimeException('Synthetic worker lock failed.');
try {
    $busy = (new ProcessoDataJudBackgroundWorker($db, $partialClient))->run(20);
    if ($busy !== ['processados'=>0, 'falhas'=>0] || $callsPartial !== 0 || $queueRow($id)['status'] !== 'cadastro_inicial') {
        throw new RuntimeException('A second worker claimed work while the batch lock was held.');
    }
} finally {
    $otherWorker->query("SELECT RELEASE_LOCK($batchLock)")->fetchColumn();
}
$notSent = new DataJudService([], static function (): array {
    throw new DataJudUpstreamException('local_throttled', 25);
});
$deferred = (new ProcessoDataJudBackgroundWorker($db, $notSent))->run(20);
$scheduledDelay = (int) $db->query('SELECT TIMESTAMPDIFF(SECOND,NOW(),proxima_tentativa_em) FROM processo_datajud_sync_queue WHERE processo_id=' . $db->quote($id))->fetchColumn();
if ($deferred !== ['processados'=>0, 'falhas'=>0] || $queueRow($id)['status'] !== 'cadastro_inicial'
    || (int) $queueRow($id)['tentativas'] !== 0 || $scheduledDelay < 20
    || $queueRow($processId) !== $legacy) {
    throw new RuntimeException('Local rate control consumed an attempt or changed legacy work.');
}
$db->exec("UPDATE processo_datajud_sync_queue SET proxima_tentativa_em=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE processo_id=" . $db->quote($id));
$blocked = (new ProcessoDataJudBackgroundWorker($db, $partialClient))->run(20);
if ($blocked !== ['processados'=>0, 'falhas'=>1] || $callsPartial !== 1
    || $queueRow($id)['status'] !== 'falhou' || (int) $queueRow($id)['tentativas'] !== 1
    || $queueRow($id2)['status'] !== 'cadastro_inicial' || (int) $queueRow($id2)['tentativas'] !== 0
    || $queueRow($processId) !== $legacy
    || (int) $db->query("SELECT IS_FREE_LOCK($batchLock)")->fetchColumn() !== 1) {
    throw new RuntimeException('Incomplete DataJud result drained the batch, changed the legacy row or leaked the lock.');
}
$repo->ensureProcess('0000002-54.2020.8.05.0001');
ProcessoDataJudSyncQueue::enqueueFirstRegistration($db, $id);
$db->exec("UPDATE processo_datajud_sync_queue SET proxima_tentativa_em=NOW() WHERE processo_id=" . $db->quote($id));
$callsOk = 0;
$client = new DataJudService([], static function (string $url, array $body) use ($empty, &$callsOk): array {
    $callsOk++;
    return ['http_code'=>200, 'decoded'=>$empty, 'curl_error'=>'', 'raw'=>''];
});
$result = (new ProcessoDataJudBackgroundWorker($db, $client))->run(20);
if ($result !== ['processados'=>1, 'falhas'=>0] || $callsOk !== 1 || $queueRow($id2)['status'] !== 'concluido'
    || $queueRow($id)['status'] !== 'falhou' || $queueRow($processId) !== $legacy) {
    throw new RuntimeException('A legacy or failed job retried, or another first registration did not run.');
}
$db->exec("UPDATE processo_datajud_sync_queue SET status='cad_inicial_exec', processando_em=DATE_SUB(NOW(6), INTERVAL 16 MINUTE) WHERE processo_id=" . $db->quote($id2));
(new ProcessoDataJudBackgroundWorker($db, $client))->run(20);
if ($callsOk !== 1 || $queueRow($id2)['status'] !== 'falhou' || $queueRow($id2)['processando_em'] !== null
    || $queueRow($processId) !== $legacy) {
    throw new RuntimeException('An interrupted first lookup retried or modified a legacy job.');
}
foreach ([$id, $id2, $restricted] as $extra) {
    $db->exec('DELETE FROM processos WHERE id=' . $db->quote($extra));
}
echo "PASS: one lookup on new registration, no retries or legacy work, no duplicate workers.\n";

// Same isolated MariaDB runtime also checks provenance migration and
// native SQL updates. No real process data or network requests.
$db->exec("CREATE TABLE classes (id CHAR(30) PRIMARY KEY, codigo VARCHAR(20) UNIQUE, nome VARCHAR(80), created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB");
$db->exec("INSERT INTO classes (id,codigo,nome) VALUES ('CLASS-A','11','Classe sintética A'),('CLASS-B','12','Classe sintética B')");
$db->exec("UPDATE processos SET classe_id='CLASS-A'");
$origemMigration = file_get_contents(__DIR__ . '/../../database/migrations/20261127_add_processos_classe_origem.sql');
if (!is_string($origemMigration) || trim($origemMigration) === '') throw new RuntimeException('Origin migration is missing.');
$applyOrigem = static function (PDO $connection) use ($origemMigration): void {
    // Execute in the same physical connection; surface errors from every statement.
    $cursor = $connection->query($origemMigration);
    do {
        if ($cursor->columnCount() > 0) $cursor->fetchAll();
    } while ($cursor->nextRowset());
    $cursor->closeCursor();
};
$mdlTimeout = (int) $db->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn();
$foreignLock = new PDO($dsn, (string) getenv('DATAJUD_TEST_USER'), (string) getenv('DATAJUD_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$lockExpr = "CONCAT('rei345_classe_origem:', LEFT(SHA2(DATABASE(), 256), 40))";
if ((int) $foreignLock->query("SELECT GET_LOCK($lockExpr, 0)")->fetchColumn() !== 1) {
    throw new RuntimeException('Failed to take the synthetic migration test lock.');
}
try {
    $applyOrigem($db);
    throw new RuntimeException('A second session migrated while the named lock was held.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'REI345_MIGRATION_LOCK_NOT_ACQUIRED')) throw $e;
} finally {
    $foreignLock->query("SELECT RELEASE_LOCK($lockExpr)")->fetchColumn();
}
if ((int) $db->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== $mdlTimeout
    || (int) $db->query("SELECT IS_FREE_LOCK($lockExpr)")->fetchColumn() !== 1) {
    throw new RuntimeException('The migration left a named lock behind after contention.');
}

$repo = new ProcessosRepository($db);
if ($repo->classeOrigemDisponivel()) throw new RuntimeException('Synthetic legacy schema already contains the provenance column.');
$applyOrigem($db);
$repo = new ProcessosRepository($db);
$legacy = $repo->findById($processId);
if (!$repo->classeOrigemDisponivel() || $legacy === null || $legacy['classe_id'] !== 'CLASS-A' || $legacy['classe_origem'] !== null) {
    throw new RuntimeException('Additive migration did not preserve unknown provenance and the original class.');
}
try {
    $repo->updateClasseForProcesso($processId, 'CLASS-B');
    throw new RuntimeException('A manual write was accepted for a populated legacy class.');
} catch (ProcessoDadoProtegidoException) {}

$classeSync = new ProcessoClasseSyncService($db);
$established = $classeSync->syncFromDataJud('00000016920208050001', '11', 'Classe sintética A', true, false);
if (($established['action'] ?? null) !== 'updated' || $repo->findById($processId)['classe_origem'] !== 'datajud') {
    throw new RuntimeException('Identical verified class did not establish DataJud origin.');
}
$applyOrigem($db);
if ($repo->findById($processId)['classe_origem'] !== 'datajud') {
    throw new RuntimeException('Reapplying the idempotent migration changed existing provenance.');
}
$repo->ensureProcess('0000001-69.2020.8.05.0001', 'CLASS-B');
try {
    $repo->updateClasseForProcesso($processId, 'CLASS-B');
    throw new RuntimeException('A manual write was accepted for a DataJud class.');
} catch (ProcessoDadoProtegidoException) {}
if ($repo->findById($processId)['classe_id'] !== 'CLASS-A') {
    throw new RuntimeException('Direct or indirect manual changes overwrote a DataJud class.');
}
$replaced = $classeSync->syncFromDataJud('00000016920208050001', '12', 'Classe sintética B', true, false);
if (($replaced['action'] ?? null) !== 'updated' || $repo->findById($processId)['classe_id'] !== 'CLASS-B') {
    throw new RuntimeException('Another verified synchronization did not replace the protected class.');
}
// Preserve incompatible pre-existing definitions instead of modifying them or declaring success.
$db->exec("ALTER TABLE processos DROP COLUMN classe_origem, ADD COLUMN classe_origem VARCHAR(12) NULL DEFAULT NULL");
$db->exec("UPDATE processos SET classe_origem='legado'");
try {
    $applyOrigem($db);
    throw new RuntimeException('An incompatible existing definition was accepted.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'REI345_INCOMPATIBLE_CLASSE_ORIGEM')) throw $e;
}
if ($db->query('SELECT classe_origem FROM processos')->fetchColumn() !== 'legado'
    || (int) $db->query("SELECT IS_FREE_LOCK($lockExpr)")->fetchColumn() !== 1) {
    throw new RuntimeException('An incompatible migration changed data or leaked its lock.');
}
$db->exec('DROP TABLE classes');
echo "PASS: isolated provenance migration applies twice, preserves legacy/DataJud origins, and rejects incompatible schema and contention.\n";

$db->exec('DROP TABLE processo_datajud_sync_queue');
$db->exec('DROP TABLE processos');
$db->exec('DROP TABLE usuario_setor');
$db->exec('DROP TABLE setores');
echo "PASS: MariaDB queue migration, first registration, concurrency and legacy isolation.\n";
