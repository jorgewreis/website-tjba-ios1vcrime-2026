<?php
declare(strict_types=1);

// Uses a disposable table in the existing isolated CI MariaDB container.
$dsn = getenv('DATAJUD_TEST_DSN') ?: '';
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=datajud_test;charset=utf8mb4') {
    throw new RuntimeException('Este teste só pode rodar no MariaDB isolado do CI.');
}
$db = new PDO($dsn, getenv('DATAJUD_TEST_USER') ?: 'root', getenv('DATAJUD_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require_once __DIR__.'/../../scripts/migrate-cartas-expedidas-prioridade.php';
$db->exec('CREATE TABLE cartas_expedidas (id VARCHAR(30) PRIMARY KEY) ENGINE=InnoDB');
try {
    $db->exec("INSERT INTO cartas_expedidas(id) VALUES ('antiga')");
    migrateCartasExpedidasPrioridade($db);
    $db->exec("INSERT INTO cartas_expedidas(id) VALUES ('nova')");
    $db->exec("INSERT INTO cartas_expedidas(id,prioridade_tarefa) VALUES ('escolha','critico')");
    migrateCartasExpedidasPrioridade($db);
    $rows = $db->query('SELECT id,prioridade_tarefa FROM cartas_expedidas ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
    if ($rows !== ['antiga'=>null,'escolha'=>'critico','nova'=>'medio']) {
        throw new RuntimeException('A migração não preservou antigos, padrão e escolha.');
    }
    $db->exec('ALTER TABLE cartas_expedidas ALTER COLUMN prioridade_tarefa DROP DEFAULT');
    migrateCartasExpedidasPrioridade($db);
    $db->exec("INSERT INTO cartas_expedidas(id) VALUES ('apos_retomada')");
    if ($db->query("SELECT prioridade_tarefa FROM cartas_expedidas WHERE id='apos_retomada'")->fetchColumn() !== 'medio') {
        throw new RuntimeException('Retomada parcial não restaurou o padrão.');
    }
    $db->exec("ALTER TABLE cartas_expedidas ALTER COLUMN prioridade_tarefa SET DEFAULT 'alto'");
    try {
        migrateCartasExpedidasPrioridade($db);
        throw new LogicException('A alteração de um default inesperado não foi bloqueada.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Coluna existente incompatível; nenhuma correção automática.') throw $e;
    }
} finally {
    $db->exec('DROP TABLE cartas_expedidas');
}
echo "PASS: execução protegida, retomada e idempotência da prioridade no MariaDB.\n";
