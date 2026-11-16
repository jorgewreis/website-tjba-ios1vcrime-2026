<?php
declare(strict_types=1);
// Banco exclusivamente em memória; adapta LIMIT de UPDATE do MySQL para SQLite.
require_once __DIR__ . '/../../app/services/CejuscRegistrosService.php';
final class CejuscTestPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (preg_match('/^\s*SELECT\b/i', $query)) $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        if (preg_match('/^\s*UPDATE\b/i', $query)) $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new CejuscTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn(): string => '2026-01-01 00:00:00');
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processos_partes (id TEXT PRIMARY KEY, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT, prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER, prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_prioridades (id TEXT PRIMARY KEY, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT)');
$db->exec('CREATE TABLE cejusc_registros (id TEXT PRIMARY KEY, numero_processo TEXT, classe_codigo TEXT, classe_descricao TEXT, nome_parte TEXT, data_envio_cejusc TEXT, criado_por TEXT, atualizado_por TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$base = '0000001' . '20248100001';
for ($i=0; $i<100; $i++) { $cnj = substr($base,0,7) . str_pad((string)$i,2,'0',STR_PAD_LEFT) . substr($base,7); if (CnjValidator::isValid($cnj)) break; }
$formatted = sprintf('%s-%s.%s.%s.%s.%s', substr($cnj,0,7),substr($cnj,7,2),substr($cnj,9,4),substr($cnj,13,1),substr($cnj,14,2),substr($cnj,16,4));
$stmt=$db->prepare('INSERT INTO processos VALUES (?, ?, NULL, NULL, 0, NULL, NULL)'); $stmt->execute(['p1',$formatted]);
$db->exec("INSERT INTO partes (id,nome_completo) VALUES ('a1','Pessoa Exemplo')");
$db->exec("INSERT INTO processos_partes (id,processo_id,parte_id) VALUES ('v1','p1','a1')");
$payload=['numero_processo'=>$formatted,'nome_parte'=>'Pessoa Exemplo','parte_id'=>'a1','data_envio_cejusc'=>'2026-01-01','usuario_id'=>'forjado'];
$service=new CejuscRegistrosService($db);
$id=$service->create($payload, 'sessao-criador');
$row=$service->find($id); check($row['criado_por']==='sessao-criador' && $row['atualizado_por']==='sessao-criador','Autoria da criação incorreta');
$payload['data_envio_cejusc']='2026-01-02';
check($service->update($id,$payload,'sessao-editor'),'Atualização não ocorreu');
$row=$service->find($id); check($row['criado_por']==='sessao-criador' && $row['atualizado_por']==='sessao-editor','Autoria da edição incorreta');
foreach (['create','update'] as $op) {
    try { $op==='create' ? $service->create($payload,' ') : $service->update($id,$payload,' '); throw new RuntimeException('Ator vazio aceito'); }
    catch (InvalidArgumentException $expected) {}
}
// Outro processo e outra parte forçam criação de registros auxiliares se a guarda falhar.
$otherBase = '0000002' . '20248100001';
for ($i=0; $i<100; $i++) { $otherCnj = substr($otherBase,0,7) . str_pad((string)$i,2,'0',STR_PAD_LEFT) . substr($otherBase,7); if (CnjValidator::isValid($otherCnj)) break; }
$novoPayload = $payload;
$novoPayload['numero_processo'] = $otherCnj;
$novoPayload['nome_parte'] = 'Pessoa Nova';
unset($novoPayload['parte_id']);
$insercoes = [];
$db->sqliteCreateFunction('registrar_insercao', static function (string $table) use (&$insercoes): int { $insercoes[] = $table; return 0; });
foreach (['processos', 'partes', 'processos_partes'] as $table) {
    $db->exec("CREATE TRIGGER observa_$table AFTER INSERT ON `$table` BEGIN SELECT registrar_insercao('$table'); END");
}
$before = [];
foreach (['processos', 'partes', 'processos_partes', 'processo_prioridades', 'cejusc_registros'] as $table) {
    $before[$table] = $db->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}
check($service->update('ausente', $novoPayload, 'sessao-editor') === false, 'ID inexistente não foi recusado');
foreach ($before as $table => $rows) {
    check($rows === $db->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC), "Efeitos indevidos em $table");
}
check($insercoes === [], 'Efeitos auxiliares foram iniciados para ID ausente');
check($service->update($id, $payload, 'sessao-editor'), 'Edição inalterada deve ser reconhecida');
$db->exec("CREATE TRIGGER falha_cejusc BEFORE UPDATE ON cejusc_registros BEGIN SELECT RAISE(ABORT, 'falha simulada'); END");
try {
    $service->update($id, $novoPayload, 'outro-editor');
    throw new RuntimeException('Falha simulada não propagada');
} catch (PDOException $expected) { check(str_contains($expected->getMessage(), 'falha simulada'), 'Falha anterior ao ponto injetado'); }
check($insercoes === ['processos', 'partes', 'processos_partes'], 'Cenário não exercitou gravações auxiliares');
foreach ($before as $table => $rows) {
    check($rows === $db->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC), "Rollback incompleto em $table");
}
check(!$db->inTransaction(), 'Transação permaneceu aberta');
echo "Autoria, ator vazio, edição ausente sem efeitos e edição legítima e rollback: OK\n";
