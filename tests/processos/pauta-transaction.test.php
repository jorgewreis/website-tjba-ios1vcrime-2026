<?php
declare(strict_types=1);

// Impede que a fixture SQLite carregue configuração de um ambiente real.
if (is_file(__DIR__ . '/../../.env')) {
    throw new RuntimeException('Execute esta fixture somente em checkout sem .env.');
}
foreach (['APP_ENV' => 'fixture', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_NAME' => 'fixture_sqlite', 'DB_USER' => 'fixture', 'DB_PASS' => 'fixture'] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}

require_once __DIR__ . '/../../app/services/SalaAudienciasPautaService.php';

// Real transactions in an in-memory SQLite database. MySQL-specific UPDATE
// LIMIT is removed and NOW/REGEXP_REPLACE are emulated for this test only.
final class PautaTestDatabase extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->sqliteCreateFunction('REGEXP_REPLACE', static fn (string $s, string $p, string $r): string => preg_replace('/' . $p . '/', $r, $s), 3);
        $this->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, codigo TEXT, nome TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE sala_audiencias_pauta (id_pauta TEXT PRIMARY KEY, numero_sequencial INTEGER, ano INTEGER, processo TEXT, tipo_audiencia TEXT, data_audiencia TEXT, hora_audiencia TEXT, localizacao TEXT, status TEXT, observacoes TEXT, criado_por TEXT, atualizado_por TEXT, ativo INTEGER)');
        $this->exec("INSERT INTO processos VALUES ('PRC-TESTE', '0000001-69.2020.8.05.0001', 'CLASSE-ORIGINAL', NULL, 1, '', '')");
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*UPDATE\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

function assertUnchanged(PautaTestDatabase $db, int $expectedPautas): void
{
    $processo = $db->query("SELECT classe_id, sigiloso FROM processos WHERE id = 'PRC-TESTE'")->fetch(PDO::FETCH_ASSOC);
    if ($processo !== ['classe_id' => 'CLASSE-ORIGINAL', 'sigiloso' => 1]
        || (int) $db->query('SELECT COUNT(*) FROM classes')->fetchColumn() !== 0
        || (int) $db->query('SELECT COUNT(*) FROM sala_audiencias_pauta')->fetchColumn() !== $expectedPautas
        || $db->inTransaction()) {
        throw new RuntimeException('Pauta rejeitada ou falha deixou alterações parciais.');
    }
}

$payload = [
    'numero_processo' => '0000001-69.2020.8.05.0001',
    'classe_codigo' => 'TESTE88',
    'classe_descricao' => 'Classe de teste',
    'tipo_audiencia' => 'Instrução',
    'data_audiencia' => (new DateTimeImmutable('+10 days'))->format('Y-m-d'),
    'hora_audiencia' => '10:00',
    'localizacao' => 'Sala de teste',
];

$db = new PautaTestDatabase();
$db->exec("INSERT INTO sala_audiencias_pauta (id_pauta, processo, ativo, status, data_audiencia, hora_audiencia) VALUES ('ANTERIOR', 'PRC-TESTE', 1, 'designada', '2000-01-01', '09:00:00')");
try {
    (new SalaAudienciasPautaService($db))->create($payload, null);
    throw new RuntimeException('Pauta duplicada foi aceita.');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'Já existe audiência ativa')) {
        throw $e;
    }
}
assertUnchanged($db, 1);

$db = new PautaTestDatabase();
$db->exec("CREATE TRIGGER falha_pauta AFTER INSERT ON sala_audiencias_pauta BEGIN SELECT RAISE(ABORT, 'falha induzida na pauta'); END");
try {
    (new SalaAudienciasPautaService($db))->create($payload, null);
    throw new RuntimeException('Falha induzida não ocorreu.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'falha induzida na pauta')) {
        throw $e;
    }
}
assertUnchanged($db, 0);

$db = new PautaTestDatabase();
$db->exec("CREATE TRIGGER falha_pauta AFTER INSERT ON sala_audiencias_pauta BEGIN SELECT RAISE(ABORT, 'falha induzida na pauta'); END");
$novoPayload = $payload;
$novoPayload['numero_processo'] = '0000002-54.2020.8.05.0001';
try {
    (new SalaAudienciasPautaService($db))->create($novoPayload, null);
    throw new RuntimeException('Falha induzida para processo novo não ocorreu.');
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'falha induzida na pauta')) {
        throw $e;
    }
}
assertUnchanged($db, 0);
if ((int) $db->query('SELECT COUNT(*) FROM processos')->fetchColumn() !== 1) {
    throw new RuntimeException('Processo criado durante a pauta com falha não foi revertido.');
}

echo "PASS rejeição e falha de pauta preservam classe e sigilo (SQLite em memória; SQL adaptado)\n";
