<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/CartasExpedidasService.php';
final class Rei168Pdo extends PDO {
    public array $parent = ['id'=>'C1','status'=>'em_andamento'];
    public array $tasks;
    public array $events=[];
    public array $log=[];
    public string $fail='';
    public bool $tx=false;
    public int $timeout=15;
    public string $engine='InnoDB';
    public function getAttribute(int $attribute): mixed {return 'mysql';}
    public function exec(string $statement): int|false {if($this->fail==='restore' && str_ends_with($statement,'15'))return false;$this->timeout=(int)substr($statement,strrpos($statement,' ')+1);return 0;}
    private array $snapshot=[];
    public function __construct(){ $this->tasks=[['id'=>'S1','carta_expedida_id'=>'C1','tipo'=>'aguardando_envio','ordem'=>1,'status'=>'em_andamento','updated_at'=>'old'],['id'=>'S2','carta_expedida_id'=>'C1','tipo'=>'aguardando_devolucao','ordem'=>2,'status'=>'pendente','updated_at'=>'old']]; }
    public function inTransaction(): bool {return $this->tx;}
    public function beginTransaction(): bool {if($this->fail==='begin')return false; $this->snapshot=[$this->parent,$this->tasks,$this->events];return $this->tx=true;}
    public function commit(): bool {if($this->fail==='commit')return false; $this->tx=false; return true;}
    public function rollBack(): bool {[$this->parent,$this->tasks,$this->events]=$this->snapshot;$this->tx=false;return true;}
    public function prepare(string $query, array $options=[]): PDOStatement|false { $this->log[]=$query;return new Rei168Statement($this,$query); }
}
final class Rei168Statement extends PDOStatement {
    private array $rows=[];
    private array $bound=[];
    public function __construct(private Rei168Pdo $db,private string $sql){}
    public function bindValue(string|int $param,mixed $value,int $type=PDO::PARAM_STR): bool {$this->bound[$param]=$value;return true;}
    public function execute(?array $params=null): bool {
        $p=$params??$this->bound;$q=$this->sql;
        if($q==='SELECT @@SESSION.lock_wait_timeout'){$this->rows=[['value'=>$this->db->timeout]];return true;}
        if($q==='SELECT @@lower_case_table_names'){$this->rows=[['value'=>0]];return true;}
        if(str_contains($q,'information_schema.TABLES')){$this->rows=[['ENGINE'=>$this->db->engine]];return true;}
        if(str_starts_with($q,'SELECT 1 FROM `'))return true;
        if(str_contains($q,'FROM `usuarios`')){$this->rows=[['nome'=>'Synthetic','sobrenome'=>'User']];return true;}
        if(str_starts_with($q,'SELECT carta_expedida_id')) {foreach($this->db->tasks as $t)if($t['id']===$p[':id'])$this->rows=[['carta_expedida_id'=>'C1']];}
        elseif(str_contains($q,'FROM cartas_expedidas_subtarefas'))$this->rows=$this->db->tasks;
        elseif(str_contains($q,'FROM cartas_expedidas'))$this->rows=[$this->db->parent];
        elseif(str_starts_with($q,'UPDATE cartas_expedidas_subtarefas')) {if($this->db->fail==='task')return false;foreach($this->db->tasks as &$t)if($t['id']===$p[':id']){$t['status']=$p[':status'];$t['updated_at']='new';if(isset($p[':dados']))$t['dados_especificos']=$p[':dados'];}}
        elseif(str_starts_with($q,'INSERT INTO cartas_expedidas_subtarefas')){$this->db->tasks[]=['id'=>$p[':id'],'carta_expedida_id'=>'C1','tipo'=>$p[':tipo'],'ordem'=>2,'status'=>$p[':status'],'updated_at'=>'new'];}
        elseif(str_starts_with($q,'UPDATE cartas_expedidas')){if($this->db->fail==='parent')throw new RuntimeException('synthetic parent failure');$this->db->parent['status']=$p[':status'];}
        elseif(str_starts_with($q,'INSERT INTO dashboard_conclusoes')){if($this->db->fail==='event')return false;$this->db->events[]=$p;}
        else throw new RuntimeException('Unmodeled SQL: '.$q);
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed{return array_shift($this->rows)??false;}
    public function fetchColumn(int $column=0): mixed{$row=$this->fetch();return $row===false?false:array_values($row)[$column];}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array{return $this->rows;}
    public function closeCursor(): bool{return true;}
}
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function rejected(callable $fn): void {try{$fn();}catch(Throwable){return;}throw new RuntimeException('Expected rejection');}
$_SESSION=['id_usuario'=>'TEST-USER'];
$db=new Rei168Pdo();$service=new CartasExpedidasService($db);
rejected(fn()=>$service->concluirSubtarefa('S2'));
check($db->tasks[0]['status']==='em_andamento'&&$db->events===[],'premature return wrote data');
$result=$service->concluirSubtarefa('S1');check(!$result['tarefa_principal_concluida']&&count($db->events)===1,'send transition');
check($db->parent['status']==='aguardando cumprimento'&&$db->tasks[1]['status']==='pendente','return eligibility');
$before=[$db->parent,$db->tasks,$db->events];check($service->concluirSubtarefa('S1')['repeticao'],'retry result');check($before===[$db->parent,$db->tasks,$db->events],'retry writes');
check($service->concluirSubtarefa('S2')['tarefa_principal_concluida'],'completion');
$before=[$db->parent,$db->tasks,$db->events];$service->concluirSubtarefa('S1');$service->concluirSubtarefa('S2');check($before===[$db->parent,$db->tasks,$db->events],'terminal retry regression');
foreach(['begin','task','parent','event','commit'] as $fail){$db=new Rei168Pdo();$db->fail=$fail;$before=[$db->parent,$db->tasks,$db->events];$svc=new CartasExpedidasService($db);rejected(fn()=>$svc->concluirSubtarefa('S1'));check($before===[$db->parent,$db->tasks,$db->events],'rollback '.$fail);$db->fail='';if(in_array($fail,['begin','commit'],true))rejected(fn()=>$svc->concluirSubtarefa('S1'));else check(!$svc->concluirSubtarefa('S1')['repeticao'],'retry after rollback');}
$db=new Rei168Pdo();array_pop($db->tasks);(new CartasExpedidasService($db))->concluirSubtarefa('S1');check(count($db->tasks)===2,'missing return created');
$db=new Rei168Pdo();$db->tasks[]=$db->tasks[0];rejected(fn()=>(new CartasExpedidasService($db))->concluirSubtarefa('S1'));check($db->events===[],'duplicate mutation');
$db=new Rei168Pdo();$db->beginTransaction();rejected(fn()=>(new CartasExpedidasService($db))->concluirSubtarefa('S1'));check($db->tx,'caller transaction changed');
$db=new Rei168Pdo();$db->engine='MyISAM';$svc=new CartasExpedidasService($db);rejected(fn()=>$svc->concluirSubtarefa('S1'));check($db->timeout===15 && $db->events===[] && $db->tasks[0]['status']==='em_andamento','engine refusal before write/restore');
$db=new Rei168Pdo();$db->fail='restore';$svc=new CartasExpedidasService($db);rejected(fn()=>$svc->concluirSubtarefa('S1'));$db->fail='';rejected(fn()=>$svc->concluirSubtarefa('S1'));check($db->events===[],'restore failure wrote');
$db=new Rei168Pdo();$db->tasks[]=['id'=>'OLD','tipo'=>'historico','ordem'=>9,'status'=>'old'];$svc=new CartasExpedidasService($db);rejected(fn()=>$svc->concluirSubtarefa('OLD'));$svc->concluirSubtarefa('S1');check(count($db->events)===1,'historical types inconsistent with classifier');
echo "PASS REI168 FakePDO transitions, retries, five failure stages, event atomicity, duplicates, outer transaction\n";
