<?php
declare(strict_types=1);

require __DIR__ . '/../../app/services/ProcessoAssuntoSyncService.php';

function checkSyncPayload(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn(): string => '2026-11-16 12:00:00', 0);
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT NULL, data_fato TEXT NULL, sigiloso INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE assuntos (id TEXT PRIMARY KEY, codigo TEXT UNIQUE, descricao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_assuntos (id TEXT PRIMARY KEY, processo_id TEXT, assunto_id TEXT, origem TEXT, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
$cnj = '0000001-69.2020.8.05.0001';
$db->exec("INSERT INTO processos VALUES ('P','$cnj',NULL,NULL,0,'','')");
$db->exec("INSERT INTO assuntos VALUES ('A1','1','Manual','',''), ('A2','2','DataJud atual','','')");
$db->exec("INSERT INTO processo_assuntos VALUES ('M','P','A1','manual','',''), ('D','P','A2','datajud','','')");
$service = new ProcessoAssuntoSyncService($db);
$snapshot = static fn(): array => [
    $db->query('SELECT * FROM assuntos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $db->query('SELECT * FROM processo_assuntos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
];
$before = $snapshot();

foreach ([[null], [['codigo' => []]], [['descricao' => 'sem código']], ['texto'], ['codigo' => '3']] as $invalid) {
    try { $service->syncFromDataJud($cnj, $invalid); throw new RuntimeException('Payload inválido aceito.'); }
    catch (InvalidArgumentException $expected) {}
    checkSyncPayload($snapshot() === $before, 'Payload inválido alterou assunto ou vínculo.');
}

$empty = $service->syncFromDataJud($cnj, []);
checkSyncPayload($empty['stats']['removed'] === 1, 'Lista vazia explícita não removeu o vínculo DataJud.');
checkSyncPayload((int)$db->query("SELECT COUNT(*) FROM processo_assuntos WHERE origem='manual'")->fetchColumn() === 1, 'Lista vazia removeu vínculo manual.');
$db->exec("INSERT INTO processo_assuntos VALUES ('D2','P','A2','datajud','','')");

$db->exec("CREATE TRIGGER fail_datajud_delete BEFORE DELETE ON processo_assuntos WHEN OLD.origem='datajud' BEGIN SELECT RAISE(ABORT, 'synthetic delete failure'); END");
$beforeFailure = $snapshot();
try {
    $service->syncFromDataJud($cnj, [['codigo' => '3', 'descricao' => 'Novo']]);
    throw new RuntimeException('Falha sintética de remoção não ocorreu.');
} catch (PDOException $expected) {
    checkSyncPayload(!$db->inTransaction(), 'Transação própria permaneceu aberta após falha.');
    checkSyncPayload($snapshot() === $beforeFailure, 'Erro intermediário deixou gravações parciais.');
}

echo "PASS rejeição, [] explícito, vínculo manual e rollback SQLite\n";
