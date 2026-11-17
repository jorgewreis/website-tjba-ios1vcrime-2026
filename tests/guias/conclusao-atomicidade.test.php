<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/GuiasService.php';

// SQLite isolado, sem arquivos nem rede: não prova locks/concurrency do MySQL.
final class GuiasTestePDO extends PDO
{
    public array $sqlOriginal = [];
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00', 0);
        $this->sqliteCreateFunction('UUID', static fn (): string => bin2hex(random_bytes(16)), 0);
        $this->exec('CREATE TABLE guias (id TEXT PRIMARY KEY, numero_processo TEXT, denunciado TEXT, tipo TEXT, status TEXT, data_fato TEXT, dias_multa INTEGER, valor_multa REAL, updated_at TEXT)');
        $this->exec('CREATE TABLE guias_subtarefas (id TEXT PRIMARY KEY, guia_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE dashboard_conclusoes (id TEXT PRIMARY KEY, usuario_id TEXT, modulo TEXT, area TEXT, referencia_interna TEXT, concluido_em TEXT)');
        $this->exec('CREATE TABLE guias_alocados (id TEXT PRIMARY KEY, guia_id TEXT, usuario_id TEXT, tipo TEXT, created_at TEXT)');
        $this->exec("INSERT INTO guias (id, numero_processo, denunciado, tipo, status) VALUES ('G1', 'synthetic', 'Sintético', 'Guia', 'em_andamento')");
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->sqlOriginal[] = $query;
        $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
            $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

function guiasFixture(int $current): GuiasTestePDO
{
    $db = new GuiasTestePDO();
    $names = ['detalhamento_informacoes', 'calculo_pena_multa', 'expedicao_guia', 'aguardando_assinatura', 'juntada_automatica', 'gerar_pdf', 'envio_guia', 'juntada_termo_envio'];
    $insert = $db->prepare('INSERT INTO guias_subtarefas (id, guia_id, tipo, ordem, status, dados_especificos) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($names as $i => $name) {
        $n = $i + 1;
        $insert->execute(['ST' . $n, 'G1', $name, $n, $n < $current ? 'concluida' : ($n === $current ? 'em_andamento' : 'pendente'), null]);
    }
    return $db;
}

function estado(PDO $db): string
{
    return json_encode([
        $db->query('SELECT * FROM guias')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guias_subtarefas ORDER BY ordem')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM dashboard_conclusoes ORDER BY referencia_interna')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT * FROM guias_alocados ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_THROW_ON_ERROR);
}

function deveRecusar(PDO $db, string $subtarefa, ?string $payload): void
{
    $antes = estado($db);
    try {
        (new GuiasService($db))->concluirSubtarefa($subtarefa, $payload);
        throw new RuntimeException('Operação deveria ser recusada: ' . $subtarefa);
    } catch (InvalidArgumentException $exception) {
        if ($db->inTransaction() || estado($db) !== $antes) {
            throw new RuntimeException('Recusa alterou a guia ou as etapas.', 0, $exception);
        }
    }
}

$_SESSION = [];
deveRecusar(guiasFixture(1), 'ST1', '{"data_fato":"2999-01-01","dias_multa":3}');
deveRecusar(guiasFixture(1), 'ST1', '{"data_fato":"2025-02-30","dias_multa":3}');
deveRecusar(guiasFixture(3), 'ST3', '{}');
deveRecusar(guiasFixture(3), 'ST3', '{"tipo_guia":"inválido"}');
deveRecusar(guiasFixture(5), 'ST5', '{}');
deveRecusar(guiasFixture(5), 'ST5', '{"tipo_juntada":"inválido"}');
deveRecusar(guiasFixture(6), 'ST6', '{}');
deveRecusar(guiasFixture(6), 'ST6', '{"qtd_docs":3}');
deveRecusar(guiasFixture(7), 'ST7', '{}');
deveRecusar(guiasFixture(7), 'ST7', '{"tipo_envio":"invalid","destinatario":"invalid"}');
deveRecusar(guiasFixture(1), 'ST8', '{}');

$db = guiasFixture(3);
$service = new GuiasService($db);
$db->sqlOriginal = [];
$service->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva"}');
$lock = $write = null;
foreach ($db->sqlOriginal as $index => $sql) {
    if ($lock === null && preg_match('/^\s*SELECT\b.*\bFROM\s+`?guias`?\b.*\bFOR\s+UPDATE\s*$/is', $sql)) $lock = $index;
    if ($write === null && preg_match('/^\s*(UPDATE|INSERT|DELETE)\b/i', $sql)) $write = $index;
}
if ($lock === null || $write === null || $lock >= $write) {
    throw new RuntimeException('SQL de bloqueio da guia deve ser preparado antes da primeira escrita.');
}
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST3'")->fetchColumn() !== 'concluida'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST4'")->fetchColumn() !== 'em_andamento'
    || $db->query("SELECT tipo FROM guias WHERE id='G1'")->fetchColumn() !== 'Guia de Execução Definitiva') {
    throw new RuntimeException('Conclusão válida não avançou a etapa e o tipo.');
}
$antesRepeticao = estado($db);
$service->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Recolhimento Definitiva"}');
if ($db->inTransaction() || estado($db) !== $antesRepeticao) {
    throw new RuntimeException('Retentativa alterou uma etapa já concluída.');
}

