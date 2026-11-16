<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO processos VALUES ('p1', '00008323520184013202', NULL, NULL, 0, NULL, NULL)");
$db->exec('CREATE TABLE estado (id INTEGER PRIMARY KEY, valor TEXT NOT NULL)');
$db->exec("INSERT INTO estado VALUES (1, 'classe anterior'), (2, 'assunto manual')");
$numero = '00008323520184013202';
$classe = ['codigo' => '123', 'descricao' => 'Classe'];
$assuntos = [['codigo' => '456', 'descricao' => 'Assunto']];
$syncClasse = function () use ($db): array {
    $db->exec("UPDATE estado SET valor='classe nova' WHERE id=1");
    return ['action' => 'updated'];
};
$syncAssuntos = function () use ($db): array {
    $db->exec("INSERT INTO estado VALUES (3, 'assunto datajud')");
    return ['action' => 'updated'];
};
$service = new ProcessoDataJudSyncService($db, $syncClasse, $syncAssuntos);
$result = $service->sync($numero, $classe, $assuntos);
if (($result['classe']['action'] ?? '') !== 'updated' || (int) $db->query('SELECT COUNT(*) FROM estado')->fetchColumn() !== 3) throw new RuntimeException('Sucesso não persistiu.');
if ($db->query('SELECT valor FROM estado WHERE id=2')->fetchColumn() !== 'assunto manual') throw new RuntimeException('Dado manual foi alterado.');

$db->exec("DELETE FROM estado WHERE id=3; UPDATE estado SET valor='classe anterior' WHERE id=1");
$failure = new ProcessoDataJudSyncService($db, $syncClasse, function () use ($db): array {
    $db->exec("INSERT INTO estado VALUES (3, 'transitório')");
    throw new RuntimeException('falha sintética');
});
try { $failure->sync($numero, $classe, $assuntos); throw new LogicException('Esperava falha.'); }
catch (RuntimeException $e) { if ($e->getMessage() !== 'falha sintética') throw $e; }
if ($db->query('SELECT valor FROM estado WHERE id=1')->fetchColumn() !== 'classe anterior' || (int) $db->query('SELECT COUNT(*) FROM estado')->fetchColumn() !== 2) throw new RuntimeException('Rollback incompleto.');
$service->sync($numero, $classe, []);
if ((int) $db->query('SELECT COUNT(*) FROM estado')->fetchColumn() !== 2) throw new RuntimeException('Lista vazia alterou assuntos.');
foreach ([null, [], [['codigo' => null]], [['codigo' => '456'], ['codigo' => '']]] as $payload) {
    try { $service->sync($numero, null, $payload); throw new LogicException('Esperava rejeição.'); }
    catch (InvalidArgumentException $e) {}
}
$db->beginTransaction();
try { $service->sync($numero, $classe, null); throw new LogicException('Transação externa foi aceita.'); }
catch (RuntimeException $e) { if (!$db->inTransaction()) throw $e; }
$db->rollBack();
echo "PASS: sincronização sintética, preservação e rollback\n";
