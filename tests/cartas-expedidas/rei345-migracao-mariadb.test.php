<?php
declare(strict_types=1);

// Uses only a connection-local TEMPORARY TABLE in the existing CI MariaDB container.
$dsn = getenv('DATAJUD_TEST_DSN') ?: '';
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=datajud_test;charset=utf8mb4') {
    throw new RuntimeException('Este teste só pode rodar no MariaDB isolado do CI.');
}
$db = new PDO($dsn, getenv('DATAJUD_TEST_USER') ?: 'root', getenv('DATAJUD_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TEMPORARY TABLE cartas_expedidas (id VARCHAR(30) PRIMARY KEY) ENGINE=InnoDB');
$db->exec("INSERT INTO cartas_expedidas(id) VALUES ('antiga')");
$source = file_get_contents(__DIR__.'/../../database/migrations/20261127_add_cartas_expedidas_prioridade.sql');
if ($source === false) throw new RuntimeException('Migração indisponível.');
$commands = array_filter(array_map('trim',explode(';',preg_replace('/^--.*$/m','',$source))));
if (count($commands)!==2) throw new RuntimeException('Migração deveria ter dois comandos aditivos.');
foreach ($commands as $sql) $db->exec($sql);
$db->exec("INSERT INTO cartas_expedidas(id) VALUES ('nova')");
$db->exec("INSERT INTO cartas_expedidas(id,prioridade_tarefa) VALUES ('escolha','critico')");
foreach ($commands as $sql) $db->exec($sql);
$rows = $db->query('SELECT id,prioridade_tarefa FROM cartas_expedidas ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
if ($rows !== ['antiga'=>null,'escolha'=>'critico','nova'=>'medio']) {
    throw new RuntimeException('A migração não preservou antigos, padrão e escolha.');
}
echo "PASS: migração idempotente de prioridade no MariaDB; antiga sem preenchimento, nova média.\n";