$db = guiasFixture(1);
(new GuiasService($db))->concluirSubtarefa('ST1', '{}');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST2'")->fetchColumn() !== 'pulada'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST3'")->fetchColumn() !== 'em_andamento') {
    throw new RuntimeException('Detalhamento opcional não avançou corretamente.');
}

$db = guiasFixture(1);
(new GuiasService($db))->cancelarSubtarefa('ST1');
if ($db->query("SELECT status FROM guias_subtarefas WHERE id='ST1'")->fetchColumn() !== 'cancelada'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST2'")->fetchColumn() !== 'pulada'
    || $db->query("SELECT status FROM guias_subtarefas WHERE id='ST3'")->fetchColumn() !== 'em_andamento') {
    throw new RuntimeException('Cancellation did not advance the sequence atomically.');
}

$db = guiasFixture(8);
$service = new GuiasService($db);
$result = $service->concluirSubtarefa('ST8', '{}');
if (($result['tarefa_principal_concluida'] ?? null) !== true
    || $db->query("SELECT status FROM guias WHERE id='G1'")->fetchColumn() !== 'concluída') {
    throw new RuntimeException('Conclusão final não encerrou a guia.');
}
$antesRepeticao = estado($db);
$result = $service->concluirSubtarefa('ST8', '{}', 'USR-TESTE');
if (($result['tarefa_principal_concluida'] ?? null) !== true
    || ($result['tarefa_principal_mensagem'] ?? '') !== 'A guia já foi concluída.'
    || estado($db) !== $antesRepeticao
    || (int) $db->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() !== 0) {
    throw new RuntimeException('Retentativa da conclusão final não foi idempotente.');
}

$db = guiasFixture(3);
$antes = estado($db);
$db->exec("CREATE TRIGGER falha_etapa BEFORE UPDATE ON guias WHEN NEW.tipo = 'Guia de Execução Definitiva' BEGIN SELECT RAISE(ABORT, 'falha induzida'); END");
try {
    (new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva"}');
    throw new RuntimeException('Falha induzida não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'falha induzida') || $db->inTransaction() || estado($db) !== $antes) {
        throw new RuntimeException('Falha após escrita não reverteu a operação.', 0, $exception);
    }
}

$db = guiasFixture(3);
$antes = estado($db);
$db->exec("CREATE TRIGGER falha_alocacao BEFORE INSERT ON guias_alocados BEGIN SELECT RAISE(ABORT, 'falha de alocação induzida'); END");
try {
    (new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva","usuario_id":"USR-TESTE"}');
    throw new RuntimeException('Falha induzida de alocação não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'falha de alocação induzida') || $db->inTransaction() || estado($db) !== $antes) {
        throw new RuntimeException('Falha de alocação não reverteu a conclusão.', 0, $exception);
    }
}

$db = guiasFixture(3);
$service = new GuiasService($db);
$service->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva"}', 'USR-TESTE');
$eventos = $db->query('SELECT usuario_id, modulo, area, referencia_interna FROM dashboard_conclusoes')->fetchAll(PDO::FETCH_ASSOC);
if ($eventos !== [['usuario_id' => 'USR-TESTE', 'modulo' => 'guias', 'area' => 'CARTORIO', 'referencia_interna' => 'ST3']]) {
    throw new RuntimeException('Conclusão não registrou o evento esperado no dashboard.');
}
$service->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Recolhimento Definitiva"}', 'USR-TESTE');
if ((int) $db->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() !== 1) {
    throw new RuntimeException('Retentativa duplicou o evento do dashboard.');
}

$db = guiasFixture(3);
$antes = estado($db);
$db->exec("CREATE TRIGGER falha_dashboard BEFORE INSERT ON dashboard_conclusoes BEGIN SELECT RAISE(ABORT, 'falha no dashboard'); END");
try {
    (new GuiasService($db))->concluirSubtarefa('ST3', '{"tipo_guia":"Guia de Execução Definitiva"}', 'USR-TESTE');
    throw new RuntimeException('Falha no dashboard não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'falha no dashboard') || $db->inTransaction()
        || estado($db) !== $antes || (int) $db->query('SELECT COUNT(*) FROM dashboard_conclusoes')->fetchColumn() !== 0) {
        throw new RuntimeException('Falha no dashboard não reverteu a conclusão.', 0, $exception);
    }
}

echo "PASS conclusão de guias: recusa, sequência, retentativa e rollback (SQLite em memória)\n";
