<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncQueue.php';
require_once __DIR__ . '/../../app/services/ProcessoDataJudBackgroundWorker.php';

$dsn = trim((string) getenv('DATAJUD_TEST_DSN'));
if ($dsn === '') throw new RuntimeException('DATAJUD_TEST_DSN is required for the isolated MariaDB test.');
$db = new PDO($dsn, (string) getenv('DATAJUD_TEST_USER'), (string) getenv('DATAJUD_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$db->exec('DROP TABLE IF EXISTS processo_datajud_sync_queue');
$db->exec('DROP TABLE IF EXISTS processos');
$db->exec("CREATE TABLE processos (
    id CHAR(30) NOT NULL PRIMARY KEY,
    numero_processo VARCHAR(64) NOT NULL,
    classe_id CHAR(30) NULL,
    data_fato DATE NULL,
    sigiloso TINYINT NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("INSERT INTO processos (id, numero_processo, sigiloso) VALUES ('PRC-00000000000000000000000000', '0000001-69.2020.8.05.0001', 0)");
$migration = file_get_contents(__DIR__ . '/../../database/migrations/20261126_create_processo_datajud_sync_queue.sql');
if (!is_string($migration) || trim($migration) === '') throw new RuntimeException('Queue migration is missing.');
$db->exec($migration);

$processId = 'PRC-00000000000000000000000000';
$queueRow = static fn(): array => $db->query('SELECT status, tentativas, solicitado_em, processando_em FROM processo_datajud_sync_queue WHERE processo_id = ' . $db->quote($processId))->fetch();
ProcessoDataJudSyncQueue::enqueueBestEffort($db, $processId);
$row = $queueRow();
if (($row['status'] ?? null) !== 'pendente' || (int) ($row['tentativas'] ?? -1) !== 0) throw new RuntimeException('First enqueue did not create a pending job.');

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

$db->exec('DROP TABLE processo_datajud_sync_queue');
$db->exec('DROP TABLE processos');
echo "PASS: MariaDB queue migration, retry, concurrent use, worker claim and stale-job recovery.\n";
