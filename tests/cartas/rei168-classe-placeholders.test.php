<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/repositories/CartasExpedidasRepository.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateCollation('utf8mb4_unicode_ci', fn(string $a, string $b): int => strcasecmp($a, $b));
$db->exec('CREATE TABLE classes (codigo TEXT, nome TEXT); CREATE TABLE cartas (finalidade TEXT)');
$db->exec("INSERT INTO classes VALUES ('COD-1','Nome Um'),('COD-2','Nome Dois'); INSERT INTO cartas VALUES ('Citação'), ('Intimação')");
$repo = new CartasExpedidasRepository($db);
$method = new ReflectionMethod($repo, 'buildFilterClause');
foreach ([[' COD-1 ', 'Citação', 'COD-1'], ['Nome Dois', 'Intimação', 'COD-2']] as [$classe, $finalidade, $expected]) {
    [$where, $params] = $method->invoke($repo, ['classe'=>$classe, 'finalidade'=>$finalidade], 'ce', false);
    preg_match_all('/:([A-Za-z_][A-Za-z_0-9]*)/', $where, $matches);
    if (count($matches[0]) !== count(array_unique($matches[0]))) throw new RuntimeException('Placeholder repetido');
    if (($params[':classe_codigo'] ?? null) !== trim($classe) || ($params[':classe_nome'] ?? null) !== trim($classe)) throw new RuntimeException('Parametros da classe inconsistentes');
    $stmt = $db->prepare('SELECT cl.codigo FROM classes cl CROSS JOIN cartas ce WHERE ' . $where);
    $stmt->execute($params);
    if ($stmt->fetchAll(PDO::FETCH_COLUMN) !== [$expected]) throw new RuntimeException('Filtro por classe e finalidade incorreto');
}
[$where, $params] = $method->invoke($repo, [], 'ce', false);
if ($where !== '' || $params !== []) throw new RuntimeException('Filtro vazio alterado');
echo "Cartas expedidas: filtro classe por codigo/nome, binds distintos e finalidade OK (SQLite).\n";
