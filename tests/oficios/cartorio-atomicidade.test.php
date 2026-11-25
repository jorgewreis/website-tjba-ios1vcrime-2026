<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/OficiosCartorioService.php';

// Fixture SQLite em memória. O teste verifica atomicidade, não semântica de locks InnoDB.
final class OficiosCartorioAtomicidadePDO extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, classe_id TEXT)');
        $this->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, nome TEXT)');
        $this->exec('CREATE TABLE oficios_cartorio (id TEXT PRIMARY KEY, ano INTEGER, numero_sequencial INTEGER, status TEXT, atualizado_por TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE oficios_cartorio_subtarefas (id TEXT PRIMARY KEY, oficio_cartorio_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE cartorio_oficios_alocados (id TEXT PRIMARY KEY, oficio_id TEXT)');
        $this->exec("INSERT INTO oficios_cartorio VALUES ('O1', 2026, 1, 'pendente', NULL, NULL), ('O2', 2026, 2, 'pendente', NULL, NULL)");
        $this->exec("INSERT INTO oficios_cartorio_subtarefas VALUES ('S1', 'O1', 'enviar_oficio', 1, 'pendente', NULL), ('S2', 'O2', 'enviar_oficio', 1, 'pendente', NULL)");
        $this->exec("INSERT INTO cartorio_oficios_alocados VALUES ('A1', 'O1'), ('A2', 'O2')");
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

