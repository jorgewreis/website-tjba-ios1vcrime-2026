<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/ProcessoAssuntoSyncService.php';

final class AssuntoSqlite extends PDO
{
    public int $locks = 0;
    public array $lockingReads = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn(): string => '2026-01-01', 0);
        $this->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE assuntos (id TEXT PRIMARY KEY, codigo TEXT, descricao TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE processo_assuntos (id TEXT PRIMARY KEY, processo_id TEXT, assunto_id TEXT, origem TEXT, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/FOR UPDATE\s*$/i', $query)) $this->lockingReads[] = $query;
        if (preg_match('/SELECT\s+`id`\s+FROM\s+`processos`.*FOR UPDATE/i', $query)) $this->locks++;
        $query = preg_replace('/\s+FOR UPDATE\s*$/i', '', $query);
        $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}
function checkAssunto(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new AssuntoSqlite();
$db->exec("INSERT INTO processos VALUES ('P','0000001-69.2020.8.05.0001',NULL,NULL,0,NULL,NULL)");
$db->exec("INSERT INTO assuntos VALUES ('A','1','Manual',NULL,NULL),('B','9','Manual adicional',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('M','P','A','manual',NULL,NULL),('E','P','B','manual',NULL,NULL)");
$service = new ProcessoAssuntoSyncService($db);
$cnj = '0000001-69.2020.8.05.0001';
$result = $service->syncFromDataJud($cnj, [['codigo'=>'1','descricao'=>'Manual']]);
checkAssunto($result['stats']['inserted'] === 0, 'Manual contado como inserido.');
checkAssunto($result['stats']['promoted'] === 1 && $result['action'] === 'updated', 'A confirmação DataJud deve atualizar só a origem.');
checkAssunto($db->query("SELECT origem FROM processo_assuntos WHERE id='M'")->fetchColumn() === 'datajud', 'Assunto confirmado não ganhou proteção.');
$service->syncFromDataJud($cnj, [['codigo'=>'2','descricao'=>'Judicial']]);
checkAssunto(count(array_filter($db->lockingReads, static fn($q) => str_contains($q, 'FROM `assuntos`'))) > 0, 'Catálogo sem leitura atual.');
checkAssunto(count(array_filter($db->lockingReads, static fn($q) => str_contains($q, 'FROM `processo_assuntos`'))) > 0, 'Vínculos sem leitura atual.');
checkAssunto($db->query("SELECT origem FROM processo_assuntos WHERE id='M'")->fetchColumn() === false, 'Só outra sincronização pode remover vínculo confirmado.');
checkAssunto($db->query("SELECT origem FROM processo_assuntos WHERE id='E'")->fetchColumn() === 'manual', 'Vínculo manual adicional removido.');
checkAssunto((int)$db->query("SELECT COUNT(*) FROM processo_assuntos WHERE origem='datajud'")->fetchColumn() === 1, 'Vínculo DataJud ausente.');
$before = $db->query('SELECT * FROM processo_assuntos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$db->exec("CREATE TRIGGER falha_delete BEFORE DELETE ON processo_assuntos WHEN OLD.origem='datajud' BEGIN SELECT RAISE(ABORT, 'falha sintetica'); END");
foreach ([false, true] as $outer) {
    if ($outer) $db->beginTransaction();
    try {
        $service->syncFromDataJud($cnj, [['codigo'=>'3','descricao'=>'Outro']]);
        throw new RuntimeException('Falha sintética não ocorreu.');
    } catch (PDOException $e) {
        checkAssunto($db->inTransaction() === $outer, 'Estado da transação externa incorreto.');
        checkAssunto($db->query('SELECT * FROM processo_assuntos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $before, 'Vínculos parcialmente alterados.');
        checkAssunto((int)$db->query("SELECT COUNT(*) FROM assuntos WHERE codigo='3'")->fetchColumn() === 0, 'Assunto parcialmente persistido.');
    }
    if ($outer) $db->rollBack();
}
$db->exec('DROP TRIGGER falha_delete');
$beforeLocks = $db->locks;
$service->linkManual('P', '4', 'Manual quatro');
$id4 = (string)$db->query("SELECT id FROM assuntos WHERE codigo='4'")->fetchColumn();
checkAssunto($db->locks === $beforeLocks + 1, 'Vínculo manual sem lock do processo.');
$beforeLocks = $db->locks;
$deleted = $service->unlinkManual('P', $id4);
checkAssunto($db->locks === $beforeLocks + 1, 'Desvínculo manual sem lock do processo.');
checkAssunto($deleted, 'Desvínculo manual não reportou a remoção.');
checkAssunto((int)$db->query("SELECT COUNT(*) FROM processo_assuntos WHERE assunto_id=" . $db->quote($id4))->fetchColumn() === 0, 'Desvínculo manual não realizado.');
$dataJudId = (string)$db->query("SELECT assunto_id FROM processo_assuntos WHERE origem='datajud' LIMIT 1")->fetchColumn();
checkAssunto($dataJudId !== '', 'Fixture DataJud não existe.');
$beforeLocks = $db->locks;
try {
    $service->unlinkManual('P', $dataJudId);
    throw new RuntimeException('Desvínculo manual não recusado para assunto DataJud.');
} catch (ProcessoDadoProtegidoException) {}
checkAssunto($db->locks === $beforeLocks + 1, 'Tentativa de desvínculo DataJud sem lock do processo.');
try {
    $service->linkManual('P', '2', 'Renomear manualmente');
    throw new RuntimeException('Vinculação manual não recusada para assunto DataJud.');
} catch (ProcessoDadoProtegidoException) {}
$db->exec("INSERT INTO processos VALUES ('P2','0000000-98.0000.0.00.0000',NULL,NULL,0,NULL,NULL)");
checkAssunto($service->linkManual('P2', '2', 'Renomear no outro processo'), 'Reuso de assunto ainda não vinculado deve ser permitido.');
checkAssunto($db->query("SELECT descricao FROM assuntos WHERE codigo='2'")->fetchColumn() === 'Judicial', 'Reuso de código manual renomeou o catálogo sincronizado.');
checkAssunto(!(new ProcessoAssuntosRepository($db))->upsert('P', $dataJudId), 'Upsert duplicado não é inserção.');
checkAssunto((string)$db->query("SELECT origem FROM processo_assuntos WHERE assunto_id=" . $db->quote($dataJudId))->fetchColumn() === 'datajud', 'Origem DataJud não foi preservada.');
$db->beginTransaction();
$service->linkManual('P', '5', 'Manual cinco');
checkAssunto($db->inTransaction(), 'Vínculo manual encerrou transação externa.');
$db->rollBack();
checkAssunto((int)$db->query("SELECT COUNT(*) FROM assuntos WHERE codigo='5'")->fetchColumn() === 0, 'Rollback externo manual não preservado.');
echo "PASS preservação manual e rollback de sincronização (SQLite adaptado; sem lock MySQL)\n";
