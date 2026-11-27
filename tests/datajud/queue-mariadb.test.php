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
$queueRow = static fn(): array|false => $db->query('SELECT status, requested_by, tentativas, solicitado_em, processando_em FROM processo_datajud_sync_queue WHERE processo_id = ' . $db->quote($processId))->fetch();
$_SESSION = [];
ProcessoDataJudSyncQueue::enqueueBestEffort($db, $processId);
if ($queueRow() !== false) throw new RuntimeException('An unattributed request created a background job.');
$_SESSION = ['id_usuario' => 'USR-00000000000000000000000000'];
ProcessoDataJudSyncQueue::enqueueBestEffort($db, $processId);
$row = $queueRow();
if (($row['status'] ?? null) !== 'pendente' || ($row['requested_by'] ?? null) !== $_SESSION['id_usuario'] || (int) ($row['tentativas'] ?? -1) !== 0) throw new RuntimeException('First enqueue did not create an attributed pending job.');
$db->beginTransaction();
$authorized = ProcessoSetorAuthorization::canModifyProcess($db, $_SESSION['id_usuario'], $processId, true);
$db->rollBack();
if (!$authorized) throw new RuntimeException('The assigned process sector was not authorized.');
$db->beginTransaction();
$denied = ProcessoSetorAuthorization::canModifyProcess($db, 'USR-OUTRO-SETOR', $processId, true);
$db->rollBack();
if ($denied) throw new RuntimeException('A user outside the responsible sector was authorized.');

$db->exec("UPDATE processo_datajud_sync_queue SET status='concluido', tentativas=6 WHERE processo_id=" . $db->quote($processId));
ProcessoDataJudSyncQueue::enqueueBestEffort($db, $processId);
$row = $queueRow();
if (($row['status'] ?? null) !== 'pendente' || (int) ($row['tentativas'] ?? -1) !== 0) throw new RuntimeException('A new process use did not requeue completed work.');

$db->exec("UPDATE processo_datajud_sync_queue SET status='processando', processando_em=NOW(6) WHERE processo_id=" . $db->quote($processId));
$claimedAt = (string) $db->query('SELECT processando_em FROM processo_datajud_sync_queue WHERE processo_id=' . $db->quote($processId))->fetchColumn();
usleep(2000);
ProcessoDataJudSyncQueue::enqueueBestEffort($db, $processId);
$row = $queueRow();
if (($row['status'] ?? null) !== 'processando' || (string) ($row['solicitado_em'] ?? '') <= $claimedAt) throw new RuntimeException('A use during processing was not scheduled for a follow-up sync.');

$db->exec("UPDATE processo_datajud_sync_queue SET status='pendente', proxima_tentativa_em=NOW(), processando_em=NULL WHERE processo_id=" . $db->quote($processId));
$empty = ['timed_out' => false, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'skipped' => 0], 'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]];
$client = new DataJudService([], static function (string $url, array $body) use ($empty): array {
    return ['http_code' => 200, 'decoded' => $empty, 'curl_error' => '', 'raw' => ''];
});
$result = (new ProcessoDataJudBackgroundWorker($db, $client))->run(1);
$row = $queueRow();
if (($result['processados'] ?? 0) !== 1 || ($row['status'] ?? null) !== 'concluido') throw new RuntimeException('MariaDB worker could not claim and finish a valid empty lookup.');

$db->exec("UPDATE processo_datajud_sync_queue SET status='processando', tentativas=0, processando_em=DATE_SUB(NOW(6), INTERVAL 16 MINUTE) WHERE processo_id=" . $db->quote($processId));
$result = (new ProcessoDataJudBackgroundWorker($db, $client))->run(1);
$row = $queueRow();
if (($result['processados'] ?? 0) !== 0 || ($row['status'] ?? null) !== 'falhou' || (int) ($row['tentativas'] ?? 0) !== 1 || $row['processando_em'] !== null) {
    throw new RuntimeException('A stale claimed job was not returned to the retry queue.');
}

// Same isolated MariaDB runtime also checks provenance migration and
// native SQL updates. No real process data or network requests.
$db->exec("CREATE TABLE classes (id CHAR(30) PRIMARY KEY, codigo VARCHAR(20) UNIQUE, nome VARCHAR(80), created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB");
$db->exec("INSERT INTO classes (id,codigo,nome) VALUES ('CLASS-A','11','Classe sintética A'),('CLASS-B','12','Classe sintética B')");
$db->exec("UPDATE processos SET classe_id='CLASS-A'");
$origemMigration = file_get_contents(__DIR__ . '/../../database/migrations/20261127_add_processos_classe_origem.sql');
if (!is_string($origemMigration) || trim($origemMigration) === '') throw new RuntimeException('Origin migration is missing.');
$repo = new ProcessosRepository($db);
if ($repo->classeOrigemDisponivel()) throw new RuntimeException('Synthetic legacy schema already contains the provenance column.');
$db->exec($origemMigration);
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
$db->exec($origemMigration);
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
$db->exec('DROP TABLE classes');
echo "PASS: MariaDB provenance migration reapplies without changing legacy or DataJud origins.\n";

$db->exec('DROP TABLE processo_datajud_sync_queue');
$db->exec('DROP TABLE processos');
$db->exec('DROP TABLE usuario_setor');
$db->exec('DROP TABLE setores');
echo "PASS: MariaDB queue migration, retry, concurrent use, worker claim and stale-job recovery.\n";
