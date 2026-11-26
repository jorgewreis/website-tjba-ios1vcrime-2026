<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/services/DashboardResumoService.php';

final class Statement168 extends PDOStatement
{
    private int $offset=0;
    public function __construct(private array $rows=[],private mixed $column=0) {}
    public function execute(?array $params=null): bool { return true; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column=0): mixed { return $this->column; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed { return $this->rows[$this->offset++]??false; }
}
final class Connection168 extends PDO
{
    public int $snapshots=0;
    public bool $fail=false;
    public array $states=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if (str_contains($query,'INFORMATION_SCHEMA.TABLES')) return new Statement168([],1);
        return new Statement168();
    }
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'SELECT DATE(created_at) dia, COUNT(*) total FROM `cartas_expedidas`')) {
            return new Statement168([['dia'=>date('Y-m-d'), 'total'=>9]]);
        }
        if (str_starts_with($query,'SELECT f.*,CURRENT_DATE hoje')) {
            $this->snapshots++;
            if ($this->fail) throw new PDOException('synthetic failure');
            return new Statement168($this->states);
        }
        if (str_contains($query,'JOIN cartas_expedidas_subtarefas')) throw new RuntimeException('Independent old deadline query must not survive');
        return new Statement168();
    }
}
function assert168(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function queue168(array $result,string $type): ?int {
    foreach ($result['geral']['filas'] as $row) {
        if ($row['key']!=='cartas_expedidas') continue;
        if (!empty($row['error'])) return null;
        if (($row['type']??null)===$type) return $row['count'];
    }
    return 0;
}
$pdo=new Connection168();
$service=new DashboardResumoService($pdo);
$base=['id'=>'c','prazo_dias'=>5,'envio_em'=>'2026-11-20 10:00:00','hoje'=>'2026-11-24','diagnostico'=>null];
foreach ([['envio',1,0,0],['devolucao',0,1,1],['encerrada',0,0,0]] as [$stage,$send,$back,$soon]) {
    $pdo->states=[array_merge($base,['etapa'=>$stage])];
    $before=$pdo->snapshots;
    $result=$service->resumo('ficticio',['CARTORIO']);
    assert168($pdo->snapshots===$before+1,'One snapshot for queue and deadlines');
    assert168(queue168($result,'aguardando_envio')===$send && queue168($result,'aguardando_devolucao')===$back,'Transition counts');
    assert168($result['geral']['tarefas_abertas']===$send+$back,'Total no double counting');
    assert168($result['geral']['cartas_cadastradas']['expedidas']===9,'Historical registrations are independent from the open queue');
    assert168($result['prazos_proximos_7d']===$soon,'Pending executable return supplies deadline');
}
$pdo->states=[array_merge($base,['etapa'=>'envio','diagnostico'=>'devolucao_ausente'])];
$result=$service->resumo('ficticio',['CARTORIO']);
assert168(queue168($result,'aguardando_envio')===1 && !$result['fila_parcial'],'Legacy absent future step keeps known send');
$pdo->states=[array_merge($base,['etapa'=>'devolucao','envio_em'=>null])];
$result=$service->resumo('ficticio',['CARTORIO']);
assert168(queue168($result,'aguardando_devolucao')===1 && !$result['fila_parcial'],'Missing date retains known queue');
assert168($result['prazos_proximos_7d']===null && $result['setor']['prazos_vencidos']['expedidas']===null,'Missing date yields unknown deadlines');
$pdo->states=[array_merge($base,['etapa'=>'indeterminada','diagnostico'=>'etapas_duplicadas'])];
$result=$service->resumo('ficticio',['CARTORIO']);
assert168(queue168($result,'aguardando_envio')===null && $result['geral']['tarefas_abertas']===null && $result['fila_parcial'],'Ambiguous data never appears as zero');
assert168($result['geral']['cartas_cadastradas']['expedidas']===9,'Historical series is available despite an ambiguous operational record');
$pdo->states=[
    array_merge($base,['id'=>'stale','status'=>'em_andamento','etapa'=>'encerrada']),
    array_merge($base,['id'=>'terminal','status'=>'finalizada','etapa'=>'encerrada']),
    array_merge($base,['id'=>'open','status'=>'em_andamento','etapa'=>'envio']),
    array_merge($base,['id'=>'ambiguous','status'=>'em_andamento','etapa'=>'indeterminada','diagnostico'=>'envio_ausente']),
];
$before=$pdo->snapshots;
$result=$service->resumo('ficticio',['CARTORIO']);
assert168($pdo->snapshots===$before+1,'Operational queue/deadline calculations share one snapshot');
assert168($result['geral']['cartas_cadastradas']['expedidas']===9,'Historical registrations are stable across status combinations');
$pdo->states=[];
$result=$service->resumo('ficticio',['CARTORIO']);
assert168(queue168($result,'aguardando_envio')===0 && !$result['fila_parcial'],'Empty result known zero');
$pdo->fail=true;
$result=$service->resumo('ficticio',['CARTORIO']);
assert168(queue168($result,'aguardando_envio')===null && $result['prazos_proximos_7d']===null,'Query failure unknown');
$pdo->fail=false;
$pdo->states=[array_merge($base,['etapa'=>'envio'])];
$result=$service->resumo('ficticio',['CARTORIO','GABINETE'],'GABINETE');
assert168($result['setor']['filas']===[] && !$result['setor']['conectado'],'Sector filter preserved');
echo "PASS REI168: dashboard fake PDO, one snapshot, transfer, legacy, empty, failure, dates and sector. No database.\n";
