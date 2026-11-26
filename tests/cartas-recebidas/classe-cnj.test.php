<?php
declare(strict_types=1);

require __DIR__ . '/../../app/services/CartasRecebidasService.php';

final class ClasseCartaFixture extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Adapt only MySQL locking and UPDATE LIMIT syntax for in-memory SQLite.
        $query = preg_replace('/ FOR UPDATE$/', '', $query);
        if (str_starts_with($query, 'UPDATE ')) {
            $query = preg_replace('/ LIMIT 1$/', '', $query);
        }
        return parent::prepare($query, $options);
    }
}

$db = new ClasseCartaFixture('sqlite::memory:');
$db->sqliteCreateFunction('GET_LOCK', static fn() => 1, 2);
$db->sqliteCreateFunction('RELEASE_LOCK', static fn() => 1, 1);
$db->sqliteCreateFunction('NOW', static fn() => '2026-01-01 12:00:00', 0);
$_SESSION = []; // No DataJud actor or queue; no network dependency.
$db->exec(<<<'SQL'
CREATE TABLE tribunais (id TEXT PRIMARY KEY, sigla TEXT, nome TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO tribunais VALUES ('T','TJBA','Tribunal','','');
CREATE TABLE comarcas (id TEXT PRIMARY KEY, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO comarcas VALUES ('C','T','BA','Local Sintético','Local Sintético','Ativa','','');
CREATE TABLE classes (id TEXT PRIMARY KEY, codigo TEXT UNIQUE, nome TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT UNIQUE, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE cartas_recebidas (id TEXT PRIMARY KEY, processo_origem_id TEXT, numero_processo_origem TEXT, comarca_origem_id TEXT, cidade_origem TEXT, uf_origem TEXT, data_recebimento TEXT, finalidade TEXT, prazo_dias INTEGER, status TEXT, observacoes TEXT, numero_processo_destino TEXT, vara_destino_id TEXT, vara_destino_nome TEXT, criado_por TEXT, atualizado_por TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE cartas_recebidas_subtarefas (id TEXT PRIMARY KEY, carta_recebida_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT);
SQL);
$service = new CartasRecebidasService($db);
$base = ['cidade_origem' => 'Local Sintético', 'uf_origem' => 'BA', 'finalidade' => 'Citação'];
$cases = [
    [['tipo_carta' => 'precatoria'], '355'],
    [['tipo_carta' => 'ordem', 'classe_codigo' => '355'], '335'], // canonical user choice wins
    [['tipo_carta' => 'rogatoria'], '375'],
    [['classe_codigo' => 335], '335'], // legacy API compatibility
];
foreach ($cases as $i => [$input, $expected]) {
    $seq = $i + 1;
    $dv = 98 - (($seq * 10000000000000 + 2026000000000) % 97);
    $number = sprintf('%07d-%02d.2026.0.00.0000', $seq, $dv);
    if ($i === 1) {
        // Previously registered without class; its existing confidentiality must not change.
        $db->prepare("INSERT INTO processos VALUES ('P', ?, NULL, NULL, 1, '', '')")->execute([$number]);
    }
    $id = $service->create($base + $input + ['numero_processo_origem' => $number]);
    $statement = $db->prepare('SELECT cl.codigo, p.sigiloso FROM cartas_recebidas cr JOIN processos p ON p.id = cr.processo_origem_id JOIN classes cl ON cl.id = p.classe_id WHERE cr.id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if ($row === false || $row['codigo'] !== $expected || (int) $row['sigiloso'] !== ($i === 1 ? 1 : 0)) {
        throw new RuntimeException('Classe CNJ ou sigilo divergente no cadastro sintético.');
    }
}
foreach ([[], ['tipo_carta' => 'outro'], ['tipo_carta' => ['ordem']], ['classe_codigo' => '999']] as $input) {
    try {
        $service->create($base + $input + ['numero_processo_origem' => '9999999-00.2026.0.00.0000']);
        throw new RuntimeException('Cadastro aceitou carta sem tipo válido.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), 'tipo')) throw $exception;
    }
}
if ((int) $db->query('SELECT COUNT(*) FROM cartas_recebidas')->fetchColumn() !== count($cases)
    || (int) $db->query('SELECT COUNT(*) FROM cartas_recebidas_subtarefas')->fetchColumn() !== 2 * count($cases)) {
    throw new RuntimeException('Quantidade inesperada de cartas ou subtarefas.');
}
echo "PASS: classes oficiais gravadas no cadastro, sem DataJud, sem alterar sigilo\n";
