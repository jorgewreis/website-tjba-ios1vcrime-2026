<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/OficiosCartorioService.php';

$db = class_exists(\Pdo\Sqlite::class) ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$now = static fn (): string => '2026-11-27 09:30:00';
if (method_exists($db, 'createFunction')) $db->createFunction('NOW', $now, 0);
else $db->sqliteCreateFunction('NOW', $now, 0);
$db->exec('CREATE TABLE oficios_cartorio (
    id TEXT PRIMARY KEY, processo_id TEXT, numero_processo TEXT, ano INTEGER,
    numero_sequencial INTEGER, tipo_oficio TEXT, forma_cumprimento TEXT, meio_envio TEXT,
    status TEXT, criado_por TEXT, atualizado_por TEXT, created_at TEXT, updated_at TEXT,
    UNIQUE(ano, numero_sequencial)
)');
// Não há outras tabelas: o número deve sair da mesma gravação, sem novo JOIN.
$service = new OficiosCartorioService($db);
$input = ['tipo_oficio' => 'Ofício Diversos', 'forma_cumprimento' => 'ccm', 'ano' => 2026];
$a = $service->createWithNumber($input);
$b = $service->create($input);
if ($a['numero_oficio'] !== '1/2026' || !preg_match('/^OFC-[0-9A-F]{26}$/D', $a['id'])
    || !preg_match('/^OFC-[0-9A-F]{26}$/D', $b) || $b === $a['id']) {
    throw new RuntimeException('Resposta não corresponde ao ofício gravado.');
}
$rows = $db->query('SELECT id, numero_sequencial, status FROM oficios_cartorio ORDER BY numero_sequencial')->fetchAll(PDO::FETCH_NUM);
if ($rows !== [[$a['id'], 1, 'finalizada'], [$b, 2, 'finalizada']]) {
    throw new RuntimeException('Numeração ou estado alterados.');
}
$db->exec("CREATE TRIGGER bloquear_insert BEFORE INSERT ON oficios_cartorio BEGIN SELECT RAISE(ABORT, 'falha sintética'); END;");
try {
    $service->createWithNumber($input);
    throw new RuntimeException('Falha de gravação não foi propagada.');
} catch (PDOException) {
    if ($db->inTransaction() || (int) $db->query('SELECT COUNT(*) FROM oficios_cartorio')->fetchColumn() !== 2) {
        throw new RuntimeException('Falha de gravação não reverteu a transação.');
    }
}
echo "OK: numeração no retorno, contrato anterior e rollback preservados.\n";
