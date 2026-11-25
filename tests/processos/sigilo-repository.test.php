<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/repositories/ProcessosRepository.php';

// SQLite is local and in-memory. Only the MySQL-specific UPDATE LIMIT syntax
// is adapted; it does not establish MySQL transaction or concurrency behavior.
final class ProcessosSqlite extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->sqliteCreateFunction('REGEXP_REPLACE', static fn (string $value, string $pattern, string $replacement): string => preg_replace('/' . $pattern . '/', $replacement, $value), 3);
        $this->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT NOT NULL, classe_id TEXT, data_fato TEXT, sigiloso INTEGER NOT NULL, created_at TEXT, updated_at TEXT)');
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (preg_match('/^\s*UPDATE\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

function expectSigilo(PDO $db, string $id, int $expected, string $case): void
{
    $query = $db->prepare('SELECT sigiloso FROM processos WHERE id = ?');
    $query->execute([$id]);
    if ($query->fetchColumn() !== $expected) {
        throw new RuntimeException("Sigilo incorreto: $case");
    }
}

$db = new ProcessosSqlite();
$repo = new ProcessosRepository($db);
$cnj = '0000001-69.2020.8.05.0001'; // synthetic, valid check digits
$id = $repo->ensureProcess($cnj, null, null, true);
expectSigilo($db, $id, 1, 'novo sigiloso');
$repo->ensureProcess($cnj);
expectSigilo($db, $id, 1, 'omissão preserva sigilo');
$repo->ensureProcess($cnj, null, null, null);
expectSigilo($db, $id, 1, 'null preserva sigilo');
$repo->ensureProcess($cnj, null, null, false);
expectSigilo($db, $id, 0, 'alteração explícita para falso');
$repo->ensureProcess($cnj, null, null, true);
expectSigilo($db, $id, 1, 'alteração explícita para verdadeiro');
$repo->ensureProcess($cnj, null, null, false, true);
expectSigilo($db, $id, 1, 'preservar existente verdadeiro apesar de falso');
$repo->ensureProcess($cnj, null, null, false);
$repo->ensureProcess($cnj, null, null, true, true);
expectSigilo($db, $id, 0, 'preservar existente falso apesar de verdadeiro');
$repo->ensureProcess($cnj, null, null, true);

$db->beginTransaction();
$repo->ensureProcess($cnj, 'CLASSE-TESTE', null, false);
$db->rollBack();
expectSigilo($db, $id, 1, 'rollback de atualização');
$stmt = $db->prepare('SELECT classe_id FROM processos WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->fetchColumn() !== null) {
    throw new RuntimeException('Rollback não preservou a classe anterior.');
}

$otherCnj = '0000002-54.2020.8.05.0001';
$newId = $repo->ensureProcess($otherCnj, null, null, true, true);
expectSigilo($db, $newId, 1, 'novo processo com preservação de existente');
$db->exec("DELETE FROM processos WHERE numero_processo = '0000002-54.2020.8.05.0001'");
$db->beginTransaction();
$newId = $repo->ensureProcess($otherCnj);
expectSigilo($db, $newId, 0, 'novo processo com sigilo omitido');
$db->rollBack();
if ($repo->findByNumero($otherCnj) !== null) {
    throw new RuntimeException('Rollback não removeu a inserção do processo.');
}

echo "PASS sigilo e rollback do repositório (SQLite em memória; SQL adaptado)\n";