function expectOficio(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function snapshotOficios(PDO $db): array
{
    return [
        $db->query('SELECT * FROM oficios_cartorio ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM oficios_cartorio_subtarefas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM cartorio_oficios_alocados ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

// Falhas induzidas em cada escrita precisam reverter todo o cancelamento/exclusão.
foreach ([
    ['cancel', 'DELETE', 'oficios_cartorio_subtarefas'],
    ['cancel', 'UPDATE', 'oficios_cartorio'],
    ['delete', 'DELETE', 'oficios_cartorio_subtarefas'],
    ['delete', 'DELETE', 'cartorio_oficios_alocados'],
    ['delete', 'DELETE', 'oficios_cartorio'],
] as [$action, $operation, $table]) {
    $db = new OficiosCartorioAtomicidadePDO();
    $before = snapshotOficios($db);
    $db->exec("CREATE TRIGGER injected_failure BEFORE $operation ON `$table` BEGIN SELECT RAISE(ABORT, 'synthetic write failure'); END");
    try {
        (new OficiosCartorioService($db))->$action('O2');
        throw new RuntimeException("Falha não propagada: $action em $table");
    } catch (PDOException $error) {
        expectOficio(str_contains($error->getMessage(), 'synthetic write failure'), 'Erro inesperado ao injetar falha.');
        expectOficio(!$db->inTransaction(), 'Transação própria permaneceu aberta após falha.');
        expectOficio(snapshotOficios($db) === $before, "Rollback incompleto: $action em $table.");
    }
}

// A regra do último sequencial e a recusa de cancelamento finalizado não alteram dados.
$db = new OficiosCartorioAtomicidadePDO();
$before = snapshotOficios($db);
try {
    (new OficiosCartorioService($db))->delete('O1');
    throw new RuntimeException('Exclusão de ofício fora da última sequência foi aceita.');
} catch (InvalidArgumentException) {
    expectOficio(snapshotOficios($db) === $before, 'Recusa de sequência alterou dados.');
}
$db->exec("UPDATE oficios_cartorio SET status = 'finalizada' WHERE id = 'O2'");
$before = snapshotOficios($db);
try {
    (new OficiosCartorioService($db))->cancel('O2');
    throw new RuntimeException('Cancelamento de ofício finalizado foi aceito.');
} catch (InvalidArgumentException) {
    expectOficio(snapshotOficios($db) === $before, 'Recusa de cancelamento alterou dados.');
}

// Sucesso preserva filhos de outro ofício; cancelamento mantém as alocações do histórico.
$db = new OficiosCartorioAtomicidadePDO();
expectOficio((new OficiosCartorioService($db))->cancel('O2'), 'Cancelamento válido falhou.');
expectOficio($db->query("SELECT status FROM oficios_cartorio WHERE id = 'O2'")->fetchColumn() === 'cancelada', 'Status não foi atualizado.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio_subtarefas WHERE oficio_cartorio_id = 'O2'")->fetchColumn() === 0, 'Subtarefas do ofício cancelado permaneceram.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM cartorio_oficios_alocados WHERE oficio_id = 'O2'")->fetchColumn() === 1, 'Cancelamento alterou alocações do histórico.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio_subtarefas WHERE oficio_cartorio_id = 'O1'")->fetchColumn() === 1, 'Cancelamento alterou outro ofício.');
expectOficio((new OficiosCartorioService($db))->cancel('O2'), 'Cancelamento repetido deixou de ser idempotente.');

// Exclusão física remove todas as linhas filhas conhecidas e mantém as do registro anterior.
$db = new OficiosCartorioAtomicidadePDO();
$result = (new OficiosCartorioService($db))->delete('O2');
expectOficio(($result['deleted'] ?? false) === true, 'Exclusão válida falhou.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio WHERE id = 'O2'")->fetchColumn() === 0, 'Ofício não foi excluído.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio_subtarefas WHERE oficio_cartorio_id = 'O2'")->fetchColumn() === 0, 'Subtarefas ficaram órfãs.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM cartorio_oficios_alocados WHERE oficio_id = 'O2'")->fetchColumn() === 0, 'Alocações ficaram órfãs.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio WHERE id = 'O1'")->fetchColumn() === 1, 'Exclusão alterou outro ofício.');
expectOficio((int) $db->query("SELECT COUNT(*) FROM cartorio_oficios_alocados WHERE oficio_id = 'O1'")->fetchColumn() === 1, 'Exclusão alterou alocação de outro ofício.');

// O serviço usa savepoint, preserva a transação do chamador e permite rollback externo.
foreach (['cancel', 'delete'] as $action) {
    $db = new OficiosCartorioAtomicidadePDO();
    $before = snapshotOficios($db);
    $db->beginTransaction();
    $db->exec("INSERT INTO oficios_cartorio (id, ano, numero_sequencial, status) VALUES ('OUTSIDE', 2025, 1, 'pendente')");
    (new OficiosCartorioService($db))->$action('O2');
    expectOficio($db->inTransaction(), "$action encerrou a transação externa.");
    $db->rollBack();
    expectOficio(snapshotOficios($db) === $before, "Rollback externo não restaurou $action.");

    $db = new OficiosCartorioAtomicidadePDO();
    $before = snapshotOficios($db);
    $operation = $action === 'cancel' ? 'UPDATE' : 'DELETE';
    $db->exec("CREATE TRIGGER injected_outer_failure BEFORE $operation ON oficios_cartorio BEGIN SELECT RAISE(ABORT, 'outer synthetic failure'); END");
    $db->beginTransaction();
    $db->exec("INSERT INTO oficios_cartorio (id, ano, numero_sequencial, status) VALUES ('OUTSIDE', 2025, 1, 'pendente')");
    $beforeFailure = snapshotOficios($db);
    try {
        (new OficiosCartorioService($db))->$action('O2');
        throw new RuntimeException("Falha externa não ocorreu: $action");
    } catch (PDOException $error) {
        expectOficio(str_contains($error->getMessage(), 'outer synthetic failure'), 'Erro externo inesperado.');
        expectOficio($db->inTransaction(), "Savepoint encerrou a transação externa em $action.");
        expectOficio((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio WHERE id = 'OUTSIDE'")->fetchColumn() === 1, 'Rollback do savepoint apagou escrita externa anterior.');
        expectOficio(snapshotOficios($db) === $beforeFailure, "Savepoint deixou escrita parcial em $action.");
    }
    $db->rollBack();
}

echo "PASS REI-89: cancelamento e exclusão atômicos, regra sequencial, savepoint e preservação de outros ofícios (SQLite em memória).\n";
