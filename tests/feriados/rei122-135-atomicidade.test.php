<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/FeriadosService.php';

function expect(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$dsn = getenv('FERIADOS_TEST_DSN');
$mysql = $dsn !== false;
if ($mysql && (getenv('FERIADOS_TEST_ISOLATION') !== 'isolated-feriados-test'
    || !preg_match('/^mysql:host=127\.0\.0\.1;dbname=feriados_[a-f0-9]{32};charset=utf8mb4$/', (string) $dsn)
    || getenv('FERIADOS_TEST_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{32}$/', (string) getenv('FERIADOS_TEST_PASSWORD')))) {
    throw new RuntimeException('Modo MySQL permitido somente pelo runner descartável de feriados.');
}
$pdo = $mysql
    ? new PDO((string) $dsn, (string) getenv('FERIADOS_TEST_USER'), (string) getenv('FERIADOS_TEST_PASSWORD'))
    : new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$engine = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
if (!$mysql) {
    $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn (string $a, string $b): int => strcasecmp($a, $b));
    $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
}
$pdo->exec('CREATE TABLE feriados (id VARCHAR(64) PRIMARY KEY, tipo_feriado VARCHAR(32), data_inicio DATE, data_fim DATE, regra_data VARCHAR(32), descricao VARCHAR(255), ativo INTEGER, created_at DATETIME NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE editais (id VARCHAR(64) PRIMARY KEY, processo_id VARCHAR(64) NULL, status VARCHAR(64), tipo_edital VARCHAR(64), prazo_dias INTEGER, data_publicacao DATE NULL, data_inicio_contagem DATE NULL, data_decurso_prazo DATE NULL, observacoes TEXT NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE editais_subtarefas (id VARCHAR(64) PRIMARY KEY, edital_id VARCHAR(64), tipo VARCHAR(64), ordem INTEGER, status VARCHAR(32), dados_especificos TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL)' . $engine);
$pdo->exec('CREATE TABLE processos (id VARCHAR(64), classe_id VARCHAR(64), numero_processo VARCHAR(64))' . $engine);
$pdo->exec('CREATE TABLE classes (id VARCHAR(64), nome VARCHAR(128), codigo VARCHAR(32))' . $engine);
$pdo->exec("INSERT INTO editais VALUES ('E-1', NULL, 'aguardando decurso', 'comum', 2, '2026-11-16', '2026-11-17', '2026-11-19', NULL, NULL)");
$pdo->exec("INSERT INTO editais VALUES ('E-2', NULL, 'aguardando decurso', 'comum', 2, '2026-11-16', '2026-11-18', '2026-11-20', NULL, NULL)");

$service = new FeriadosService($pdo);
$created = $service->create([
    'tipo_feriado' => 'municipal', 'data_inicio' => '2026-11-17', 'data_fim' => '2026-11-17',
    'regra_data' => 'unica', 'descricao' => 'fixture', 'ativo' => 1,
]);
$deadline = (string) $pdo->query("SELECT data_decurso_prazo FROM editais WHERE id = 'E-1'")->fetchColumn();
expect($created['editais_decurso_recalculados'] === 2 && $deadline === '2026-11-20', 'criação deve recalcular ambos editais afetados');

