<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';
require_once __DIR__ . '/../../app/services/DashboardResumoService.php';

function rei115Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('UUID', static fn (): string => 'test-uuid');
$pdo->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, classe_id TEXT, numero_processo TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, nome TEXT, codigo TEXT)');
$pdo->exec('CREATE TABLE feriados (id TEXT PRIMARY KEY, ativo INTEGER, data_inicio TEXT, data_fim TEXT)');
$pdo->exec('CREATE TABLE editais (id TEXT PRIMARY KEY, processo_id TEXT, tipo_edital TEXT, prazo_dias INTEGER, data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT, status TEXT, observacoes TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE editais_subtarefas (id TEXT PRIMARY KEY, edital_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE usuarios (id_usuario TEXT PRIMARY KEY, nome TEXT, sobrenome TEXT, email TEXT)');
$pdo->exec('CREATE TABLE dashboard_conclusoes (id TEXT, usuario_id TEXT, modulo TEXT, area TEXT, referencia_interna TEXT, concluido_em TEXT)');
$pdo->exec("INSERT INTO editais VALUES ('e1', NULL, 'edital', 5, NULL, NULL, NULL, 'aguardando publicação', NULL, NULL)");
$pdo->exec("INSERT INTO editais_subtarefas VALUES ('s1', 'e1', 'certificacao_publicacao', 1, 'em_andamento', '{\"antigo\":true}', NULL, NULL)");
$pdo->exec("INSERT INTO editais_subtarefas VALUES ('s2', 'e1', 'certificacao_decurso_prazo', 2, 'pendente', NULL, NULL, NULL)");

// Invalid publication must not persist submitted metadata (this failed before the fix).
try {
    (new EditaisService($pdo))->concluirSubtarefa('s1', '{"outro":"valor"}');
    throw new RuntimeException('Publicação sem data foi aceita.');
} catch (InvalidArgumentException $exception) {
    rei115Check(str_contains($exception->getMessage(), 'data da publicação'), 'rejeição inesperada');
}
$row = $pdo->query("SELECT status, dados_especificos FROM editais_subtarefas WHERE id='s1'")->fetch(PDO::FETCH_ASSOC);
rei115Check($row['status'] === 'em_andamento', 'rejeição alterou status');
rei115Check($row['dados_especificos'] === '{"antigo":true}', 'rejeição alterou dados específicos');

// A failure recording the dashboard must roll back the complete HTTP transition.
$pdo->exec("CREATE TRIGGER fail_dashboard BEFORE INSERT ON dashboard_conclusoes BEGIN SELECT RAISE(ABORT, 'synthetic dashboard failure'); END");
$beforeEdital = $pdo->query("SELECT * FROM editais WHERE id='e1'")->fetch(PDO::FETCH_ASSOC);
$beforeTask = $pdo->query("SELECT * FROM editais_subtarefas WHERE id='s1'")->fetch(PDO::FETCH_ASSOC);
$beforeNextTask = $pdo->query("SELECT * FROM editais_subtarefas WHERE id='s2'")->fetch(PDO::FETCH_ASSOC);
$pdo->beginTransaction();
try {
    $before = DashboardResumoService::statusAnterior($pdo, 'editais_subtarefas', 's1');
    (new EditaisService($pdo))->concluirSubtarefa('s1', json_encode(['data_publicacao' => date('Y-m-d'), 'novo' => true]));
    DashboardResumoService::registrarConclusao($pdo, $before, 'editais', 'CARTORIO', 'user-1');
    $pdo->commit();
    throw new RuntimeException('Falha sintética do dashboard não ocorreu.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    rei115Check(str_contains($exception->getMessage(), 'synthetic dashboard failure'), 'falha sintética inesperada: ' . $exception->getMessage());
}
$afterEdital = $pdo->query("SELECT * FROM editais WHERE id='e1'")->fetch(PDO::FETCH_ASSOC);
$afterTask = $pdo->query("SELECT * FROM editais_subtarefas WHERE id='s1'")->fetch(PDO::FETCH_ASSOC);
$afterNextTask = $pdo->query("SELECT * FROM editais_subtarefas WHERE id='s2'")->fetch(PDO::FETCH_ASSOC);
rei115Check($afterEdital === $beforeEdital, 'falha no dashboard deixou edital parcialmente alterado');
rei115Check($afterTask === $beforeTask, 'falha no dashboard deixou subtarefa parcialmente alterada');
rei115Check($afterNextTask === $beforeNextTask, 'falha no dashboard deixou próxima subtarefa parcialmente alterada');
rei115Check((int)$pdo->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() === 0, 'falha no dashboard deixou registro parcial');

// A valid transition commits the edital, task, and dashboard together; a retry does not duplicate the dashboard event.
$pdo->exec('DROP TRIGGER fail_dashboard');
$pdo->beginTransaction();
$before = DashboardResumoService::statusAnterior($pdo, 'editais_subtarefas', 's1');
$result = (new EditaisService($pdo))->concluirSubtarefa('s1', json_encode(['data_publicacao' => date('Y-m-d'), 'novo' => true]));
DashboardResumoService::registrarConclusao($pdo, $before, 'editais', 'CARTORIO', 'user-1');
$pdo->commit();
rei115Check(($result['tarefa_principal_concluida'] ?? true) === false, 'conclusão válida não avançou à etapa de decurso');
rei115Check($pdo->query("SELECT status FROM editais_subtarefas WHERE id='s1'")->fetchColumn() === 'concluida', 'conclusão válida não foi persistida');
rei115Check((int)$pdo->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() === 1, 'conclusão válida não registrou o dashboard');

$pdo->beginTransaction();
$before = DashboardResumoService::statusAnterior($pdo, 'editais_subtarefas', 's1');
(new EditaisService($pdo))->concluirSubtarefa('s1', json_encode(['data_publicacao' => date('Y-m-d'), 'novo' => true]));
DashboardResumoService::registrarConclusao($pdo, $before, 'editais', 'CARTORIO', 'user-1');
$pdo->commit();
rei115Check((int)$pdo->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() === 1, 'repetição duplicou o registro de dashboard');

echo "REI-115: rejeição sem escrita parcial, rollback de falha intermediária, sucesso e repetição passaram em SQLite memória.\n";
