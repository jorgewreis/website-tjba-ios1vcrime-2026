<?php
declare(strict_types=1);
// Deliberately refuses TCP, application configuration and any pre-existing database.
$dsn='mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei168_test;charset=utf8mb4';
if (getenv('REI168_TEST_DISPOSABLE')!=='1' || getenv('REI168_MYSQL_DSN')!==$dsn || getenv('REI168_TEST_REPO_ROOT')!=='/repo') throw new RuntimeException('Disposable fixture required');
$marker=(string)getenv('REI168_TEST_DISPOSABLE_MARKER');
if (!preg_match('/^[a-f0-9]{64}$/',$marker)) throw new RuntimeException('Invalid marker');
$pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='rei168_test' || !str_starts_with((string)$pdo->query('SELECT VERSION()')->fetchColumn(),'8.4.')) throw new RuntimeException('Unexpected database');
$worker=($argv[1]??'')==='--race-worker';
$expectedTables=$worker ? ['cartas_expedidas','cartas_expedidas_subtarefas','classes','comarcas','dashboard_conclusoes','processos','rei168_test_guard','tribunais','usuarios','varas'] : ['rei168_test_guard'];
if ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)!==$expectedTables || !hash_equals($marker,(string)$pdo->query('SELECT marker FROM rei168_test_guard')->fetchColumn())) throw new RuntimeException('Sentinel/schema mismatch');
if ((string)$pdo->query('SELECT @@lower_case_table_names')->fetchColumn()!==getenv('REI168_EXPECT_LOWER_CASE')) throw new RuntimeException('Wrong case mode');
require_once '/repo/app/services/CartasExpedidasService.php';
require_once '/repo/app/helpers/CartasExpedidasEstadoFluxo.php';
if ($worker) {
    $_SESSION=['id_usuario'=>'fixture'];
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 15');
    echo json_encode(['ready'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR).PHP_EOL;
    flush();
    $result=(new CartasExpedidasService($pdo))->concluirSubtarefa('race-s','{"race":"same-request"}');
    echo json_encode(['result'=>$result],JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$assertions=0;
function check168(bool $ok,string $message):void { global $assertions; $assertions++; if(!$ok) throw new RuntimeException($message); }
$schema=[
'usuarios (id_usuario VARCHAR(128) PRIMARY KEY,nome VARCHAR(100),sobrenome VARCHAR(100),email VARCHAR(150))',
'classes (id VARCHAR(30) PRIMARY KEY,codigo VARCHAR(80) UNIQUE,nome VARCHAR(200),created_at DATETIME,updated_at DATETIME)',
'processos (id VARCHAR(30) PRIMARY KEY,numero_processo VARCHAR(32) UNIQUE,classe_id VARCHAR(30),data_fato DATE,sigiloso TINYINT DEFAULT 0,created_at DATETIME,updated_at DATETIME)',
 'tribunais (id VARCHAR(30) PRIMARY KEY,sigla VARCHAR(16) UNIQUE,nome VARCHAR(200),created_at DATETIME,updated_at DATETIME)',
 'comarcas (id VARCHAR(30) PRIMARY KEY,tribunal_id VARCHAR(30),uf CHAR(2),nome VARCHAR(200),cidade VARCHAR(200),status_cidade VARCHAR(32),created_at DATETIME,updated_at DATETIME,UNIQUE KEY uq_comarca(tribunal_id,uf,nome))',
 'varas (id VARCHAR(30) PRIMARY KEY,comarca_id VARCHAR(30),codigo VARCHAR(80) DEFAULT \'\',nome VARCHAR(200),email VARCHAR(200) DEFAULT \'\',telefone VARCHAR(80) DEFAULT \'\',created_at DATETIME,updated_at DATETIME)',
'cartas_expedidas (id VARCHAR(30) PRIMARY KEY,processo_origem_id VARCHAR(30),numero_processo_origem VARCHAR(30),finalidade VARCHAR(100),prazo_dias INT,cidade_destino VARCHAR(100),uf_destino CHAR(2),status VARCHAR(50),created_at DATETIME,updated_at DATETIME,criado_por VARCHAR(128),atualizado_por VARCHAR(128),comarca_destino_id VARCHAR(30),numero_processo_destino VARCHAR(32),vara_destino_id VARCHAR(30),vara_destino_nome VARCHAR(200),observacoes TEXT)',
'cartas_expedidas_subtarefas (id VARCHAR(30) PRIMARY KEY,carta_expedida_id VARCHAR(30),tipo VARCHAR(60),ordem INT,status VARCHAR(30),dados_especificos TEXT,created_at DATETIME,updated_at DATETIME, FOREIGN KEY(carta_expedida_id) REFERENCES cartas_expedidas(id))',
'dashboard_conclusoes (id VARCHAR(36) PRIMARY KEY,usuario_id VARCHAR(128),modulo VARCHAR(80),area VARCHAR(30),referencia_interna VARCHAR(30),concluido_em DATETIME)'];
foreach($schema as $ddl) $pdo->exec('CREATE TABLE '.$ddl.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$pdo->exec("INSERT INTO usuarios VALUES ('fixture','Synthetic','User','fixture@example.invalid')");
$_SESSION=['id_usuario'=>'fixture'];
function seed168(PDO $pdo,string $id,string $send='em_andamento',?string $return='pendente',string $date='2026-11-01 00:00:00',string $parent='em_andamento'):void {
 $pdo->prepare('INSERT INTO cartas_expedidas (id,numero_processo_origem,finalidade,prazo_dias,cidade_destino,uf_destino,status,created_at,updated_at) VALUES (?, ?, ?,30, ?, ?, ?, ?, ?)')->execute([$id,'synthetic-'.$id,'teste','Cidade','BA',$parent,$date,$date]);
 $st=$pdo->prepare('INSERT INTO cartas_expedidas_subtarefas VALUES (?,?,?,?,?,NULL,?,?)');
 $st->execute([$id.'-s',$id,'aguardando_envio',1,$send,$date,$date]);
 if($return!==null) $st->execute([$id.'-r',$id,'aguardando_devolucao',2,$return,$date,$date]);
}
function snapshot168(PDO $pdo):array { return [$pdo->query('SELECT * FROM cartas_expedidas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),$pdo->query('SELECT * FROM cartas_expedidas_subtarefas ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),$pdo->query('SELECT * FROM dashboard_conclusoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)]; }
seed168($pdo,'queued'); seed168($pdo,'near','concluida','pendente','2026-10-27 00:00:00','aguardando cumprimento');
seed168($pdo,'late','concluida','em_andamento','2026-10-01 00:00:00','aguardando cumprimento');
seed168($pdo,'done','concluida','concluida','2026-10-01 00:00:00','concluída');
$repo=new CartasExpedidasRepository($pdo); $subs=new CartasExpedidasSubtarefasRepository($pdo); $service=new CartasExpedidasService($pdo);
$before=snapshot168($pdo);
$rows=$pdo->query(CartasExpedidasEstadoFluxo::consultaEstados())->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) { $php=CartasExpedidasEstadoFluxo::classificar($subs->findByCartaId($row['id']),$row['status']); check168($php['etapa']===$row['etapa'] && $php['diagnostico']===$row['diagnostico'],'PHP/SQL classification '.$row['id']); }
$summary=CartasExpedidasEstadoFluxo::resumir($rows,new DateTimeImmutable('2026-11-24'));
check168($summary['envios']===1 && $summary['devolucoes']===2,'Queue counters');
check168($summary['proximos']===1 && $summary['vencidos']===1 && $summary['maior_atraso']===24,'Deadline counters');
foreach(['aguardando_envio'=>['queued'],'aguardando_cumprimento'=>['late','near']] as $filter=>$expected) {
 $filters=['status'=>$filter]; $ids=array_column($repo->fetchList($filters,100,0),'IdCarta'); sort($ids);
 check168($ids===$expected,'Filtered list '.$filter); check168($repo->count($filters)===count($expected),'Filtered count '.$filter);
 $pages=[]; for($offset=0;$offset<count($expected);$offset++) $pages=array_merge($pages,array_column($repo->fetchList($filters,1,$offset),'IdCarta'));
 sort($pages); check168($pages===$expected,'Pagination '.$filter);
}
check168(snapshot168($pdo)===$before,'Reads must not mutate');
try { $service->concluirSubtarefa('queued-r'); throw new LogicException('premature return accepted'); } catch(InvalidArgumentException $e) {}
check168(snapshot168($pdo)===$before,'Rejected future step preserved state');
$r=$service->concluirSubtarefa('queued-s','{"test":"sent"}'); check168($r['repeticao']===false && !$r['tarefa_principal_concluida'],'First send');
$afterSend=snapshot168($pdo); sleep(1);
$r=$service->concluirSubtarefa('queued-s','{"test":"overwrite forbidden"}');
check168($r['repeticao']===true && snapshot168($pdo)===$afterSend,'Send retry unchanged timestamp data parent events');
check168((int)$pdo->query("SELECT COUNT(*) FROM dashboard_conclusoes WHERE referencia_interna='queued-s'")->fetchColumn()===1,'One send event');
$r=$service->concluirSubtarefa('queued-r','{"test":"returned"}'); check168($r['tarefa_principal_concluida'] && !$r['repeticao'],'Complete return');
$afterReturn=snapshot168($pdo); sleep(1); $r=$service->concluirSubtarefa('queued-r','{"overwrite":true}');
check168($r['repeticao'] && snapshot168($pdo)===$afterReturn,'Return retry invariant');
// Actual MySQL SIGNAL failures after child write exercise rollback of InnoDB state.
foreach(['parent','event'] as $failure) {
 seed168($pdo,'fail-'.$failure); $snap=snapshot168($pdo);
 $ddl=$failure==='parent' ? "CREATE TRIGGER fail168 BEFORE UPDATE ON cartas_expedidas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture parent failure'" : "CREATE TRIGGER fail168 BEFORE INSERT ON dashboard_conclusoes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture event failure'";
 $pdo->exec($ddl); $failed=false;
 try { $service->concluirSubtarefa('fail-'.$failure.'-s'); } catch(PDOException $e) { $failed=true; }
 $pdo->exec('DROP TRIGGER fail168'); check168($failed,'Injected '.$failure.' failure observed');
 check168(!$pdo->inTransaction() && snapshot168($pdo)===$snap,'Atomic rollback '.$failure);
 $r=$service->concluirSubtarefa('fail-'.$failure.'-s'); check168(!$r['repeticao'],'Retry after known rollback '.$failure);
}
seed168($pdo,'missing-return','em_andamento',null); $r=$service->concluirSubtarefa('missing-return-s');
check168(count($subs->findByCartaId('missing-return'))===2,'Missing future return created atomically');
// Register using the actual service, then reload through new instances/connections.
$baseline=CartasExpedidasEstadoFluxo::resumir($pdo->query(CartasExpedidasEstadoFluxo::consultaEstados())->fetchAll(PDO::FETCH_ASSOC));
$created=$service->create(['numero_processo'=>'0000001-69.2020.8.05.0001','estado'=>'SP','cidade'=>'Campinas','classe_codigo'=>'SYNTHETIC','classe_descricao'=>'Classe sintetica','finalidade'=>'Cadastro sintetico REI168','prazo'=>'30','usuario_cadastro_id'=>'fixture']);
$reload=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$reloadService=new CartasExpedidasService($reload); $reloadSubs=new CartasExpedidasSubtarefasRepository($reload);
check168($reloadService->find($created)!==null,'Actual create persisted same ID on reload');
$createdSteps=$reloadSubs->findByCartaId($created);
check168(count($createdSteps)===2 && $createdSteps[0]['status']==='em_andamento' && $createdSteps[1]['status']==='pendente','Actual create persisted initial steps');
$createdSummary=CartasExpedidasEstadoFluxo::resumir($reload->query(CartasExpedidasEstadoFluxo::consultaEstados())->fetchAll(PDO::FETCH_ASSOC));
check168($createdSummary['envios']===$baseline['envios']+1 && $createdSummary['devolucoes']===$baseline['devolucoes'],'Create increments only send queue');
$createdList=$reloadService->listCartas(['status'=>'aguardando_envio'],100,0);
check168(in_array($created,array_column($createdList['rows'],'IdCarta'),true),'Created ID belongs to send list');
$sendId=(string)$createdSteps[0]['id']; $reloadService->concluirSubtarefa($sendId);
$sentSummary=CartasExpedidasEstadoFluxo::resumir($pdo->query(CartasExpedidasEstadoFluxo::consultaEstados())->fetchAll(PDO::FETCH_ASSOC));
check168($sentSummary['envios']===$baseline['envios'] && $sentSummary['devolucoes']===$baseline['devolucoes']+1,'Send transfers once between counters');
$sentList=$service->listCartas(['status'=>'aguardando_cumprimento'],100,0);
check168(in_array($created,array_column($sentList['rows'],'IdCarta'),true),'Same created ID belongs to return list after reload');
$snapshot=snapshot168($pdo); $service->concluirSubtarefa($sendId);
check168(snapshot168($pdo)===$snapshot,'Reload retry preserves complete state');
// Real engine refusal must happen before the first write, restoring session timeout.
$pdo->exec('SET SESSION lock_wait_timeout = 17');
$pdo->exec('ALTER TABLE dashboard_conclusoes ENGINE=MyISAM');
$snapshot=snapshot168($pdo); $rejected=false;
try { $service->concluirSubtarefa((string)$createdSteps[1]['id']); } catch(RuntimeException $error) { $rejected=str_contains($error->getMessage(),'InnoDB'); }
check168($rejected && snapshot168($pdo)===$snapshot && !$pdo->inTransaction(),'Actual MyISAM rejected before writes');
check168((int)$pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn()===17,'Timeout restored after engine refusal');
$pdo->exec('ALTER TABLE dashboard_conclusoes ENGINE=InnoDB');
$service->concluirSubtarefa((string)$createdSteps[1]['id']);
check168((int)$pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn()===17,'Timeout restored after successful transition');
// Two independent PHP/MySQL clients must both be observed waiting for the
// same parent lock before release. Readiness alone is not concurrency proof.
seed168($pdo,'race');
$children=[]; $outputs=['','']; $errors=['','']; $exitCodes=[]; $connectionIds=[];
$pdo->beginTransaction();
$pdo->query("SELECT id FROM cartas_expedidas WHERE id='race' FOR UPDATE")->fetchAll();
try {
    for($i=0;$i<2;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__FILE__,'--race-worker'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Cannot start race worker');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $children[$i]=[$process,$pipes];
    }
    $deadline=microtime(true)+10; $observed=false;
    do {
        foreach($children as $i=>[$process,$pipes]) {
            $outputs[$i].=stream_get_contents($pipes[1]); $errors[$i].=stream_get_contents($pipes[2]);
            if(!isset($connectionIds[$i]) && str_contains($outputs[$i],"\n")) {
                $ready=json_decode(explode("\n",$outputs[$i])[0],true,512,JSON_THROW_ON_ERROR);
                if(!isset($ready['ready']) || !is_int($ready['ready'])) throw new RuntimeException('Invalid worker readiness');
                $connectionIds[$i]=$ready['ready'];
            }
            $status=proc_get_status($process);
            if(!$status['running']) { $exitCodes[$i]=$status['exitcode']; throw new RuntimeException('Worker ended before lock barrier: '.$errors[$i]); }
        }
        if(count($connectionIds)===2) {
            $ids=implode(',',array_map('intval',$connectionIds));
            $waiting=(int)$pdo->query("SELECT COUNT(DISTINCT t.PROCESSLIST_ID) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID IN ($ids)")->fetchColumn();
            if($waiting===2) { $observed=true; break; }
        }
        usleep(20000);
    } while(microtime(true)<$deadline);
    check168($observed,'Both independent clients observed blocked on parent row');
    $pdo->commit();
    $deadline=microtime(true)+20;
    do {
        $running=false;
        foreach($children as $i=>[$process,$pipes]) {
            $outputs[$i].=stream_get_contents($pipes[1]); $errors[$i].=stream_get_contents($pipes[2]);
            if(isset($exitCodes[$i])) continue;
            $status=proc_get_status($process);
            if($status['running']) $running=true; else $exitCodes[$i]=$status['exitcode'];
        }
        if(!$running) break;
        usleep(20000);
    } while(microtime(true)<$deadline);
    check168(count($exitCodes)===2,'Concurrent workers terminated within deadline');
    $repeats=[];
    foreach($children as $i=>[$process,$pipes]) {
        $outputs[$i].=stream_get_contents($pipes[1]); $errors[$i].=stream_get_contents($pipes[2]);
        check168($exitCodes[$i]===0 && trim($errors[$i])==='','Worker succeeded without runtime error');
        $lines=explode("\n",trim($outputs[$i]));
        check168(count($lines)===2,'Worker emitted ready and result');
        $answer=json_decode($lines[1],true,512,JSON_THROW_ON_ERROR);
        $repeats[]=$answer['result']['repeticao'];
        check168($answer['result']['tarefa_principal_concluida']===false,'Concurrent send leaves parent open');
    }
    sort($repeats); check168($repeats===[false,true],'Exactly one first commit and one concurrent replay');
} finally {
    if($pdo->inTransaction()) $pdo->rollBack();
    foreach($children as $i=>[$process,$pipes]) {
        $status=proc_get_status($process);
        if($status['running']) {
            proc_terminate($process); $end=microtime(true)+2;
            do { usleep(20000); $status=proc_get_status($process); } while($status['running'] && microtime(true)<$end);
            if($status['running']) {
                proc_terminate($process,9); $end=microtime(true)+2;
                do { usleep(20000); $status=proc_get_status($process); } while($status['running'] && microtime(true)<$end);
                if($status['running']) throw new RuntimeException('Worker termination could not be verified');
            }
        }
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    }
}
check168((int)$pdo->query("SELECT COUNT(*) FROM dashboard_conclusoes WHERE referencia_interna='race-s'")->fetchColumn()===1,'Concurrent send emits exactly one event');
check168($pdo->query("SELECT status FROM cartas_expedidas WHERE id='race'")->fetchColumn()==='aguardando cumprimento','Concurrent parent advances once');
$raceSnapshot=snapshot168($pdo); sleep(1); $retry=$service->concluirSubtarefa('race-s','{"overwrite":"forbidden"}');
check168($retry['repeticao']===true && snapshot168($pdo)===$raceSnapshot,'Post-concurrency retry preserves timestamp data and single event');
// Legacy ambiguity must not silently inflate totals or select an arbitrary step.
seed168($pdo,'duplicate'); $pdo->exec("INSERT INTO cartas_expedidas_subtarefas SELECT 'duplicate-s2',carta_expedida_id,tipo,ordem,status,dados_especificos,created_at,updated_at FROM cartas_expedidas_subtarefas WHERE id='duplicate-s'");
$rows=$pdo->query(CartasExpedidasEstadoFluxo::consultaEstados())->fetchAll(PDO::FETCH_ASSOC);
$summary=CartasExpedidasEstadoFluxo::resumir($rows,new DateTimeImmutable('2026-11-24'));
check168($summary['envios']===null && $summary['devolucoes']===null && $summary['inconsistencias']===1,'Ambiguous aggregate unavailable');
$before=snapshot168($pdo); try { $service->concluirSubtarefa('duplicate-s'); throw new LogicException('duplicate accepted'); } catch(InvalidArgumentException $e) {}
check168(snapshot168($pdo)===$before,'Duplicate transition refused without mutation');
echo json_encode(['suite'=>'REI168 MySQL 8.4 synthetic','assertions'=>$assertions,'case_mode'=>getenv('REI168_EXPECT_LOWER_CASE'),'result'=>'PASS'],JSON_THROW_ON_ERROR).PHP_EOL;
