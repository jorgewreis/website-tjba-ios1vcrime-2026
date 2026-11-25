<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoAssuntoSyncService.php';

final class AssuntoValidationProbe extends PDO
{
    public int $prepares = 0;
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->prepares++;
        throw new RuntimeException('QUERY_REACHED');
    }
}

$cnj = '0000001-69.2020.8.05.0001';
$db = new AssuntoValidationProbe();
$service = new ProcessoAssuntoSyncService($db);
foreach ([[], ['x' => ['codigo' => '1']], [null], [['codigo' => '1'], []],
          [['codigo' => '---']], [['codigo' => []]], [['codigo' => '1', 'descricao' => []]],
          ['apenas descrição']] as $payload) {
    try {
        $service->syncFromDataJud($cnj, $payload);
        throw new RuntimeException('Lote inválido foi aceito.');
    } catch (InvalidArgumentException $e) {
        if ($db->prepares !== 0) throw new RuntimeException('Consulta ocorreu antes de validar o lote.', 0, $e);
    }
}
try {
    $service->syncFromDataJud($cnj, [['codigo' => '  ', 'codigoAssunto' => 123, 'descricao' => '', 'nome' => 'Teste']]);
    throw new RuntimeException('Lote válido não alcançou o repositório.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'QUERY_REACHED') throw $e;
}
echo "PASS validação integral do serviço antes de acessar banco (PDO sentinela)\n";
