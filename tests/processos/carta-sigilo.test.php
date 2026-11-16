<?php
declare(strict_types=1);

// PDO SQLite em memória; o serviço é carregado sem seus requires para impedir
// qualquer leitura de configuração/.env ou conexão fora desta fixture.
require_once __DIR__ . '/sigilo-repository.test.php';
class CartasExpedidasRepository { public function __construct(PDO $db) {} }
class CartasExpedidasSubtarefasRepository { public function __construct(PDO $db) {} }
class CartasExpedidasAlocadosRepository { public function __construct(PDO $db) {} }
class ClassesRepository { public function __construct(PDO $db) {} public function ensureClass(...$args): string { return 'CLA-SYNTHETIC'; } }
class TribunaisRepository {
    private int $calls = 0;
    public function __construct(PDO $db) {}
    public function ensureTribunal(...$args): string {
        if (++$this->calls === 2) { throw new RuntimeException('STOP_AFTER_PROCESS'); }
        return 'TRI-SYNTHETIC';
    }
}
class ComarcasRepository { public function __construct(PDO $db) {} public function ensureComarca(...$args): string { return 'COM-SYNTHETIC'; } }
class VarasRepository { public function __construct(PDO $db) {} public function ensureVara(...$args): string { return 'VAR-SYNTHETIC'; } }
class DataJudService {}
class TextFormatter { public static function formatProperName(string $value, int $max): string { return $value; } }
class CentralMandadosCcm { public const MESSAGE = 'synthetic'; public static function contains(...$args): bool { return false; } }

$source = file_get_contents(__DIR__ . '/../../app/services/CartasExpedidasService.php');
if ($source === false) { throw new RuntimeException('Serviço indisponível.'); }
$source = preg_replace('/^require_once[^;]+;\s*$/m', '', $source);
if ($source === null || str_contains($source, 'require_once')) { throw new RuntimeException('Requires inesperados no serviço.'); }
eval('?>' . $source);

$db = new ProcessosSqlite();
$repo = new ProcessosRepository($db);
$existingCnj = '0000001-69.2020.8.05.0001';
$existingId = $repo->ensureProcess($existingCnj, null, null, true);
$inputs = [
    'ausente' => [], 'nulo' => ['nivel_sigilo' => null],
    'falso' => ['nivel_sigilo' => false], 'zero' => ['nivel_sigilo' => 0],
    'zero string' => ['nivel_sigilo' => '0'], 'verdadeiro' => ['nivel_sigilo' => true],
];
$base = ['numero_processo' => $existingCnj, 'estado' => 'SP', 'cidade' => 'Campinas', 'classe_codigo' => 'SYN', 'classe_descricao' => 'Classe sintetica'];
foreach ($inputs as $label => $extra) {
    $repo->ensureProcess($existingCnj, null, null, true);
    try { (new CartasExpedidasService($db))->create($base + $extra); throw new RuntimeException('Não atingiu a sentinela.'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'STOP_AFTER_PROCESS') { throw $e; } }
    expectSigilo($db, $existingId, 1, $label . ' em processo existente');
}

$newCnj = '0000002-54.2020.8.05.0001';
foreach ([false, true] as $value) {
    $db->exec("DELETE FROM processos WHERE numero_processo = '0000002-54.2020.8.05.0001'");
    try { (new CartasExpedidasService($db))->create(array_merge($base, ['numero_processo' => $newCnj, 'nivel_sigilo' => $value])); throw new RuntimeException('Não atingiu a sentinela.'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'STOP_AFTER_PROCESS') { throw $e; } }
    $new = $repo->findByNumero($newCnj);
    if ($new !== null || $db->inTransaction()) { throw new RuntimeException('Falha na carta deixou processo novo parcial ou transação aberta.'); }
}

$db = new ProcessosSqlite();
$repo = new ProcessosRepository($db);
$existingId = $repo->ensureProcess($existingCnj, null, null, true);
$db->beginTransaction();
$db->exec('SAVEPOINT cartas_expedidas_create');
$repo->ensureProcess($existingCnj, 'OUTER-CLASS', null, null);
try {
    (new CartasExpedidasService($db))->create($base);
    throw new RuntimeException('Não atingiu a sentinela na transação externa.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'STOP_AFTER_PROCESS') { throw $e; }
}
$outerState = $repo->findById($existingId);
if (!$db->inTransaction() || $outerState === null || $outerState['classe_id'] !== 'OUTER-CLASS') {
    throw new RuntimeException('A falha da carta desfez alterações externas ou encerrou a transação do chamador.');
}
$db->exec('ROLLBACK TO SAVEPOINT cartas_expedidas_create');
$afterOuterRollback = $repo->findById($existingId);
if ($afterOuterRollback === null || $afterOuterRollback['classe_id'] !== null || !$db->inTransaction()) {
    throw new RuntimeException('O savepoint anterior do chamador foi substituído pela criação da carta.');
}
$db->rollBack();
echo "PASS create de carta e sigilo (SQLite em memória; periféricos simulados)\n";
