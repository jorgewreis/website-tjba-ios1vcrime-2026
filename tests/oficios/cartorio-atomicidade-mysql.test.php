<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/OficiosCartorioService.php';

$dsn = getenv('REI89_TEST_DSN');
if (getenv('REI89_TEST_ISOLATION') !== 'isolated-rei89-test'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=127\.0\.0\.1;dbname=rei89_[a-f0-9]{32};charset=utf8mb4$/', $dsn)
    || getenv('REI89_TEST_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{32}$/', (string) getenv('REI89_TEST_PASSWORD'))) {
    throw new RuntimeException('Teste permitido somente pelo runner MySQL descartável REI-89.');
}

function expectRei89(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO($dsn, (string) getenv('REI89_TEST_USER'), (string) getenv('REI89_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$db->exec('CREATE TABLE oficios_cartorio (id VARCHAR(64) PRIMARY KEY, ano INT NOT NULL, numero_sequencial INT NOT NULL, status VARCHAR(32) NOT NULL, atualizado_por VARCHAR(64) NULL, updated_at DATETIME NULL)' . $engine);
$db->exec('CREATE TABLE oficios_cartorio_subtarefas (id VARCHAR(64) PRIMARY KEY, oficio_cartorio_id VARCHAR(64) NOT NULL, tipo VARCHAR(64), ordem INT, status VARCHAR(32), updated_at DATETIME NULL, KEY idx_subtarefas_oficio (oficio_cartorio_id))' . $engine);
$db->exec('CREATE TABLE cartorio_oficios_alocados (id VARCHAR(64) PRIMARY KEY, oficio_id VARCHAR(64) NOT NULL, KEY idx_alocados_oficio (oficio_id))' . $engine);

$seed = static function () use ($db): void {
    $db->exec('DELETE FROM cartorio_oficios_alocados');
    $db->exec('DELETE FROM oficios_cartorio_subtarefas');
    $db->exec('DELETE FROM oficios_cartorio');
    $db->exec("INSERT INTO oficios_cartorio VALUES ('O1', 2026, 1, 'pendente', NULL, NULL), ('O2', 2026, 2, 'pendente', NULL, NULL)");
    $db->exec("INSERT INTO oficios_cartorio_subtarefas VALUES ('S1', 'O1', 'enviar_oficio', 1, 'pendente', NULL), ('S2', 'O2', 'enviar_oficio', 1, 'pendente', NULL)");
    $db->exec("INSERT INTO cartorio_oficios_alocados VALUES ('A1', 'O1'), ('A2', 'O2')");
};
$snapshot = static fn (): array => [
    $db->query('SELECT * FROM oficios_cartorio ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $db->query('SELECT * FROM oficios_cartorio_subtarefas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $db->query('SELECT * FROM cartorio_oficios_alocados ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
];

foreach ([
    ['cancel', 'DELETE', 'oficios_cartorio_subtarefas'],
    ['cancel', 'UPDATE', 'oficios_cartorio'],
    ['delete', 'DELETE', 'oficios_cartorio_subtarefas'],
    ['delete', 'DELETE', 'cartorio_oficios_alocados'],
    ['delete', 'DELETE', 'oficios_cartorio'],
] as [$action, $operation, $table]) {
    $seed();
    $before = $snapshot();
    $db->exec("CREATE TRIGGER fail_rei89 BEFORE $operation ON `$table` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic REI-89 failure'");
    try {
        (new OficiosCartorioService($db))->$action('O2');
        throw new RuntimeException("Falha não propagada: $action em $table");
    } catch (PDOException $error) {
        expectRei89(str_contains($error->getMessage(), 'synthetic REI-89 failure'), 'Erro inesperado na falha induzida.');
        expectRei89(!$db->inTransaction(), 'Transação própria permaneceu aberta após falha.');
        expectRei89($snapshot() === $before, "Rollback MySQL incompleto: $action em $table.");
    }
    $db->exec('DROP TRIGGER fail_rei89');
}

$seed();
$before = $snapshot();
try {
    (new OficiosCartorioService($db))->delete('O1');
    throw new RuntimeException('Exclusão não respeitou o último sequencial.');
} catch (InvalidArgumentException) {
    expectRei89($snapshot() === $before, 'Recusa de sequência alterou dados.');
}

$seed();
$service = new OficiosCartorioService($db);
expectRei89($service->cancel('O2'), 'Cancelamento válido falhou.');
expectRei89((string) $db->query("SELECT status FROM oficios_cartorio WHERE id = 'O2'")->fetchColumn() === 'cancelada', 'Status não foi atualizado.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio_subtarefas WHERE oficio_cartorio_id = 'O2'")->fetchColumn() === 0, 'Cancelamento não removeu subtarefas.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM cartorio_oficios_alocados WHERE oficio_id = 'O2'")->fetchColumn() === 1, 'Cancelamento removeu alocações do histórico.');

$seed();
$result = (new OficiosCartorioService($db))->delete('O2');
expectRei89(($result['deleted'] ?? false) === true, 'Exclusão válida falhou.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio WHERE id = 'O2'")->fetchColumn() === 0, 'Ofício não foi excluído.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio_subtarefas WHERE oficio_cartorio_id = 'O2'")->fetchColumn() === 0, 'Subtarefas ficaram órfãs.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM cartorio_oficios_alocados WHERE oficio_id = 'O2'")->fetchColumn() === 0, 'Alocações ficaram órfãs.');
expectRei89((int) $db->query("SELECT COUNT(*) FROM oficios_cartorio WHERE id = 'O1'")->fetchColumn() === 1, 'Exclusão alterou outro ofício.');

// A chamada aninhada deve reverter só o próprio escopo e deixar a transação externa aberta.
$seed();
$before = $snapshot();
$db->beginTransaction();
$db->exec("INSERT INTO oficios_cartorio VALUES ('OUTSIDE', 2025, 1, 'pendente', NULL, NULL)");
$service = new OficiosCartorioService($db);
$service->cancel('O2');
expectRei89($db->inTransaction(), 'Serviço encerrou a transação externa.');
$db->rollBack();
expectRei89($snapshot() === $before, 'Rollback do chamador não restaurou o cancelamento.');

echo "PASS REI-89: rollback das gravações, regra sequencial, cancelamento e exclusão em fixture descartável MySQL 8.4/InnoDB.\n";
