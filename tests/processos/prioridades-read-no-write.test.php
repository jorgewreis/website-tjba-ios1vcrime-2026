<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/services/ProcessoPrioridadesService.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SKIP SQLite indisponível\n");
    exit(0);
}

final class PrioridadesReadTestPDO extends PDO
{
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace('TIMESTAMPDIFF(YEAR, p.`data_nascimento`, CURDATE()) < 18', '0 < 0', $query);
        if (preg_match('/^\s*(UPDATE|DELETE)\b/i', $query)) {
            $query = str_replace(' LIMIT 1', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

$pdo = new PrioridadesReadTestPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE processos_partes (id TEXT PRIMARY KEY, processo_id TEXT, parte_id TEXT, prioridade_crianca_adolescente INTEGER)');
$pdo->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, data_nascimento TEXT)');
$pdo->exec('CREATE TABLE processo_prioridades (id TEXT PRIMARY KEY, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT, UNIQUE(processo_id, codigo))');
$pdo->exec('CREATE TABLE gabinete_reu_preso_processo (id TEXT, processo_id TEXT, reu_preso_id TEXT, situacao TEXT)');
$pdo->exec('CREATE TABLE gabinete_reu_preso (id TEXT, status TEXT, controle_tipo TEXT)');
$pdo->exec('CREATE TABLE audit_writes (n INTEGER)');
$pdo->exec("CREATE TRIGGER track_insert AFTER INSERT ON processo_prioridades BEGIN INSERT INTO audit_writes VALUES (1); END");
$pdo->exec("CREATE TRIGGER track_update AFTER UPDATE ON processo_prioridades BEGIN INSERT INTO audit_writes VALUES (1); END");
$pdo->exec("CREATE TRIGGER track_delete AFTER DELETE ON processo_prioridades BEGIN INSERT INTO audit_writes VALUES (1); END");
$pdo->exec("INSERT INTO processos VALUES ('P1', '12345678901234567890', NULL, NULL, 0, NULL, NULL)");
$pdo->exec("INSERT INTO processo_prioridades VALUES ('R1', 'P1', 'crianca_adolescente', 'regra_menor', NULL, 'now')");
$pdo->exec("INSERT INTO processo_prioridades VALUES ('R2', 'P1', 'sigiloso', 'regra_derivada_menor_ou_vd', NULL, 'now')");
$pdo->exec("INSERT INTO processo_prioridades VALUES ('R3', 'P1', 'reu_preso', 'regra_reu_preso', NULL, 'now')");
$pdo->exec('DELETE FROM audit_writes');

$service = new ProcessoPrioridadesService($pdo);
$state = $service->getEstadoEdicaoPorProcessoId('P1');
$codes = array_column($state['prioridades'], 'codigo');
if (in_array('crianca_adolescente', $codes, true) || in_array('sigiloso', $codes, true)) {
    throw new RuntimeException('A projeção GET não removeu regras automáticas obsoletas.');
}
if ((int) $pdo->query('SELECT COUNT(*) FROM processo_prioridades')->fetchColumn() !== 3
    || (int) $pdo->query('SELECT COUNT(*) FROM audit_writes')->fetchColumn() !== 0) {
    throw new RuntimeException('A consulta GET alterou persistência de prioridades.');
}

$pdo->exec("INSERT INTO partes VALUES ('X1', NULL)");
$pdo->exec("INSERT INTO processos_partes VALUES ('PP1', 'P1', 'X1', 1)");
$pdo->exec("INSERT INTO processo_prioridades VALUES ('R4', 'P1', 'violencia_domestica', 'regra_violencia_domestica_mp', NULL, 'now')");
$pdo->exec('DELETE FROM audit_writes');
$state = $service->getEstadoEdicaoPorProcessoId('P1');
$byCode = [];
foreach ($state['prioridades'] as $priority) $byCode[$priority['codigo']] = $priority['origem'];
if (($byCode['crianca_adolescente'] ?? null) !== 'regra_menor'
    || !isset($byCode['sigiloso'])
    || (int) $pdo->query('SELECT COUNT(*) FROM processo_prioridades')->fetchColumn() !== 4
    || (int) $pdo->query('SELECT COUNT(*) FROM audit_writes')->fetchColumn() !== 0) {
    throw new RuntimeException('A consulta não projetou corretamente prioridades automáticas sem escrever no banco: ' . json_encode([$byCode, (int) $pdo->query('SELECT COUNT(*) FROM processo_prioridades')->fetchColumn(), (int) $pdo->query('SELECT COUNT(*) FROM audit_writes')->fetchColumn()]));
}

$pdo->exec("INSERT INTO gabinete_reu_preso VALUES ('G1', 'ativo', 'principal')");
$pdo->exec("INSERT INTO gabinete_reu_preso_processo VALUES ('GP1', 'P1', 'G1', 'preso')");
$pdo->exec('DELETE FROM audit_writes');
$state = $service->getEstadoEdicaoPorProcessoId('P1');
$byCode = [];
foreach ($state['prioridades'] as $priority) $byCode[$priority['codigo']] = $priority['origem'];
if (($byCode['reu_preso'] ?? null) !== 'regra_reu_preso'
    || (int) $pdo->query('SELECT COUNT(*) FROM processo_prioridades')->fetchColumn() !== 4
    || (int) $pdo->query('SELECT COUNT(*) FROM audit_writes')->fetchColumn() !== 0) {
    throw new RuntimeException('A projeção de réu preso falhou ou o GET persistiu a projeção.');
}

// A reconciliação permanece ativa nos fluxos mutáveis; comprova-se diretamente em SQLite.
$service->reconciliarAutomaticas('P1', '12345678901234567890');
$reu = $pdo->query("SELECT origem FROM processo_prioridades WHERE processo_id = 'P1' AND codigo = 'reu_preso'")->fetchColumn();
if ($reu !== 'regra_reu_preso' || (int) $pdo->query('SELECT COUNT(*) FROM audit_writes')->fetchColumn() === 0) {
    throw new RuntimeException('A reconciliação explícita não persistiu a prioridade de réu preso.');
}

echo "PASS prioridades automáticas (GET read-only para menor/réu preso/sigilo; reconciliação explícita persiste)\n";
