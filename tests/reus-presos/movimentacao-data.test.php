<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/GabineteReusPresosService.php';

final class DateValidationPDO extends PDO
{
    public int $queries = 0;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->queries++;
        throw new RuntimeException('Consulta inesperada.');
    }
}

date_default_timezone_set('America/Bahia');
$db = new DateValidationPDO();
$service = new GabineteReusPresosService($db);
$today = new DateTimeImmutable('today');
foreach ([
    [$today->format('Y-m-d'), 'Réu obrigatório.'],
    [$today->modify('-1 day')->format('Y-m-d'), 'Réu obrigatório.'],
    [$today->modify('+1 day')->format('Y-m-d'), 'A data da movimentação não pode ser futura.'],
    ['2024-02-30', 'Informe uma data de movimentação válida.'],
    ['2024-2-01', 'Informe uma data de movimentação válida.'],
    ['', 'Data da movimentação é obrigatória.'],
] as [$date, $expected]) {
    try {
        $service->addMovimentacao(['data_movimentacao' => $date]);
        throw new RuntimeException('Esperava rejeição.');
    } catch (InvalidArgumentException $error) {
        if ($error->getMessage() !== $expected) throw $error;
    }
}
if ($db->queries !== 0) throw new RuntimeException('Validação consultou ou alterou banco.');

$sqlite = new PDO('sqlite::memory:');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sqlite->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00');
$sqlite->exec('CREATE TABLE gabinete_reu_preso (id TEXT, controle_tipo TEXT, status TEXT)');
$sqlite->exec('CREATE TABLE gabinete_reu_preso_processo (id TEXT, reu_preso_id TEXT, processo_id TEXT)');
$sqlite->exec('CREATE TABLE gabinete_reu_preso_movimentacao (id TEXT, reu_preso_id TEXT, reu_preso_processo_id TEXT, tipo_codigo TEXT, data_movimentacao TEXT, observacoes TEXT, created_at TEXT, user_cadastro TEXT)');
$sqlite->exec("INSERT INTO gabinete_reu_preso VALUES ('R1', 'principal', 'ativo')");
$sqlite->exec("INSERT INTO gabinete_reu_preso_processo VALUES ('L1', 'R1', 'P1')");
$service = new GabineteReusPresosService($sqlite);
$input = ['reu_preso_id' => 'R1', 'tipo_codigo' => 'outro', 'data_movimentacao' => $today->format('Y-m-d')];
if ($service->addMovimentacao($input) !== 1) throw new RuntimeException('Data atual não foi gravada.');
$input['data_movimentacao'] = $today->modify('+1 day')->format('Y-m-d');
try { $service->addMovimentacao($input); throw new RuntimeException('Data futura aceita.'); }
catch (InvalidArgumentException $error) {
    if ($error->getMessage() !== 'A data da movimentação não pode ser futura.') throw $error;
}
if ((int) $sqlite->query('SELECT COUNT(*) FROM gabinete_reu_preso_movimentacao')->fetchColumn() !== 1) {
    throw new RuntimeException('Rejeição alterou registros.');
}
echo "PASS datas e persistência sintética de movimentação (SQLite)\n";
