<?php
declare(strict_types=1);

// SQLite em memória; adaptação limitada à sintaxe MySQL UPDATE ... LIMIT 1.
require __DIR__ . '/../../app/services/CartasRecebidasService.php';

final class CartaTestPdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with(ltrim($query), 'UPDATE `cartas_recebidas`')) {
            $query = preg_replace('/ LIMIT 1$/', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

$db = new CartaTestPdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', fn() => '2026-11-16 12:00:00', 0);
$db->exec('CREATE TABLE comarcas (id TEXT PRIMARY KEY, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO comarcas VALUES ('agregada','tjba','BA','Itabuna','Itajuípe','Agregada','',''), ('sede','tjba','BA','Itabuna','Itabuna','Ativa','',''), ('sp','tjsp','SP','Itajuípe','Itajuípe','Ativa','','')");
$db->exec('CREATE TABLE cartas_recebidas (id TEXT PRIMARY KEY, cidade_origem TEXT, uf_origem TEXT, comarca_origem_id TEXT, updated_at TEXT)');
$db->exec("INSERT INTO cartas_recebidas VALUES ('carta','Anterior','BA','anterior','original')");
$service = new CartasRecebidasService($db);
$row = static fn(): array => $db->query("SELECT comarca_origem_id,cidade_origem,uf_origem,updated_at FROM cartas_recebidas WHERE id='carta'")->fetch(PDO::FETCH_ASSOC);

foreach ([
    ['agregada', 'Itajuípe', 'BA', 'sede', 'Itabuna'], // cidade agregada resolvida para a sede
    ['sede', 'Itabuna', 'BA', 'sede', 'Itabuna'],  // nome da comarca sede
    ['sp', 'Itajuipe', 'SP', 'sp', 'Itajuípe'],   // normalização de acento
] as [$id, $city, $uf, $expectedId, $expectedCity]) {
    if (!$service->update('carta', ['comarca_origem_id' => $id, 'cidade_origem' => $city, 'uf_origem' => $uf])) {
        throw new RuntimeException('Atualização válida não persistiu');
    }
    $actual = $row();
    if ($actual !== ['comarca_origem_id' => $expectedId, 'cidade_origem' => $expectedCity, 'uf_origem' => $uf, 'updated_at' => '2026-11-16 12:00:00']) {
        throw new RuntimeException('Dados persistidos divergentes: ' . json_encode($actual));
    }
}

foreach ([
    ['comarca_origem_id' => 'sede', 'cidade_origem' => 'Itajuípe', 'uf_origem' => 'SP'],
    ['comarca_origem_id' => 'sede', 'cidade_origem' => 'Cidade inexistente', 'uf_origem' => 'BA'],
    ['comarca_origem_id' => 'ausente', 'cidade_origem' => 'Itajuípe', 'uf_origem' => 'BA'],
    ['cidade_origem' => 'Itajuípe'],
] as $payload) {
    $before = $row();
    $rejected = false;
    try { $service->update('carta', $payload); } catch (InvalidArgumentException $e) { $rejected = true; }
    if (!$rejected || $row() !== $before) throw new RuntimeException('Entrada inválida aceita ou escrita alterada');
}
echo "PASS: persistência e comarca agregada em SQLite sintético\n";