$beforeHoliday = (string) $pdo->query('SELECT COUNT(*) FROM feriados')->fetchColumn();
$beforeDeadlines = $pdo->query("SELECT id, data_decurso_prazo FROM editais WHERE id IN ('E-1', 'E-2') ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
if ($mysql) {
    $pdo->exec("CREATE TRIGGER fail_edital_update BEFORE UPDATE ON editais FOR EACH ROW BEGIN IF OLD.id = 'E-2' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected failure'; END IF; END");
} else {
    $pdo->exec("CREATE TRIGGER fail_edital_update BEFORE UPDATE ON editais WHEN OLD.id = 'E-2' BEGIN SELECT RAISE(ABORT, 'injected failure'); END");
}
try {
    $service->update($created['id'], [
        'tipo_feriado' => 'municipal', 'data_inicio' => '2026-11-18', 'data_fim' => '2026-11-18',
        'regra_data' => 'unica', 'descricao' => 'moved', 'ativo' => 1,
    ]);
    throw new RuntimeException('falha injetada não foi propagada');
} catch (PDOException $error) {
    expect(str_contains($error->getMessage(), 'injected failure'), 'erro injetado esperado');
}
$afterHoliday = (string) $pdo->query('SELECT COUNT(*) FROM feriados')->fetchColumn();
$afterDeadlines = $pdo->query("SELECT id, data_decurso_prazo FROM editais WHERE id IN ('E-1', 'E-2') ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
expect($afterHoliday === $beforeHoliday && $afterDeadlines === $beforeDeadlines, 'falha após atualizar o primeiro edital deve reverter feriado e ambos os editais');

$pdo->exec('DROP TRIGGER fail_edital_update');
$updated = $service->update($created['id'], [
    'tipo_feriado' => 'municipal', 'data_inicio' => '2026-11-18', 'data_fim' => '2026-11-18',
    'regra_data' => 'unica', 'descricao' => 'moved', 'ativo' => 1,
]);
expect($updated['editais_decurso_recalculados'] === 2, 'atualização deve recalcular o intervalo anterior e o novo sem duplicar os editais');
$service->delete($created['id']);
expect((int) $pdo->query('SELECT COUNT(*) FROM feriados')->fetchColumn() === 0, 'exclusão deve persistir após recálculo');

$reactivated = $service->create([
    'tipo_feriado' => 'municipal', 'data_inicio' => '2026-11-17', 'data_fim' => '2026-11-17',
    'regra_data' => 'unica', 'descricao' => 'deactivation-fixture', 'ativo' => 1,
]);
expect($reactivated['editais_decurso_recalculados'] === 2, 'feriado de ativação deve recalcular os dois editais');
$deactivated = $service->update($reactivated['id'], [
    'tipo_feriado' => 'municipal', 'data_inicio' => '2026-11-17', 'data_fim' => '2026-11-17',
    'regra_data' => 'unica', 'descricao' => 'deactivation-fixture', 'ativo' => 0,
]);
$afterDeactivation = $pdo->query("SELECT data_inicio_contagem, data_decurso_prazo FROM editais WHERE id = 'E-1'")->fetch(PDO::FETCH_NUM);
expect($deactivated['editais_decurso_recalculados'] === 2, 'desativação deve recalcular pelo intervalo antigo');
expect($afterDeactivation === ['2026-11-17', '2026-11-19'], 'desativação deve remover o feriado antigo do cálculo');

$pdo->exec("INSERT INTO feriados VALUES ('FER-aaaaaaaaaaaaaaaaaaaaaaaaaa', 'municipal', '2026-11-17', '2026-11-17', 'unica', 'fixture', 1, NULL, NULL)");
$legacyRemoval = $service->delete('FER-aaaaaaaaaaaaaaaaaaaaaaaaaa');
$legacy = $pdo->query("SELECT data_inicio_contagem, data_decurso_prazo FROM editais WHERE id = 'E-2'")->fetch(PDO::FETCH_NUM);
expect($legacyRemoval['editais_decurso_recalculados'] === 2, 'remoção deve encontrar ambos editais afetados, inclusive o feriado anterior ao início armazenado');
expect($legacy === ['2026-11-17', '2026-11-19'], 'remoção deve reconstruir datas a partir da publicação original');

$pdo->exec("INSERT INTO feriados VALUES ('FER-bbbbbbbbbbbbbbbbbbbbbbbbbb', 'municipal', '2026-11-17', '2026-11-18', 'unica', 'consecutivos', 1, NULL, NULL)");
$pdo->exec("INSERT INTO editais VALUES ('E-3', NULL, 'aguardando decurso', 'comum', 2, '2026-11-16', '2026-11-19', '2026-11-23', NULL, NULL)");
$pdo->exec("INSERT INTO editais VALUES ('E-4', NULL, 'aguardando decurso', 'comum', 2, '2026-11-09', '2026-11-10', '2026-11-12', NULL, NULL)");
$consecutiveRemoval = $service->delete('FER-bbbbbbbbbbbbbbbbbbbbbbbbbb');
$consecutive = $pdo->query("SELECT data_inicio_contagem, data_decurso_prazo FROM editais WHERE id = 'E-3'")->fetch(PDO::FETCH_NUM);
$unaffected = $pdo->query("SELECT data_inicio_contagem, data_decurso_prazo FROM editais WHERE id = 'E-4'")->fetch(PDO::FETCH_NUM);
expect($consecutiveRemoval['editais_decurso_recalculados'] === 3, 'intervalo de feriados consecutivos deve recalcular os três editais impactados');
expect($consecutive === ['2026-11-17', '2026-11-19'], 'remoção de feriados consecutivos deve refazer a origem da contagem');
expect($unaffected === ['2026-11-10', '2026-11-12'], 'edital vencido antes do feriado deve permanecer inalterado');

$pdo->exec("INSERT INTO feriados VALUES ('FER-cccccccccccccccccccccccccc', 'municipal', '2026-11-17', '2026-11-17', 'unica', 'legacy', 1, NULL, NULL)");
$pdo->exec("INSERT INTO editais VALUES ('E-5', NULL, 'aguardando decurso', 'comum', 2, NULL, '2026-11-18', '2026-11-20', NULL, NULL)");
$legacyJson = json_encode(['data_publicacao' => '2026-11-16'], JSON_THROW_ON_ERROR);
$statement = $pdo->prepare('INSERT INTO editais_subtarefas VALUES (?, ?, ?, ?, ?, ?, NULL, NULL)');
$statement->execute(['EDS-legacy', 'E-5', 'certificacao_publicacao', 1, 'concluida', $legacyJson]);
$legacyOnly = $service->delete('FER-cccccccccccccccccccccccccc');
$legacyOnlyDates = $pdo->query("SELECT data_inicio_contagem, data_decurso_prazo FROM editais WHERE id = 'E-5'")->fetch(PDO::FETCH_NUM);
expect($legacyOnly['editais_decurso_recalculados'] === 4, 'remoção deve incluir publicação recuperada da subtarefa legada');
expect($legacyOnlyDates === ['2026-11-17', '2026-11-19'], 'publicação legada deve reconstruir o início e vencimento sem o feriado');

if ($mysql) {
    $secondConnection = new PDO((string) getenv('FERIADOS_TEST_DSN'), (string) getenv('FERIADOS_TEST_USER'), (string) getenv('FERIADOS_TEST_PASSWORD'));
    expect((int) $pdo->query("SELECT GET_LOCK('fokus-law-feriados-recalc', 0)")->fetchColumn() === 1, 'primeira conexão deve obter o mutex global');
    expect((int) $secondConnection->query("SELECT GET_LOCK('fokus-law-feriados-recalc', 1)")->fetchColumn() === 0, 'segunda conexão deve aguardar o mutex já mantido');
    expect((int) $pdo->query("SELECT RELEASE_LOCK('fokus-law-feriados-recalc')")->fetchColumn() === 1, 'mutex global deve ser liberado pela conexão proprietária');
}

echo 'REI-122/135: criação, atualização, exclusão, publicação legada, feriados consecutivos, não afetados, rollback atômico e mutex ' . ($mysql ? 'verificados em MySQL/InnoDB' : 'não aplicável') . " isolado.\n";
