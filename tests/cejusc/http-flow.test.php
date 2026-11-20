<?php
declare(strict_types=1);
if (getenv('CEJUSC_DISPOSABLE_TEST') !== '1') throw new RuntimeException('Runner isolado obrigatório');
require_once __DIR__.'/../../app/helpers/Conection.php';
require_once __DIR__.'/../../app/helpers/CnjValidator.php';
$pdo=Conection::getInstance()->getConnection();
function expect(bool $ok,string $msg):void { if (!$ok) throw new RuntimeException($msg); }
function request(string $path,array $body,?string $session,string $origin='http://127.0.0.1:8765'):array {
 $headers="Content-Type: application/json\r\nOrigin: $origin\r\n".($session ? "Cookie: PHPSESSID=cejusctest$session\r\n" : '');
 $ctx=stream_context_create(['http'=>['method'=>'POST','header'=>$headers,'content'=>json_encode($body),'ignore_errors'=>true,'timeout'=>5]]);
 $raw=file_get_contents('http://127.0.0.1:8765/api/cartorio-cejusc/'.$path.'.php',false,$ctx);
 preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m); return [(int)($m[1]??0),json_decode((string)$raw,true)];
}
function validCnj(string $seq):string { $base=$seq.'20248100001'; for($i=0;$i<100;$i++){ $v=substr($base,0,7).str_pad((string)$i,2,'0',STR_PAD_LEFT).substr($base,7); if(CnjValidator::isValid($v)) return $v; } throw new RuntimeException('CNJ sintético inválido'); }
$payload=['numero_processo'=>validCnj('0000003'),'nome_parte'=>'Pessoa HTTP Sintetica','data_envio_cejusc'=>'2026-01-01','usuario_id'=>'forjado'];
$tables=['processos','partes','processos_partes','processo_prioridades','cejusc_registros'];
$snap=static function()use($pdo,$tables):array { $x=[]; foreach($tables as $t) $x[$t]=$pdo->query("SELECT * FROM `$t` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC); return $x; };
$before=$snap();
foreach ([[null,'http://127.0.0.1:8765'],['leitor','http://127.0.0.1:8765'],['autor','http://outro.invalid']] as [$who,$origin]) { [$status]=request('create',$payload,$who,$origin); expect($status===403,'Requisição não autorizada aceita'); expect($snap()===$before,'Escrita indevida'); }
[$status,$body]=request('create',$payload,'autor'); expect($status===201 && !empty($body['id']),'Criação HTTP falhou: '.$status.' '.json_encode($body)); $id=$body['id'];
$st=$pdo->prepare('SELECT criado_por,atualizado_por FROM cejusc_registros WHERE id=?'); $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC); expect($row['criado_por']==='autor' && $row['atualizado_por']==='autor','Autoria de criação');
$payload['id']=$id; $payload['data_envio_cejusc']='2026-01-02'; [$status,$body]=request('update',$payload,'autor'); expect($status===200 && ($body['success']??false)===true,'Edição HTTP falhou');
$st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC); expect($row['criado_por']==='autor' && $row['atualizado_por']==='autor','Autoria de edição');
$payload['id']='ausente'; $payload['numero_processo']=validCnj('0000004'); $payload['nome_parte']='Pessoa HTTP Nova'; $before=$snap(); [$status,$body]=request('update',$payload,'autor'); expect($status===200 && ($body['success']??true)===false,'Ausência não reconhecida'); expect($snap()===$before,'Efeito ao editar ausente');
echo "HTTP: sessão, permissões, origem, autoria e edição ausente OK\n";
