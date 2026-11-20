<?php
declare(strict_types=1);
if (getenv('REI124ATA_TEST_DISPOSABLE')!=='1' || getenv('DB_HOST')!=='127.0.0.1' || getenv('DB_NAME')!=='rei124ata_test') throw new RuntimeException('Runner descartavel obrigatorio');
$marker=(string)getenv('REI124ATA_TEST_MARKER');if(!preg_match('/^[a-f0-9]{64}$/D',$marker))throw new RuntimeException('Marcador invalido');
session_id('rei124ataadmin');require '/repo/app/helpers/Conection.php';
$db=Conection::getInstance()->getConnection();
if($db->query('SELECT DATABASE()')->fetchColumn()!=='rei124ata_test'||!hash_equals($marker,(string)$db->query('SELECT marker FROM rei124ata_test_guard')->fetchColumn()))throw new RuntimeException('Identidade descartavel invalida');
function assertHttpAta(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
if(($argv[1]??'')==='setup'){
 $db->exec('CREATE TABLE usuarios (id_usuario VARCHAR(64) PRIMARY KEY,token_acesso VARCHAR(64),token_expira DATETIME,cadastro_ativo INT) ENGINE=InnoDB');
 $db->exec('CREATE TABLE usuario_perfil (usuario VARCHAR(64),perfil VARCHAR(64)) ENGINE=InnoDB');
 $st=$db->prepare('INSERT INTO usuarios VALUES (?, ?, DATE_ADD(NOW(),INTERVAL 1 HOUR),1)');foreach(['admin','student'] as $who)$st->execute([$who,hash('sha256','token-'.$who)]);
 $db->exec("INSERT INTO usuario_perfil VALUES ('admin','ADMIN'),('student','ESTUDANTE')");
 if(!is_dir('/tmp/ios1vcrime_sess'))mkdir('/tmp/ios1vcrime_sess',0750,true);ini_set('session.save_path','/tmp/ios1vcrime_sess');ini_set('session.use_strict_mode','0');
 foreach(['admin','student'] as $who){session_id('rei124ata'.$who);session_start();$_SESSION=['id_usuario'=>$who,'token'=>'token-'.$who,'last_activity_at'=>time()];session_write_close();}
 if(!is_dir('/repo/storage/sala-audiencias-atas'))mkdir('/repo/storage/sala-audiencias-atas',0700,true);
 echo "Fixture HTTP sintetica criada\n";exit;
}
assertHttpAta(($argv[1]??'')==='check','Modo invalido');
function requestAta(?string $who,string $origin='http://127.0.0.1:18766'):array {
 $boundary='rei124-boundary';$pdf="%PDF-1.4\nsynthetic http\n";
 $body="--$boundary\r\nContent-Disposition: form-data; name=\"pauta\"\r\n\r\np\r\n--$boundary\r\nContent-Disposition: form-data; name=\"arquivo\"; filename=\"synthetic.pdf\"\r\nContent-Type: application/pdf\r\n\r\n$pdf\r\n--$boundary--\r\n";
 $headers="Origin: $origin\r\nContent-Type: multipart/form-data; boundary=$boundary\r\n".($who===null?'':"Cookie: PHPSESSID=rei124ata$who\r\n");
 $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>$body,'ignore_errors'=>true,'timeout'=>10]]);
 for($i=0;$i<30;$i++){ $raw=@file_get_contents('http://127.0.0.1:18766/api/sala-audiencias-ata/upload.php',false,$ctx);if($raw!==false)break;usleep(100000); }
 preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);return [(int)($m[1]??0),json_decode((string)$raw,true)];
}
$before=$db->query("SELECT * FROM sala_audiencias_ata WHERE pauta='p'")->fetch(PDO::FETCH_ASSOC);
foreach([[null,'http://127.0.0.1:18766',401],['student','http://127.0.0.1:18766',403],['admin','http://hostil.invalid',403]] as [$who,$origin,$expected]){[$status,$body]=requestAta($who,$origin);assertHttpAta($status===$expected,'Negacao HTTP inesperada: '.$status);assertHttpAta($db->query("SELECT * FROM sala_audiencias_ata WHERE pauta='p'")->fetch(PDO::FETCH_ASSOC)===$before && count(glob('/repo/storage/sala-audiencias-atas/*')?:[])===0,'Negacao causou escrita');}
$db->exec("UPDATE sala_audiencias_pauta SET processo='secret-process' WHERE id_pauta='p'");[$status,$body]=requestAta('admin');assertHttpAta($status===400,'Sigilo nao recusado: '.$status);assertHttpAta($db->query("SELECT * FROM sala_audiencias_ata WHERE pauta='p'")->fetch(PDO::FETCH_ASSOC)===$before && count(glob('/repo/storage/sala-audiencias-atas/*')?:[])===0,'Sigilo causou escrita');
$db->exec("UPDATE sala_audiencias_pauta SET processo=NULL WHERE id_pauta='p'");[$status,$body]=requestAta('admin');assertHttpAta($status===200 && ($body['success']??false)===true,'Upload HTTP falhou: '.$status);
$row=$db->query("SELECT * FROM sala_audiencias_ata WHERE pauta='p'")->fetch(PDO::FETCH_ASSOC);assertHttpAta($row!==false && $row['arquivo_relativo']!==$before['arquivo_relativo'] && is_file('/repo/storage/'.$row['arquivo_relativo']) && $row['uploaded_by']==='admin','Persistencia HTTP invalida');
echo "PASS ata HTTP multipart: 401,403,origem,sigilo e upload autenticado\n";
