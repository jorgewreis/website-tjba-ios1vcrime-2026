<?php
declare(strict_types=1);
require __DIR__ . '/../../app/services/CartasRecebidasService.php';

final class SedeTestPdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_starts_with(ltrim($query), 'UPDATE `cartas_recebidas`')) $query = preg_replace('/ LIMIT 1$/', '', $query);
        return parent::prepare($query, $options);
    }
}
$db = new SedeTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', fn() => '2026-11-16 12:00:00', 0);
$db->exec('CREATE TABLE comarcas (id TEXT PRIMARY KEY, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO comarcas VALUES ('agregada','tjba','BA','Itabuna','Itajuípe','Agregada','',''), ('sede','tjba','BA','Itabuna','Itabuna','Ativa','',''), ('outra','tjsp','SP','Itajuípe','Itajuípe','Ativa','','')");
$db->exec('CREATE TABLE cartas_recebidas (id TEXT PRIMARY KEY, cidade_origem TEXT, uf_origem TEXT, comarca_origem_id TEXT, updated_at TEXT)');
$db->exec("INSERT INTO cartas_recebidas VALUES ('carta','Anterior','BA','anterior','original')");
$repo = new ComarcasRepository($db);
$service = new CartasRecebidasService($db);
function checkSede(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$found = $repo->resolveSedePorCidadeUf('Itajuipe', 'BA');
checkSede(($found['id'] ?? '') === 'sede' && ($found['nome'] ?? '') === 'Itabuna', 'Busca não devolveu sede');
checkSede(($repo->resolveSedePorCidadeUf('Itajuípe', 'SP')['id'] ?? '') === 'outra', 'UF não isolada');
checkSede($repo->resolveSedePorCidadeUf('Inexistente', 'BA') === null, 'Cidade inexistente aceita');
checkSede($service->update('carta', ['comarca_origem_id'=>'agregada','cidade_origem'=>'Itajuípe','uf_origem'=>'BA']), 'Atualização falhou');
$row = $db->query("SELECT comarca_origem_id,cidade_origem,uf_origem FROM cartas_recebidas WHERE id='carta'")->fetch(PDO::FETCH_ASSOC);
checkSede($row === ['comarca_origem_id'=>'sede','cidade_origem'=>'Itabuna','uf_origem'=>'BA'], 'Persistência não canônica');
$db->beginTransaction();
checkSede($service->update('carta', ['comarca_origem_id'=>'outra','cidade_origem'=>'Itajuípe','uf_origem'=>'SP']), 'Atualização em transação externa falhou');
checkSede($db->inTransaction(), 'Serviço encerrou transação externa');
$db->rollBack();
checkSede($row === $db->query("SELECT comarca_origem_id,cidade_origem,uf_origem FROM cartas_recebidas WHERE id='carta'")->fetch(PDO::FETCH_ASSOC), 'Rollback externo não preservou a carta');
foreach ([
    ['comarca_origem_id'=>'agregada','cidade_origem'=>'Itajuípe','uf_origem'=>'SP'],
    ['comarca_origem_id'=>'agregada','cidade_origem'=>'Inexistente','uf_origem'=>'BA'],
    ['comarca_origem_id'=>'ausente','cidade_origem'=>'Itajuípe','uf_origem'=>'BA'],
] as $invalid) {
    $before = $db->query('SELECT * FROM cartas_recebidas')->fetchAll(PDO::FETCH_ASSOC);
    try { $service->update('carta', $invalid); throw new RuntimeException('Entrada inválida aceita'); } catch (InvalidArgumentException $expected) {}
    checkSede($before === $db->query('SELECT * FROM cartas_recebidas')->fetchAll(PDO::FETCH_ASSOC), 'Rejeição alterou dados');
}
$db->exec("DELETE FROM comarcas WHERE id='sede'");
try { $repo->resolveSedePorCidadeUf('Itajuípe','BA'); throw new RuntimeException('Sede ausente aceita'); } catch (InvalidArgumentException $expected) {}
$db->exec("INSERT INTO comarcas VALUES ('sede1','tjba','BA','Itabuna','Itabuna','Ativa','',''), ('sede2','tjba','BA','Itabuna','Itabuna','Ativa','','')");
try { $repo->resolveSedePorCidadeUf('Itajuípe','BA'); throw new RuntimeException('Sede ambígua aceita'); } catch (InvalidArgumentException $expected) {}
$before = $db->query('SELECT * FROM cartas_recebidas')->fetchAll(PDO::FETCH_ASSOC);
try { $service->update('carta', ['comarca_origem_id'=>'agregada','cidade_origem'=>'Itajuípe','uf_origem'=>'BA']); throw new RuntimeException('Atualização ambígua aceita'); } catch (InvalidArgumentException $expected) {}
checkSede(!$db->inTransaction() && $before === $db->query('SELECT * FROM cartas_recebidas')->fetchAll(PDO::FETCH_ASSOC), 'Ambiguidade não reverteu transação');
echo "PASS sede distinta, UF, ausência e ambiguidade (SQLite sintético)\n";
