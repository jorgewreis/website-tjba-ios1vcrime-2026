<?php
declare(strict_types=1);
// Standalone synthetic test; never loads the suspended ata test runner or its guard.
if (getenv('REI124ATA_TEST_DISPOSABLE')!=='1') throw new RuntimeException('Runner descartavel obrigatorio');
$dsn=(string)getenv('REI124ATA_MYSQL_DSN');$marker=(string)getenv('REI124ATA_TEST_MARKER');
if ($dsn!=='mysql:host=127.0.0.1;dbname=rei124ata_test;charset=utf8mb4' || !preg_match('/^[a-f0-9]{64}$/D',$marker)) throw new RuntimeException('Destino recusado');
$db=new PDO($dsn,(string)getenv('REI124ATA_MYSQL_USER'),(string)getenv('REI124ATA_MYSQL_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ($db->query('SELECT DATABASE()')->fetchColumn()!=='rei124ata_test' || !hash_equals($marker,(string)$db->query('SELECT marker FROM rei124ata_test_guard')->fetchColumn())) throw new RuntimeException('Identidade descartavel nao comprovada');
$worker=($argv[1]??'')==='worker';
if (!$worker && $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)!==['rei124ata_test_guard']) throw new RuntimeException('Schema nao esta vazio');
require '/repo/app/services/SalaAudienciasAtaService.php';
function check124(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);}
final class Rei124AtaStorage implements SalaAudienciasAtaStorage {
 public bool $failMove=false;public function __construct(private string $root){} public function rootPath():string{return $this->root;}
 public function isUploadedFile(string $path):bool{return is_file($path);} public function moveUploadedFile(string $src,string $dst):bool{return !$this->failMove && copy($src,$dst);}
 public function isFile(string $path):bool{return is_file($path);}public function deleteFile(string $path):bool{return !is_file($path)||unlink($path);}
}
if ($worker) {
 $workerRoot=(string)($argv[2]??'');$workerId=(string)($argv[3]??'');
 if (!preg_match('#^/tmp/rei124ata-[a-f0-9]{24}$#D',$workerRoot) || !in_array($workerId,['1','2'],true) || is_link($workerRoot)) throw new RuntimeException('Worker invalido');
 $source=$workerRoot.'/worker-'.$workerId.'.pdf';if(!is_file($source))throw new RuntimeException('Fonte sintetica ausente');
 file_put_contents($workerRoot.'/ready-'.$workerId,(string)$db->query('SELECT CONNECTION_ID()')->fetchColumn());$deadline=microtime(true)+15;
 while(!is_file($workerRoot.'/go')) {if(microtime(true)>$deadline)throw new RuntimeException('Barreira nao liberada');usleep(10000);}
 $file=['name'=>'worker.pdf','type'=>'application/pdf','tmp_name'=>$source,'error'=>UPLOAD_ERR_OK,'size'=>filesize($source)];
 (new SalaAudienciasAtaService($db,new Rei124AtaStorage($workerRoot)))->uploadPdf('p',$file,'u');echo "PASS worker\n";exit;
}
foreach ([
 'CREATE TABLE processos (id VARCHAR(64) PRIMARY KEY,numero_processo VARCHAR(64),classe_id VARCHAR(64),sigiloso INT) ENGINE=InnoDB',
 'CREATE TABLE classes (id VARCHAR(64) PRIMARY KEY,nome VARCHAR(255)) ENGINE=InnoDB',
 'CREATE TABLE processo_prioridades (id VARCHAR(64) PRIMARY KEY,processo_id VARCHAR(64),codigo VARCHAR(64),origem VARCHAR(64),regra_detalhe TEXT,created_at DATETIME) ENGINE=InnoDB',
 'CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(64) PRIMARY KEY,processo VARCHAR(64),data_audiencia DATE,status VARCHAR(40),ativo INT) ENGINE=InnoDB',
 'CREATE TABLE sala_audiencias_ata (id_ata VARCHAR(64) PRIMARY KEY,pauta VARCHAR(64) NOT NULL UNIQUE,arquivo_relativo VARCHAR(255),nome_original VARCHAR(255),mime VARCHAR(64),tamanho_bytes INT,uploaded_at DATETIME,uploaded_by VARCHAR(64),expires_at DATETIME,identificador_arquivo VARCHAR(255)) ENGINE=InnoDB'
] as $sql)$db->exec($sql);
$db->exec("INSERT INTO sala_audiencias_pauta VALUES ('p',NULL,CURDATE(),'realizada',1)");
$root='/tmp/rei124ata-'.bin2hex(random_bytes(12));check124(mkdir($root,0700) && mkdir($root.'/sala-audiencias-atas',0700),'storage sintetico');
$storage=new Rei124AtaStorage($root);$service=new SalaAudienciasAtaService($db,$storage);$src=$root.'/new.pdf';file_put_contents($src,"%PDF-1.4\nsynthetic\n");$file=['name'=>'synthetic.pdf','type'=>'application/pdf','tmp_name'=>$src,'error'=>UPLOAD_ERR_OK,'size'=>filesize($src)];
$old='sala-audiencias-atas/old.pdf';file_put_contents($root.'/'.$old,"%PDF-1.4\nold\n");
$db->prepare("INSERT INTO sala_audiencias_ata VALUES ('a','p',?,'old.pdf','application/pdf',10,NOW(),'u',DATE_ADD(NOW(),INTERVAL 30 DAY),'old')")->execute([$old]);
$db->exec("INSERT INTO processos VALUES ('secret-process','00000000000000000000',NULL,1); UPDATE sala_audiencias_pauta SET processo='secret-process' WHERE id_pauta='p'");
try {$service->uploadPdf('p',$file,'u');throw new LogicException('Processo sigiloso deveria ser recusado');} catch (InvalidArgumentException $e) {}
check124($db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn()===$old && is_file($root.'/'.$old) && count(glob($root.'/sala-audiencias-atas/*')?:[])===1,'Sigilo permitiu escrita ou alterou ata');
$db->exec("UPDATE processos SET sigiloso=0 WHERE id='secret-process'; INSERT INTO processo_prioridades VALUES ('sp','secret-process','sigiloso','manual',NULL,NOW())");
try {$service->uploadPdf('p',$file,'u');throw new LogicException('Prioridade sigilosa deveria ser recusada');} catch (InvalidArgumentException $e) {check124($e->getMessage()===SalaAudienciasAtaSigiloHelper::MSG_PROCESSO_SIGILOSO,'Motivo nao corresponde ao sigilo');}
check124($db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn()===$old && is_file($root.'/'.$old) && count(glob($root.'/sala-audiencias-atas/*')?:[])===1,'Prioridade sigilosa permitiu escrita ou alterou ata');
$db->exec("UPDATE sala_audiencias_pauta SET processo=NULL WHERE id_pauta='p'");
$storage->failMove=true;try{$service->uploadPdf('p',$file,'u');throw new LogicException('Falha de move esperada');}catch(RuntimeException $e){}$storage->failMove=false;
check124($db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn()===$old && is_file($root.'/'.$old),'Falha move alterou ata');
$db->exec("CREATE TRIGGER rei124ata_fail BEFORE UPDATE ON sala_audiencias_ata FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
try{$service->uploadPdf('p',$file,'u');throw new LogicException('Falha SQL esperada');}catch(PDOException $e){}
check124($db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn()===$old && is_file($root.'/'.$old),'Rollback nao preservou ata');
check124(count(glob($root.'/sala-audiencias-atas/*')?:[])===1,'Arquivo novo nao removido apos rollback');$db->exec('DROP TRIGGER rei124ata_fail');
$service->uploadPdf('p',$file,'u');$current=(string)$db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn();
check124($current!==$old && is_file($root.'/'.$current) && !is_file($root.'/'.$old),'Substituicao nao persistiu corretamente');
echo "PASS ata service sintetico: sigilo por flag e prioridade, falhas de armazenamento/SQL, rollback e substituicao\n";

// Both workers have moved their candidate PDFs and are blocked on the same
// InnoDB row before the parent releases it. No database or filesystem is shared
// with the host; children inherit only the disposable container environment.
file_put_contents($root.'/worker-1.pdf',"%PDF-1.4\nworker one\n");file_put_contents($root.'/worker-2.pdf',"%PDF-1.4\nworker two\n");
$db->beginTransaction();$locked=$db->query("SELECT id_ata FROM sala_audiencias_ata WHERE pauta='p' FOR UPDATE")->fetchColumn();check124($locked!==false,'Nao obteve lock de prova');
$workers=[];
try {
 foreach(['1','2'] as $i) { $proc=proc_open([PHP_BINARY,__FILE__,'worker',$root,$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);check124(is_resource($proc),'Worker nao iniciou');fclose($pipes[0]);$workers[]=[$proc,$pipes]; }
 $deadline=microtime(true)+15;while(!is_file($root.'/ready-1')||!is_file($root.'/ready-2')){if(microtime(true)>$deadline)throw new RuntimeException('Workers nao chegaram a barreira');usleep(10000);}
 $workerIds=[(int)file_get_contents($root.'/ready-1'),(int)file_get_contents($root.'/ready-2')];
 check124($workerIds[0]>0 && $workerIds[1]>0 && $workerIds[0]!==$workerIds[1],'IDs de conexao invalidos');
 file_put_contents($root.'/go','go');$deadline=microtime(true)+15;
 do {
  $waiting=$db->query("SELECT DISTINCT requesting.PROCESSLIST_ID FROM performance_schema.data_lock_waits w
    JOIN performance_schema.threads requesting ON requesting.THREAD_ID=w.REQUESTING_THREAD_ID
    JOIN performance_schema.threads blocking ON blocking.THREAD_ID=w.BLOCKING_THREAD_ID
    JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE
    WHERE blocking.PROCESSLIST_ID=CONNECTION_ID() AND l.OBJECT_SCHEMA='rei124ata_test'
      AND l.OBJECT_NAME='sala_audiencias_ata' AND l.LOCK_TYPE='RECORD'")->fetchAll(PDO::FETCH_COLUMN);
  $waiting=array_map('intval',$waiting);
  if (in_array($workerIds[0],$waiting,true) && in_array($workerIds[1],$waiting,true)) break;
  if(microtime(true)>$deadline)throw new RuntimeException('MySQL nao confirmou espera de ambos os workers');
  usleep(10000);
 } while(true);
 check124(count(glob($root.'/sala-audiencias-atas/*')?:[])===3,'Candidatos nao chegaram a fase de lock');
 $db->commit();
 foreach($workers as [$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($proc);check124($status===0 && str_contains($stdout,'PASS worker'),'Worker falhou');}
 $workers=[];$final=(string)$db->query("SELECT arquivo_relativo FROM sala_audiencias_ata WHERE pauta='p'")->fetchColumn();
 check124(is_file($root.'/'.$final) && count(glob($root.'/sala-audiencias-atas/*')?:[])===1,'Concorrencia deixou arquivo ausente ou orfao');
 echo "PASS ata concorrente: ambas conexoes aguardaram data_lock_waits, referencia valida e arquivo unico\n";
} finally {
 if($db->inTransaction())$db->rollBack();
 foreach($workers as [$proc,$pipes]) {proc_terminate($proc);foreach([1,2] as $i)if(is_resource($pipes[$i]))fclose($pipes[$i]);proc_close($proc);}
}
