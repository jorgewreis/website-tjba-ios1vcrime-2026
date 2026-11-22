<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/GuiasService.php';

// Adapt MySQL's UPDATE ... LIMIT 1 only; the fixture cannot prove MySQL locks.
class CadastroGuiasSqlite extends PDO
{
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        if (preg_match('/^UPDATE\s/i', ltrim($query))) $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}
final class CadastroGuiasFalhaRollback extends CadastroGuiasSqlite
{
    public function rollBack(): bool { throw new RuntimeException('rollback sintetico'); }
}
final class CadastroGuiasCommitAmbiguo extends CadastroGuiasSqlite
{
    public function commit(): bool
    {
        parent::commit();
        throw new RuntimeException('resposta de commit perdida');
    }
}
$db = new CadastroGuiasSqlite();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn(): string => '2026-11-20 00:00:00', 0);
$db->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT NOT NULL, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE guias_alocados (id TEXT PRIMARY KEY, guia_id TEXT, usuario_id TEXT, tipo TEXT, created_at TEXT)');
$db->exec('CREATE TABLE guias (id TEXT PRIMARY KEY, numero_processo TEXT, processo_id TEXT, denunciado TEXT, parte_id TEXT, tipo TEXT, etapa TEXT, status TEXT, regime TEXT, data_fato TEXT, dias_multa INTEGER, valor_multa REAL, observacoes TEXT, criado_por TEXT, atualizado_por TEXT, created_at TEXT, updated_at TEXT, user_cadastro TEXT)');

try {
    (new GuiasService($db))->create([
        'numero_processo' => '0000001-69.2020.8.05.0001',
        'denunciado' => 'Pessoa Sintetica Unica',
        'regime' => 'invalido',
    ]);
    throw new RuntimeException('Regime inválido foi aceito.');
} catch (InvalidArgumentException $expected) {
    if (!str_contains($expected->getMessage(), 'Regime inválido')) throw $expected;
}

if ((int)$db->query('SELECT COUNT(*) FROM partes')->fetchColumn() !== 0) {
    throw new RuntimeException('Cadastro rejeitado persistiu parte.');
}

// Missing processos simulates a persistence error after the part was inserted.
try {
    (new GuiasService($db))->create([
        'numero_processo' => '0000001-69.2020.8.05.0001',
        'denunciado' => 'Outra Pessoa Sintetica',
        'confirmar_nova_pessoa' => true,
        'regime' => 'Aberto',
    ]);
    throw new RuntimeException('Falha no armazenamento foi ocultada.');
} catch (PDOException $expected) {
    if (!str_contains($expected->getMessage(), 'processos')) throw $expected;
}
if ((int)$db->query('SELECT COUNT(*) FROM partes')->fetchColumn() !== 0) {
    throw new RuntimeException('Falha posterior persistiu parte.');
}

