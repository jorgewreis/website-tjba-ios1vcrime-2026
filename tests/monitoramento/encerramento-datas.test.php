<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/GabineteMonitoramentoEletronicoService.php';
final class MonitoramentoDatePDO extends PDO
{
    public function __construct() {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-01-01 00:00:00');
        $this->exec('CREATE TABLE processos (id TEXT, classe_id TEXT)');
        $this->exec('CREATE TABLE classes (id TEXT, nome TEXT)');
        $this->exec('CREATE TABLE gabinete_monitoramento_eletronico (id TEXT, processo_id TEXT, status TEXT, data_inicio TEXT, data_encerramento TEXT, atualizado_por TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE gabinete_monitoramento_eletronico_subtarefas (id TEXT, monitoramento_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, updated_at TEXT)');
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (preg_match('/^\s*UPDATE/i', $query)) $query = preg_replace('/\s+LIMIT\s+1\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}
date_default_timezone_set('America/Bahia');
$today = new DateTimeImmutable('today');
foreach ([$today->modify('-1 day')->format('Y-m-d'), $today->format('Y-m-d'), $today->modify('+1 day')->format('Y-m-d'), '2024-02-30', ''] as $start) {
    $db = new MonitoramentoDatePDO();
    $db->prepare('INSERT INTO gabinete_monitoramento_eletronico (id,status,data_inicio) VALUES (?,?,?)')->execute(['M1','em_andamento',$start]);
    $db->exec("INSERT INTO gabinete_monitoramento_eletronico_subtarefas VALUES ('S1','M1','encerrar_monitoramento',2,'em_andamento',NULL)");
    $service = new GabineteMonitoramentoEletronicoService($db);
    if ($start === '' || $start === '2024-02-30') {
        try { $service->concluirSubtarefa('S1', null); throw new RuntimeException('Início inválido foi aceito.'); }
        catch (InvalidArgumentException $error) { /* esperado */ }
        $status = $db->query("SELECT status FROM gabinete_monitoramento_eletronico_subtarefas WHERE id='S1'")->fetchColumn();
        if ($status !== 'em_andamento') throw new RuntimeException('Dados alterados em rejeição.');
        continue;
    }
    $service->concluirSubtarefa('S1', null);
    $row = $db->query("SELECT status,data_inicio,data_encerramento FROM gabinete_monitoramento_eletronico WHERE id='M1'")->fetch(PDO::FETCH_ASSOC);
    if ($row['status'] !== 'finalizada' || $row['data_encerramento'] !== max($start, $today->format('Y-m-d'))) {
        throw new RuntimeException('Data final inesperada para ' . $start);
    }
}
echo "PASS datas de encerramento e rejeições (SQLite em memória)\n";
