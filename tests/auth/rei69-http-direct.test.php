<?php
declare(strict_types=1);
// Mandatory runner-created file and token precede configuration and all I/O.
require __DIR__ . '/sandbox-guard.php';
$stage='setup';
set_exception_handler(static function (Throwable $e) use (&$stage): void {
    $allowed=['setup','seed','login','anonymous','permissions','cross_sector','positive','rollback','read'];
    fwrite(STDERR,'REI69_HTTP_FAILURE stage='.(in_array($stage,$allowed,true)?$stage:'setup')."\n");
    exit(1);
});
require dirname(__DIR__,2).'/config/config.php';
require dirname(__DIR__,2).'/config/security.php';
if (DB_HOST!=='mysql' || DB_PORT!==3306 || DB_NAME!=='auth_test_e2e' || DB_USER!=='root') throw new RuntimeException('Configuração de sandbox divergente.');
$dsn=sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',DB_HOST,DB_PORT,DB_NAME,DB_CHARSET);
$pdo=new PDO($dsn,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='auth_test_e2e' || !str_starts_with((string)$pdo->query('SELECT VERSION()')->fetchColumn(),'8.4.') || strtoupper((string)$pdo->query('SELECT @@default_storage_engine')->fetchColumn())!=='INNODB') throw new RuntimeException('Banco sintético não corresponde ao runner.');
$names=['setores','usuario_setor','processos','partes','processos_partes'];
$check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach ($names as $name) { $check->execute([$name]); if ((int)$check->fetchColumn()!==0) throw new RuntimeException('Tabela sintética já existe.'); }
$stage='seed';
$ddl=[
    "CREATE TABLE setores (id_setor CHAR(30) NOT NULL PRIMARY KEY, ativo TINYINT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE usuario_setor (usuario CHAR(30) NOT NULL, setor CHAR(30) NOT NULL, PRIMARY KEY(usuario,setor)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id CHAR(30) NOT NULL PRIMARY KEY, numero_processo VARCHAR(64), id_setor_responsavel CHAR(30), sigiloso TINYINT NOT NULL DEFAULT 0, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE partes (id CHAR(30) NOT NULL PRIMARY KEY, nome_completo VARCHAR(180), cpf VARCHAR(32), sexo VARCHAR(10), data_nascimento DATE, profissao VARCHAR(255), created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos_partes (id CHAR(30) NOT NULL PRIMARY KEY, processo_id CHAR(30) NOT NULL, parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, polo VARCHAR(50) NOT NULL, prioridade_mulher TINYINT NOT NULL DEFAULT 0, prioridade_crianca_adolescente TINYINT NOT NULL DEFAULT 0, prioridade_idoso TINYINT NOT NULL DEFAULT 0, prioridade_pessoa_deficiencia TINYINT NOT NULL DEFAULT 0, created_at DATETIME, updated_at DATETIME, KEY(processo_id), FOREIGN KEY(processo_id) REFERENCES processos(id), FOREIGN KEY(parte_id) REFERENCES partes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) $pdo->exec($sql);
$pdo->exec("INSERT INTO setores VALUES ('REI69-SA',1),('REI69-SB',1),('REI69-SI',0)");
$user=$pdo->prepare('INSERT INTO usuarios (id_usuario,nome,email,cpf,senha_hash,cadastro_ativo) VALUES (?,?,?,?,?,1)');
$profile=$pdo->prepare('INSERT INTO usuario_perfil (id_usu_perfil,usuario,perfil) VALUES (?,?,?)');
foreach ([['REI69-USER-A','REI69-PA','390.533.447-05','SERVIDOR-CARTORIO'],['REI69-USER-B','REI69-PB','168.995.350-09','ADMIN']] as [$id,$profileId,$cpf,$role]) {
    $user->execute([$id,'Pessoa Sintetica '.$id,strtolower($id).'@auth-test.invalid',$cpf,Security::hashPassword('Sintetica!12345')]);
    $profile->execute([$profileId,$id,$role]);
}
$pdo->exec("INSERT INTO usuario_setor VALUES ('REI69-USER-A','REI69-SA'),('REI69-USER-A','REI69-SI'),('REI69-USER-B','REI69-SB')");
$pdo->exec("INSERT INTO processos (id,numero_processo,id_setor_responsavel,sigiloso) VALUES ('REI69-P1','CNJ-SINTETICO-1','REI69-SA',0),('REI69-P2','CNJ-SINTETICO-2','REI69-SB',0),('REI69-P3','CNJ-SINTETICO-3',NULL,0),('REI69-P4','CNJ-SINTETICO-4','REI69-SI',0)");
$pdo->exec("INSERT INTO partes VALUES ('REI69-A','Pessoa Sintetica A',NULL,'X','2000-01-01','Outra',NOW(),NOW()),('REI69-B','Pessoa Sintetica B',NULL,'X','2000-01-01','Outra',NOW(),NOW()),('REI69-C','Pessoa Sintetica C',NULL,'X','2000-01-01','Outra',NOW(),NOW()),('REI69-D','Pessoa Sintetica D',NULL,'X','2000-01-01','Outra',NOW(),NOW())");
$pdo->exec("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES ('REI69-L2','REI69-P2','REI69-A','vitima','ativo',NOW(),NOW()),('REI69-L3','REI69-P3','REI69-A','vitima','ativo',NOW(),NOW()),('REI69-L4','REI69-P4','REI69-A','vitima','ativo',NOW(),NOW())");
$assert=static function (bool $ok): void { if (!$ok) throw new RuntimeException('Falha de cenário sintético.'); };
$snapshot=static function () use ($pdo,$names): array { $out=[]; foreach ($names as $name) $out[$name]=['ddl'=>$pdo->query('SHOW CREATE TABLE `'.$name.'`')->fetch(PDO::FETCH_NUM),'rows'=>$pdo->query('SELECT * FROM `'.$name.'` ORDER BY 1,2')->fetchAll(PDO::FETCH_ASSOC)]; return $out; };
$request=static function (string $path,string $method='GET',array $fields=[],string $cookie='',?string $origin=null): array {
    if (!str_starts_with($path,'/') || str_contains($path,"\r") || str_contains($path,"\n")) throw new RuntimeException('Caminho inválido.');
    $socket=fsockopen('app',8080,$errno,$error,5); if (!$socket) throw new RuntimeException('Socket sintético indisponível.');
    stream_set_timeout($socket,15);
    $body=$fields ? http_build_query($fields) : '';
    $headers=['Host: app:8080','Accept: application/json, text/html;q=0.9','Connection: close'];
    if ($cookie!=='') $headers[]='Cookie: '.$cookie;
    if ($method==='POST') $headers[]='Origin: '.($origin ?? 'http://app:8080');
    if ($body!=='') { $headers[]='Content-Type: application/x-www-form-urlencoded'; $headers[]='Content-Length: '.strlen($body); }
    fwrite($socket,$method.' '.$path." HTTP/1.1\r\n".implode("\r\n",$headers)."\r\n\r\n".$body);
    $raw=stream_get_contents($socket); $meta=stream_get_meta_data($socket); fclose($socket);
    if ($raw===false || ($meta['timed_out'] ?? false) || !str_contains($raw,"\r\n\r\n")) throw new RuntimeException('Resposta HTTP inválida.');
    [$head,$body]=explode("\r\n\r\n",$raw,2); $lines=explode("\r\n",$head);
    if (!preg_match('#\s(\d{3})\s#',$lines[0] ?? '',$m)) throw new RuntimeException('Status ausente.');
    return ['status'=>(int)$m[1],'headers'=>$lines,'body'=>$body];
};
$cookie=static function (array $headers): string { foreach ($headers as $h) if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i',$h,$m)) return $m[1]; throw new RuntimeException('Cookie ausente.'); };
$login=static function (string $cpf) use ($request,$cookie,$assert): string {
    $form=$request('/views/public/login-form.php');
    $assert($form['status']===200 && preg_match('/name="csrf_token"\s+value="([^"]+)"/',$form['body'],$m)===1);
    $login=$request('/login.php','POST',['cpf'=>$cpf,'senha'=>'Sintetica!12345','csrf_token'=>html_entity_decode($m[1],ENT_QUOTES,'UTF-8')],$cookie($form['headers']));
    $body=json_decode($login['body'],true); $assert($login['status']===200 && is_array($body) && ($body['success'] ?? false)===true);
    return $cookie($login['headers']);
};
$stage='login'; $sessionA=$login('390.533.447-05'); $sessionB=$login('168.995.350-09'); $consultant=$login('258.369.147-37');
$stage='anonymous'; $before=$snapshot();
foreach (['create.php','update.php','delete.php'] as $endpoint) { $r=$request('/api/processos-partes/'.$endpoint,'POST',['id'=>'REI69-L2']); $assert($r['status']===401); }
$assert($snapshot()===$before);
$stage='permissions';
$payload=['processo_id'=>'REI69-P1','parte_id'=>'REI69-B','tipo_parte'=>'advogado','polo'=>'terceiros'];
foreach (['create.php','update.php','delete.php'] as $endpoint) {
    $fields=$endpoint==='create.php' ? $payload : ['id'=>'REI69-L2','tipo_parte'=>'advogado'];
    $assert($request('/api/processos-partes/'.$endpoint,'POST',$fields,$consultant)['status']===403);
    $assert($request('/api/processos-partes/'.$endpoint,'GET',[],$sessionA)['status']===403);
    $assert($request('/api/processos-partes/'.$endpoint,'POST',$fields,$sessionA,'http://hostile.invalid')['status']===403);
}
$assert($snapshot()===$before);
$stage='cross_sector';
$assert($request('/api/processos-partes/create.php','POST',array_replace($payload,['processo_id'=>'REI69-P2']),$sessionA)['status']===403);
$assert($request('/api/processos-partes/create.php','POST',$payload,$sessionB)['status']===403);
$assert($request('/api/processos-partes/create.php','POST',array_replace($payload,['processo_id'=>'REI69-P3']),$sessionA)['status']===403);
$assert($request('/api/processos-partes/create.php','POST',array_replace($payload,['processo_id'=>'REI69-P4']),$sessionA)['status']===403);
foreach (['update.php','delete.php'] as $endpoint) {
    $missing=$request('/api/processos-partes/'.$endpoint,'POST',['id'=>'REI69-MISSING','tipo_parte'=>'advogado'],$sessionA);
    $assert($missing['status']===404);
    foreach (['REI69-L2','REI69-L3','REI69-L4'] as $link) {
        $denied=$request('/api/processos-partes/'.$endpoint,'POST',['id'=>$link,'tipo_parte'=>'advogado'],$sessionA);
        $assert($denied['status']===$missing['status'] && $denied['body']===$missing['body']);
    }
}
$assert($snapshot()===$before);
$stage='read';
foreach (['REI69-P2'=>'REI69-L2','REI69-P3'=>'REI69-L3'] as $process=>$link) {
    $r=$request('/api/processos-partes/list.php?processo_id='.$process,'GET',[],$sessionA);
    $data=json_decode($r['body'],true); $assert($r['status']===200 && array_column($data['data'] ?? [],'id')===[$link]);
}
$assert($snapshot()===$before);
$stage='rollback';
$pdo->exec("CREATE TRIGGER rei69_http_reject_sync BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic sync rejection'");
try { $pdo->exec("UPDATE partes SET profissao='controle' WHERE id='REI69-C'"); throw new RuntimeException('Controle do trigger falhou.'); }
catch (PDOException $e) { $assert($e->getCode()==='45000' && str_contains($e->getMessage(),'synthetic sync rejection')); }
$assert($request('/api/processos-partes/create.php','POST',array_replace($payload,['parte_id'=>'REI69-C']),$sessionA)['status']===500 && $snapshot()===$before);
$pdo->exec('DROP TRIGGER rei69_http_reject_sync');
$stage='positive';
$r=$request('/api/processos-partes/create.php','POST',$payload,$sessionA); $body=json_decode($r['body'],true); $id=(string)($body['id'] ?? '');
$assert($r['status']===201 && $id!=='' && $pdo->query("SELECT profissao FROM partes WHERE id='REI69-B'")->fetchColumn()==='Advogado');
$stmt=$pdo->prepare('SELECT processo_id,parte_id,tipo_parte,polo FROM processos_partes WHERE id=?'); $stmt->execute([$id]);
$assert($stmt->fetch(PDO::FETCH_ASSOC)===['processo_id'=>'REI69-P1','parte_id'=>'REI69-B','tipo_parte'=>'advogado','polo'=>'terceiros']);
$stage='rollback'; $beforeUpdate=$snapshot();
$pdo->exec("CREATE TRIGGER rei69_http_reject_sync BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic sync rejection'");
$assert($request('/api/processos-partes/update.php','POST',['id'=>$id,'tipo_parte'=>'defensor_publico'],$sessionA)['status']===500 && $snapshot()===$beforeUpdate);
$pdo->exec('DROP TRIGGER rei69_http_reject_sync');
$stage='positive';
$r=$request('/api/processos-partes/update.php','POST',['id'=>$id,'tipo_parte'=>'defensor_publico'],$sessionA);
$assert($r['status']===200 && $pdo->query("SELECT profissao FROM partes WHERE id='REI69-B'")->fetchColumn()==='Defensor Público');
$stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC); $assert(is_array($row) && $row['tipo_parte']==='defensor_publico');
$r=$request('/api/processos-partes/delete.php','POST',['id'=>$id],$sessionA); $deleted=json_decode($r['body'],true);
$stmt->execute([$id]); $assert($r['status']===200 && ($deleted['deleted'] ?? false)===true && $stmt->fetch()===false);
$afterA=$snapshot(); $expectedA=$before;
$beforeIndex=null; $afterIndex=null;
foreach ($expectedA['partes']['rows'] as $i=>$row) if ($row['id']==='REI69-B') $beforeIndex=$i;
foreach ($afterA['partes']['rows'] as $i=>$row) if ($row['id']==='REI69-B') $afterIndex=$i;
$assert($beforeIndex!==null && $afterIndex!==null);
$oldTime=$expectedA['partes']['rows'][$beforeIndex]['updated_at']; $newTime=$afterA['partes']['rows'][$afterIndex]['updated_at'];
$assert(is_string($oldTime) && is_string($newTime) && $newTime >= $oldTime);
$expectedA['partes']['rows'][$beforeIndex]['profissao']='Defensor Público';
$expectedA['partes']['rows'][$beforeIndex]['updated_at']=$newTime;
$assert($afterA===$expectedA);
$r=$request('/api/processos-partes/create.php','POST',['processo_id'=>'REI69-P2','parte_id'=>'REI69-D','tipo_parte'=>'vitima','polo'=>'ativo'],$sessionB);
$admin=json_decode($r['body'],true); $adminId=(string)($admin['id'] ?? '');
$assert($r['status']===201 && $adminId!=='');
$stmt->execute([$adminId]); $assert($stmt->fetch(PDO::FETCH_ASSOC)===['processo_id'=>'REI69-P2','parte_id'=>'REI69-D','tipo_parte'=>'vitima','polo'=>'ativo']);
$r=$request('/api/processos-partes/delete.php','POST',['id'=>$adminId],$sessionB); $adminDeleted=json_decode($r['body'],true);
$stmt->execute([$adminId]); $assert($r['status']===200 && ($adminDeleted['deleted'] ?? false)===true && $stmt->fetch()===false);
$assert($snapshot()===$afterA);
echo "PASS REI69 HTTP autenticado sintético\n";
