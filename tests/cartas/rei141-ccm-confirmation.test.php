<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/CentralMandadosCcm.php';

class CartasExpedidasRepository
{
    public static array $rows = [];
    public function __construct(PDO $db) {}
    public function getTableName(): string { return 'cartas_expedidas'; }
    public function insert(array $row): string { self::$rows[] = $row; return (string) $row['id']; }
}
class CartasExpedidasSubtarefasRepository
{
    public const TIPO_ENVIO = 'envio';
    public const ORDEM_ENVIO = 1;
    public const TIPO_DEVOLUCAO = 'devolucao';
    public const ORDEM_DEVOLUCAO = 2;
    public static array $creates = [];
    public function __construct(PDO $db) {}
    public function create(...$args): string { self::$creates[] = $args; return 'SUB-SYNTHETIC'; }
}
class ProcessosRepository
{
    public static int $ensureCalls = 0;
    public function __construct(PDO $db) {}
    public function ensureProcess(...$args): string { self::$ensureCalls++; return 'PROC-SYNTHETIC'; }
}
class ClassesRepository
{
    public function __construct(PDO $db) {}
    public function ensureClass(...$args): string { return 'CLASS-SYNTHETIC'; }
}
class TribunaisRepository
{
    public static int $calls = 0;
    public function __construct(PDO $db) {}
    public function ensureTribunal(string $sigla, string $nome): string { self::$calls++; return 'TRIBUNAL-SYNTHETIC'; }
}
class ComarcasRepository
{
    public function __construct(PDO $db) {}
    public function ensureComarca(...$args): string { return 'COMARCA-SYNTHETIC'; }
    public function findByTribunalUfNome(...$args): ?array { return null; }
}
class VarasRepository
{
    public function __construct(PDO $db) {}
    public function ensureVara(...$args): string { return 'VARA-SYNTHETIC'; }
}
class CartasExpedidasAlocadosRepository { public function __construct(PDO $db) {} }
class DataJudService {}
class IdGenerator
{
    public static function generateUniquePrefixedId(...$args): string { return 'CPE-SYNTHETIC'; }
}
class TextFormatter
{
    public static function formatProperName(string $value, int $maxLength): string { return $value; }
}

$serviceSource = file_get_contents(__DIR__ . '/../../app/services/CartasExpedidasService.php');
if ($serviceSource === false) {
    throw new RuntimeException('Serviço indisponível.');
}
$serviceSource = preg_replace('/^require_once[^;]+;\s*$/m', '', $serviceSource);
if ($serviceSource === null || str_contains($serviceSource, 'require_once')) {
    throw new RuntimeException('Requires inesperados no serviço.');
}
eval('?>' . $serviceSource);

$db = new PDO('sqlite::memory:');
$base = [
    'numero_processo' => '0000001-69.2020.8.05.0001',
    'estado' => 'BA',
    'cidade' => 'Salvador',
    'classe_codigo' => 'SYN',
    'classe_descricao' => 'Classe sintética',
    'finalidade' => 'Acompanhamento',
    'prazo' => '30',
];

foreach ([[], ['confirmacao_ccm' => false], ['confirmacao_ccm' => 'true']] as $unconfirmed) {
    ProcessosRepository::$ensureCalls = 0;
    TribunaisRepository::$calls = 0;
    CartasExpedidasRepository::$rows = [];
    CartasExpedidasSubtarefasRepository::$creates = [];
    try {
        (new CartasExpedidasService($db))->create(array_merge($base, $unconfirmed));
        throw new RuntimeException('CCM sem confirmação explícita foi aceito.');
    } catch (InvalidArgumentException $exception) {
        if ($exception->getMessage() !== CentralMandadosCcm::MESSAGE) {
            throw $exception;
        }
    }
    if (ProcessosRepository::$ensureCalls !== 0
        || TribunaisRepository::$calls !== 0
        || CartasExpedidasRepository::$rows !== []
        || CartasExpedidasSubtarefasRepository::$creates !== []) {
        throw new RuntimeException('A recusa da CCM ocorreu depois de iniciar as escritas do fluxo.');
    }
}

ProcessosRepository::$ensureCalls = 0;
TribunaisRepository::$calls = 0;
try {
    (new CartasExpedidasService($db))->create(array_merge($base, [
        'cidade' => 'Ilhéus',
        'confirmacao_ccm' => true,
    ]));
    throw new RuntimeException('Confirmação CCM contornou o bloqueio da comarca de origem.');
} catch (InvalidArgumentException $exception) {
    if ($exception->getMessage() !== 'O cadastro de carta expedida não é permitido para a cidade de Ilhéus (comarca de origem).') {
        throw $exception;
    }
}
if (ProcessosRepository::$ensureCalls !== 0
    || TribunaisRepository::$calls !== 0
    || CartasExpedidasRepository::$rows !== []
    || CartasExpedidasSubtarefasRepository::$creates !== []) {
    throw new RuntimeException('Validação da comarca de origem ocorreu após iniciar escritas.');
}

foreach ([
    ['label' => 'CCM com confirmação explícita', 'payload' => array_merge($base, ['confirmacao_ccm' => true])],
    ['label' => 'destino não CCM sem confirmação', 'payload' => array_merge($base, ['cidade' => 'Campinas'])],
] as $scenario) {
    ProcessosRepository::$ensureCalls = 0;
    TribunaisRepository::$calls = 0;
    CartasExpedidasRepository::$rows = [];
    CartasExpedidasSubtarefasRepository::$creates = [];
    $id = (new CartasExpedidasService($db))->create($scenario['payload']);
    if ($id !== 'CPE-SYNTHETIC'
        || ProcessosRepository::$ensureCalls !== 1
        || TribunaisRepository::$calls !== 2
        || count(CartasExpedidasRepository::$rows) !== 1
        || count(CartasExpedidasSubtarefasRepository::$creates) !== 2) {
        throw new RuntimeException('Fluxo inválido antes da sentinela: ' . $scenario['label']);
    }
}

echo "PASS REI-141 CCM: negação default/strict-true, regra de origem preservada e criação sintética para CCM/não CCM\n";
