<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncService.php';

final class FichaSQLite extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Simulate SQL supported by SQLite; this fixture does not test MySQL locks.
        return parent::prepare(preg_replace('/ FOR UPDATE$/', '', $query), $options);
    }
}

function expectFicha(bool $fact, string $message): void
{
    if (!$fact) throw new RuntimeException($message);
}

$db = new FichaSQLite('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('NOW', static fn () => '2026-01-01 00:00:00');
$db->exec('CREATE TABLE processos(id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE classes(id TEXT PRIMARY KEY, codigo TEXT UNIQUE, nome TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE assuntos(id TEXT PRIMARY KEY, codigo TEXT UNIQUE, descricao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_assuntos(id TEXT PRIMARY KEY, processo_id TEXT, assunto_id TEXT, origem TEXT, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
$db->exec("INSERT INTO processos VALUES ('P','00008323520184013202','C1',NULL,0,NULL,NULL)");
$db->exec("INSERT INTO classes VALUES ('C1','1268','Classe local',NULL,NULL),('C2','2','Outra classe',NULL,NULL)");
$db->exec("INSERT INTO assuntos VALUES ('A1','42','Assunto local',NULL,NULL),('A2','44','Manual',NULL,NULL),('A3','45','Substituto',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('L1','P','A1','datajud',NULL,NULL),('L2','P','A2','manual',NULL,NULL)");
$service = new ProcessoDataJudSyncService($db, onlyChangedFields: true);

$before = (int) $db->query('SELECT total_changes()')->fetchColumn();
$same = $service->sync('00008323520184013202', ['codigo'=>1268,'descricao'=>'Classe remota'], [['codigo'=>42,'descricao'=>'Rótulo remoto']]);
expectFicha($same['classe']['action'] === 'noop' && $same['assuntos']['action'] === 'noop', 'Mesmos códigos devem retornar noop.');
expectFicha((int) $db->query('SELECT total_changes()')->fetchColumn() === $before, 'Sincronização igual escreveu dados.');
$newClass = $service->sync('00008323520184013202', ['codigo'=>2,'descricao'=>'Outra classe'], null);
expectFicha($newClass['classe']['action'] === 'updated' && $newClass['assuntos'] === null, 'Ausência de assunto não deve apagar dados.');
expectFicha($db->query('SELECT COUNT(*) FROM processo_assuntos')->fetchColumn() == 2, 'Lista existente foi apagada.');
$newSubject = $service->sync('00008323520184013202', null, [['codigo'=>45,'descricao'=>'Substituto']]);
expectFicha($newSubject['assuntos']['action'] === 'updated', 'Lista diferente deve atualizar.');
expectFicha($db->query('SELECT assunto_id,origem FROM processo_assuntos ORDER BY assunto_id')->fetchAll(PDO::FETCH_KEY_PAIR) === ['A2'=>'manual','A3'=>'datajud'], 'Deve substituir só vínculos DataJud e preservar os manuais.');
$db->exec("UPDATE processos SET classe_id=NULL WHERE id='P'; DELETE FROM processo_assuntos WHERE processo_id='P'");
$fill = $service->sync('00008323520184013202', ['codigo'=>2,'descricao'=>'Outra classe'], [['codigo'=>45,'descricao'=>'Substituto']]);
expectFicha($fill['classe']['action'] === 'updated' && $fill['assuntos']['action'] === 'updated', 'Campos vazios devem ser preenchidos.');
echo "PASS: ficha DataJud atualiza só diferenças, sem apagar campos ausentes ou links manuais.\n";
