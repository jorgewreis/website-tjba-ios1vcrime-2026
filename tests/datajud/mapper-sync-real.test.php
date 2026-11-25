<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/DataJud/DataJudResponseMapper.php';
require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncService.php';

// MySQL accepts UPDATE ... LIMIT 1; SQLite does not. Keep the production SQL
// path intact while adapting only that syntax for this in-memory test.
final class MysqlLimitSqlitePdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\\s*UPDATE\\b/i', $query) === 1) {
            $query = preg_replace('/\\s+LIMIT\\s+1\\s*$/i', '', $query) ?? $query;
        }
        // The repository uses a MySQL row lock; SQLite serializes this in-memory test.
        $query = preg_replace('/\\s+FOR\\s+UPDATE\\s*$/i', '', $query) ?? $query;
        return parent::prepare($query, $options);
    }
}

$db = new MysqlLimitSqlitePdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn(): string => '2026-01-01 00:00:00');
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE assuntos (id TEXT PRIMARY KEY, codigo TEXT UNIQUE, descricao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_assuntos (id TEXT PRIMARY KEY, processo_id TEXT NOT NULL, assunto_id TEXT NOT NULL, origem TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
$db->exec("INSERT INTO processos VALUES ('p1','00008323520184013202',NULL,NULL,0,NULL,NULL)");
$db->exec("INSERT INTO assuntos VALUES ('a1','42','Manual',NULL,NULL),('a2','7','Antigo',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('m','p1','a1','manual',NULL,NULL),('d','p1','a2','datajud',NULL,NULL)");
$sync = new ProcessoDataJudSyncService($db);
$snapshot = static fn(): array => $db->query('SELECT assunto_id, origem FROM processo_assuntos ORDER BY assunto_id')->fetchAll(PDO::FETCH_ASSOC);
$before = $snapshot();
$map = static fn(array $source): array => DataJudResponseMapper::mapSourceToPayload($source, '00008323520184013202', false);
foreach ([[]] as $source) {
    $payload = $map($source);
    if ($payload['assuntos_estado'] !== 'ausente') throw new RuntimeException('Fonte ausente deveria permanecer distinta da lista vazia explícita.');
    try { $sync->sync('00008323520184013202', null, null); throw new LogicException('Sem dados deve falhar.'); }
    catch (InvalidArgumentException $expected) {}
    if ($snapshot() !== $before) throw new RuntimeException('Ausência alterou vínculos.');
}
foreach ([null, [['nome' => 'Sem código']], [['codigo'=>42,'nome'=>'Manual'], ['nome'=>'Sem código']]] as $invalid) {
    $partial = $map(['assuntos' => $invalid]);
    if ($partial['assuntos_completos'] !== false) throw new LogicException('Lote parcial marcado como completo.');
    if ($snapshot() !== $before) throw new RuntimeException('Resposta inválida alterou vínculos.');
}
$payload = $map(['assuntos'=>[['codigo'=>42,'nome'=>'Manual']]]);
$sync->sync('00008323520184013202', null, $payload['assuntos']);
if ($snapshot() !== [['assunto_id'=>'a1','origem'=>'manual']]) throw new RuntimeException('Sincronização não preservou vínculo manual ou não removeu automático obsoleto.');

$db->exec("INSERT INTO assuntos VALUES ('a3','8','DataJud antigo',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('d2','p1','a3','datajud',NULL,NULL)");
$empty = $map(['numeroProcesso'=>'00008323520184013202','nivelSigilo'=>0,'assuntos'=>[]]);
if ($empty['assuntos_estado'] !== 'vazio' || $empty['assuntos'] !== [] || $empty['assuntos_completos'] !== false) {
    throw new RuntimeException('Resposta explicitamente vazia foi mapeada incorretamente.');
}
try {
    $sync->sync('00008323520184013202', null, $empty['assuntos']);
    throw new LogicException('Lista vazia sem evidência explícita deveria ser rejeitada.');
} catch (InvalidArgumentException $expected) {}
if ($snapshot() !== [['assunto_id'=>'a1','origem'=>'manual'], ['assunto_id'=>'a3','origem'=>'datajud']]) {
    throw new RuntimeException('Lista vazia não autorizada alterou vínculos.');
}
$sync->sync('00008323520184013202', null, $empty['assuntos'], true);
if ($snapshot() !== [['assunto_id'=>'a1','origem'=>'manual']]) {
    throw new RuntimeException('Lista vazia não removeu somente vínculos DataJud, ou removeu vínculo manual.');
}
$db->exec("INSERT INTO assuntos VALUES ('a4','9','DataJud a preservar',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('d3','p1','a4','datajud',NULL,NULL)");
$beforeInvalid = $snapshot();
try {
    $sync->sync('00008323520184013202', null, [['codigo'=>[],'descricao'=>'inválido']]);
    throw new LogicException('Payload inválido deveria ser rejeitado.');
} catch (InvalidArgumentException $expected) {}
if ($snapshot() !== $beforeInvalid) throw new RuntimeException('Payload inválido alterou vínculos.');

try {
    (new ProcessoAssuntoSyncService($db))->syncFromDataJud('00008323520184013202', [], true);
    throw new LogicException('Sincronização direta não deve aceitar lista vazia sem autorização explícita.');
} catch (InvalidArgumentException $expected) {}
if ($snapshot() !== $beforeInvalid) throw new RuntimeException('Lista vazia direta alterou vínculos.');
echo "PASS: mapper e serviços reais preservam ausência, erro e vínculo manual em SQLite.\n";