// Update creates an allocation before validating regime in the original code.
try {
    (new GuiasService($db))->update('GUI-TESTE', ['usuario_id' => 'USR-TESTE', 'regime' => 'invalido']);
    throw new RuntimeException('Edição inválida foi aceita.');
} catch (InvalidArgumentException $expected) {
    if (!str_contains($expected->getMessage(), 'Regime inválido')) throw $expected;
}
if ((int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 0) {
    throw new RuntimeException('Edição rejeitada persistiu alocação.');
}
$result = (new GuiasService($db))->update('GUI-AUSENTE', ['usuario_id' => 'USR-TESTE']);
if ($result !== false || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 0) {
    throw new RuntimeException('Edição sem alterações persistiu alocação.');
}
$db->exec("CREATE TRIGGER falha_alocacao BEFORE INSERT ON guias_alocados BEGIN SELECT RAISE(ABORT, 'falha sintetica'); END");
$db->exec("INSERT INTO guias (id, status) VALUES ('GUI-TESTE', 'pendente')");
try {
    (new GuiasService($db))->update('GUI-TESTE', ['usuario_id' => 'USR-TESTE', 'tipo' => 'Guia']);
    throw new RuntimeException('Erro de persistência de alocação foi ocultado.');
} catch (PDOException $expected) {
    if (!str_contains($expected->getMessage(), 'falha sintetica')) throw $expected;
}
$db->exec('DROP TRIGGER falha_alocacao');
$db->exec("DELETE FROM guias WHERE id='GUI-TESTE'");
if ($db->inTransaction() || (int)$db->query('SELECT COUNT(*) FROM guias_alocados')->fetchColumn() !== 0) {
    throw new RuntimeException('Falha de alocação não reverteu a edição.');
}

$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processos_partes (id TEXT PRIMARY KEY, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT, prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER, prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE guias_subtarefas (id TEXT PRIMARY KEY, guia_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE alocacoes_regras (id TEXT PRIMARY KEY, regra TEXT, descricao TEXT, usuario_id TEXT, usuario_atualiza_id TEXT, created_at TEXT, updated_at TEXT)');
$input = ['numero_processo' => '0000001-69.2020.8.05.0001', 'confirmar_nova_pessoa' => true, 'denunciado' => 'Pessoa Teste Tardia', 'regime' => 'Aberto'];
$db->exec("CREATE TRIGGER falha_quarta_subtarefa BEFORE INSERT ON guias_subtarefas WHEN NEW.ordem = 4 BEGIN SELECT RAISE(ABORT, 'falha tardia'); END");
try {
    (new GuiasService($db))->create($input);
    throw new RuntimeException('Falha tardia não foi propagada.');
} catch (PDOException $expected) {
    if (!str_contains($expected->getMessage(), 'falha tardia')) throw $expected;
}
foreach (['partes', 'processos', 'processos_partes', 'guias', 'guias_subtarefas'] as $table) {
    if ((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== 0) {
        throw new RuntimeException('Falha tardia deixou estado parcial.');
    }
}
$db->exec('DROP TRIGGER falha_quarta_subtarefa');
$id = (new GuiasService($db))->create($input);
if ($id === '' || (int)$db->query('SELECT COUNT(*) FROM guias')->fetchColumn() !== 1
    || (int)$db->query('SELECT COUNT(*) FROM guias_subtarefas')->fetchColumn() !== 8
    || (int)$db->query('SELECT COUNT(*) FROM processos_partes')->fetchColumn() !== 1) {
    throw new RuntimeException('Retentativa válida não persistiu conjunto esperado.');
}

$unsafe = new CadastroGuiasFalhaRollback();
$unsafe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    (new GuiasService($unsafe))->create(['numero_processo' => '0000001-69.2020.8.05.0001', 'denunciado' => 'Pessoa Teste', 'confirmar_nova_pessoa' => true]);
    throw new RuntimeException('Falha principal foi ignorada.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'partes')) throw $e;
}
try {
    (new GuiasService($unsafe))->create([]);
    throw new RuntimeException('Conexão com rollback incerto foi reutilizada.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Conexão indisponível para cadastro ou edição de guia.') throw $e;
}
$ambiguous = new CadastroGuiasCommitAmbiguo();
$ambiguous->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$ambiguous->exec('CREATE TABLE sintese_commit (id TEXT PRIMARY KEY)');
$transaction = new ReflectionMethod(GuiasService::class, 'executarTransacaoCadastroEdicao');
try {
    $transaction->invoke(new GuiasService($ambiguous), static function () use ($ambiguous): string {
        $ambiguous->exec("INSERT INTO sintese_commit VALUES ('confirmado')");
        return 'ok';
    });
    throw new RuntimeException('Commit ambíguo não foi sinalizado.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'resposta de commit perdida') throw $e;
}
if ((int)$ambiguous->query('SELECT COUNT(*) FROM sintese_commit')->fetchColumn() !== 1) {
    throw new RuntimeException('Fixture não simulou commit confirmado e resposta perdida.');
}
echo "PASS: validação, rollback de falhas, edição recusada e retentativa válida.\n";

foreach ([
    ['numero_processo'=>'0000001-69.2020.8.05.0001'],
    ['numero_processo'=>'123', 'denunciado'=>'Pessoa Sintetica', 'confirmar_nova_pessoa'=>true],
] as $invalidIdentity) {
    $before=[]; foreach (['partes','processos','processos_partes','guias','guias_subtarefas'] as $table) $before[$table]=(int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    try { (new GuiasService($db))->create($invalidIdentity); throw new RuntimeException('Entrada sem identidade ou CNJ válido foi aceita.'); }
    catch (InvalidArgumentException $expected) {}
    foreach ($before as $table=>$count) if ((int)$db->query("SELECT COUNT(*) FROM $table")->fetchColumn() !== $count) throw new RuntimeException('Entrada inválida alterou estado.');
}
echo "PASS REI-250: identidade obrigatória e CNJ inválido recusados sem gravações.\n";
