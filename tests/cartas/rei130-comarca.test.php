<?php
declare(strict_types=1);
require __DIR__ . '/../../app/services/CartasRecebidasService.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', fn() => '2026-01-01');
$db->exec('CREATE TABLE comarcas (id TEXT, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO comarcas VALUES ('a','tjba','BA','Ilhéus','Ilhéus','Ativa','',''), ('b','tjsp','SP','Ilhéus','Ilhéus','Ativa','',''), ('c','tjba','BA','Itabuna','Itabuna','Ativa','','')");
$db->exec('CREATE TABLE cartas_recebidas (id TEXT, cidade_origem TEXT, uf_origem TEXT, comarca_origem_id TEXT, updated_at TEXT)');
$db->exec("INSERT INTO cartas_recebidas VALUES ('1','Ilhéus','BA','a','')");
$service = new CartasRecebidasService($db);
foreach ([['comarca_origem_id'=>'a','cidade_origem'=>'Ilhéus','uf_origem'=>'SP'], ['comarca_origem_id'=>'a','cidade_origem'=>'Inexistente','uf_origem'=>'BA'], ['cidade_origem'=>'Itabuna']] as $payload) {
    try { $service->update('1', $payload); throw new RuntimeException('entrada inválida aceita'); } catch (InvalidArgumentException $e) {}
    $row=$db->query("SELECT comarca_origem_id FROM cartas_recebidas WHERE id='1'")->fetchColumn();
    if ($row !== 'a') throw new RuntimeException('houve alteração inválida');
}
$repo = new ComarcasRepository($db);
if (($repo->findFirstByNomeOuCidade('Ilheus','SP')['id'] ?? '') !== 'b') throw new RuntimeException('UF ignorada');
if (!$repo->correspondeIdCidadeUf('a','Ilheus','BA')) throw new RuntimeException('nome normalizado rejeitado');
echo "OK validação sintética SQLite\n";
