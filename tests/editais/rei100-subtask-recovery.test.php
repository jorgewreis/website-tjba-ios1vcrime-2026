<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/EditaisService.php';

function rei100Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, classe_id TEXT, numero_processo TEXT)');
$db->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, nome TEXT, codigo TEXT)');
$db->exec('CREATE TABLE feriados (id TEXT PRIMARY KEY, ativo INTEGER, data_inicio TEXT, data_fim TEXT, regra_data TEXT, descricao TEXT, tipo_feriado TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE editais (id TEXT PRIMARY KEY, processo_id TEXT, tipo_edital TEXT, prazo_dias INTEGER, data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT, status TEXT, observacoes TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE editais_subtarefas (id TEXT PRIMARY KEY, edital_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO editais VALUES ('e1', NULL, 'intimacao', 5, NULL, NULL, NULL, 'pendente', NULL, NULL, NULL)");

$db->exec("CREATE TRIGGER falha_segunda_etapa BEFORE INSERT ON editais_subtarefas WHEN NEW.ordem = 2 BEGIN SELECT RAISE(ABORT, 'falha sintetica segunda etapa'); END");
try {
    (new EditaisService($db))->criarPrimeiraSubtarefa('e1');
    throw new RuntimeException('Falha sintética da segunda etapa não ocorreu.');
} catch (PDOException $exception) {
    rei100Assert(str_contains($exception->getMessage(), 'falha sintetica segunda etapa'), 'Falha inesperada ao criar etapas: ' . $exception->getMessage());
}
rei100Assert((int) $db->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id='e1'")->fetchColumn() === 0, 'Rollback deixou etapa parcial');
rei100Assert($db->query("SELECT status FROM editais WHERE id='e1'")->fetchColumn() === 'pendente', 'Rollback deixou status parcial');

$db->exec('DROP TRIGGER falha_segunda_etapa');
(new EditaisService($db))->criarPrimeiraSubtarefa('e1');
$firstRows = $db->query("SELECT id, ordem FROM editais_subtarefas WHERE edital_id='e1' ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
rei100Assert(count($firstRows) === 2 && (int) $firstRows[0]['ordem'] === 1 && (int) $firstRows[1]['ordem'] === 2, 'Sucesso não criou exatamente as duas ordens');
(new EditaisService($db))->criarPrimeiraSubtarefa('e1');
$secondRows = $db->query("SELECT id, ordem FROM editais_subtarefas WHERE edital_id='e1' ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
rei100Assert($firstRows === $secondRows, 'Retentativa duplicou ou substituiu etapas');

$db->exec("INSERT INTO editais VALUES ('e2', NULL, 'intimacao', 5, NULL, NULL, NULL, 'pendente', NULL, NULL, NULL)");
$db->exec("INSERT INTO editais_subtarefas VALUES ('partial-1', 'e2', 'certificacao_publicacao', 1, 'em_andamento', NULL, NULL, NULL)");
(new EditaisService($db))->criarPrimeiraSubtarefa('e2');
$partial = $db->query("SELECT ordem, COUNT(*) total FROM editais_subtarefas WHERE edital_id='e2' GROUP BY ordem ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
rei100Assert(count($partial) === 2 && (int) $partial[0]['total'] === 1 && (int) $partial[1]['total'] === 1, 'Retentativa não recuperou o registro parcial');
rei100Assert($db->query("SELECT status FROM editais WHERE id='e2'")->fetchColumn() === 'aguardando publicação', 'Status não foi reconciliado ao recuperar etapa parcial');

echo "PASS REI-100: rollback, retentativa parcial e idempotência (SQLite em memória)\n";
