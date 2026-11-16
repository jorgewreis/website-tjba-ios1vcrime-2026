<?php
declare(strict_types=1);
// Só para ambiente de teste criado pelo runner sem rede e banco efêmero.
if (getenv('CEJUSC_DISPOSABLE_TEST') !== '1') throw new RuntimeException('Runner isolado obrigatório');
ob_start();
require __DIR__ . '/mysql-isolated.test.php';
$db->exec('CREATE TABLE usuarios (id_usuario VARCHAR(64) PRIMARY KEY, token_acesso VARCHAR(64), token_expira DATETIME, cadastro_ativo INT) ENGINE=InnoDB');
$db->exec('CREATE TABLE usuario_perfil (usuario VARCHAR(64), perfil VARCHAR(64)) ENGINE=InnoDB');
$st=$db->prepare('INSERT INTO usuarios VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 1)');
foreach (['autor','leitor'] as $who) $st->execute([$who, hash('sha256', 'token-'.$who)]);
$db->exec("INSERT INTO usuario_perfil VALUES ('autor','ADMIN'), ('leitor','ESTUDANTE')");
$dir=dirname(__DIR__,2).'/tmp/sessions'; if (!is_dir($dir) && !mkdir($dir,0750,true)) throw new RuntimeException('diretório de sessão');
ini_set('session.save_path',$dir); ini_set('session.use_strict_mode','0');
foreach (['autor','leitor'] as $who) { session_id('cejusctest'.$who); session_start(); $_SESSION=['id_usuario'=>$who,'token'=>'token-'.$who,'last_activity_at'=>time()]; session_write_close(); }
echo "Sessões sintéticas criadas\n";
