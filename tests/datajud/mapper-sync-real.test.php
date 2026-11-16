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
foreach ([[], ['assuntos' => []]] as $source) {
    $payload = $map($source);
    if (!in_array($payload['assuntos_estado'], ['ausente', 'vazio'], true)) throw new RuntimeException('Estado ausente/vazio incorreto.');
    try { $sync->sync('00008323520184013202', null, $payload['assuntos']); throw new LogicException('Sem dados deve falhar.'); }
    catch (InvalidArgumentException $expected) {}
    if ($snapshot() !== $before) throw new RuntimeException('Ausência alterou vínculos.');
}
foreach ([null, [['nome' => 'Sem código']], [['codigo'=>42,'nome'=>'Manual'], ['nome'=>'Sem código']]] as $invalid) {
    try { $map(['assuntos' => $invalid]); throw new LogicException('Assunto inválido foi aceito.'); }
    catch (RuntimeException $expected) {}
    if ($snapshot() !== $before) throw new RuntimeException('Resposta inválida alterou vínculos.');
}
$payload = $map(['assuntos'=>[['codigo'=>42,'nome'=>'Manual']]]);
$sync->sync('00008323520184013202', null, $payload['assuntos']);
if ($snapshot() !== [['assunto_id'=>'a1','origem'=>'manual']]) throw new RuntimeException('Sincronização não preservou vínculo manual ou não removeu automático obsoleto.');
echo "PASS: mapper e serviços reais preservam ausência, erro e vínculo manual em SQLite.\n";
