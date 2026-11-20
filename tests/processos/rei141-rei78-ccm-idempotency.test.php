<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/CentralMandadosCcm.php';

final class CartasExpedidasRepository
{
    public static array $idempotentRows = [];
    public static array $insertedRows = [];
    public static int $lookupCalls = 0;
    public static int $schemaCheckCalls = 0;

    public function __construct(PDO $connection) {}
    public function getTableName(): string { return 'cartas_expedidas'; }
    public function assertIdempotencyColumnsAvailable(): void { self::$schemaCheckCalls++; }
    public function findByIdempotencyKey(string $userId, string $key, bool $currentRead = false): ?array
    {
        self::$lookupCalls++;
        return self::$idempotentRows[$userId . ':' . $key] ?? null;
    }
    public function insert(array $row): string
    {
        self::$insertedRows[] = $row;
        if (!empty($row['idempotency_key'])) {
            self::$idempotentRows[$row['criado_por'] . ':' . $row['idempotency_key']] = [
                'id' => $row['id'],
                'idempotency_payload_hash' => $row['idempotency_payload_hash'],
            ];
        }
        return (string) $row['id'];
    }
}

final class CartasExpedidasSubtarefasRepository
{
    public const TIPO_ENVIO = 'envio';
    public const ORDEM_ENVIO = 1;
    public const TIPO_DEVOLUCAO = 'devolucao';
    public const ORDEM_DEVOLUCAO = 2;
    public static array $created = [];

    public function __construct(PDO $connection) {}
    public function create(...$args): string { self::$created[] = $args; return 'SUB-SYNTHETIC'; }
}

final class ProcessosRepository
{
    public function __construct(PDO $connection) {}
    public function ensureProcess(...$args): string { return 'PROCESS-SYNTHETIC'; }
}
final class ClassesRepository
{
    public function __construct(PDO $connection) {}
    public function ensureClass(...$args): string { return 'CLASS-SYNTHETIC'; }
}
final class TribunaisRepository
{
    public function __construct(PDO $connection) {}
    public function ensureTribunal(...$args): string { return 'TRIBUNAL-SYNTHETIC'; }
}
final class ComarcasRepository
{
    public function __construct(PDO $connection) {}
    public function findByTribunalUfNome(...$args): ?array { return null; }
    public function ensureComarca(...$args): string { return 'COMARCA-SYNTHETIC'; }
}
final class VarasRepository
{
    public function __construct(PDO $connection) {}
    public function ensureVara(...$args): string { return 'VARA-SYNTHETIC'; }
}
final class CartasExpedidasAlocadosRepository { public function __construct(PDO $connection) {} }
final class DataJudService {}
final class IdGenerator
{
    private static int $next = 0;
    public static function generateUniquePrefixedId(...$args): string { return 'CPE-SYNTHETIC-' . ++self::$next; }
}
final class TextFormatter
{
    public static function formatProperName(string $value, int $limit): string { return $value; }
}
final class Rei141TransactionConnection extends PDO
{
    private bool $transaction = false;
    public function __construct() {}
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function exec(string $statement): int|false { return 0; }
}

$source = file_get_contents(__DIR__ . '/../../app/services/CartasExpedidasService.php');
if ($source === false) {
    throw new RuntimeException('Serviço indisponível.');
}
$source = preg_replace('/^require_once[^;]+;\s*$/m', '', $source);
if ($source === null || str_contains($source, 'require_once')) {
    throw new RuntimeException('Requires inesperados no serviço.');
}
eval('?>' . $source);

$connection = new Rei141TransactionConnection();
$base = [
    'numero_processo' => '0000001-69.2020.8.05.0001',
    'estado' => 'BA',
    'cidade' => 'Salvador',
    'classe_codigo' => 'SYN',
    'classe_descricao' => 'Classe sintética',
    'finalidade' => 'Acompanhamento',
    'prazo' => '30',
    'usuario_cadastro_id' => 'USER-REI141-REI78-TEST',
    'idempotency_key' => str_repeat('a', 64),
];
$service = new CartasExpedidasService($connection);
$confirmedCcm = array_merge($base, ['confirmacao_ccm' => true]);

$confirmedId = $service->create($confirmedCcm);
$insertCountAfterCreate = count(CartasExpedidasRepository::$insertedRows);
$subtaskCountAfterCreate = count(CartasExpedidasSubtarefasRepository::$created);
if ($insertCountAfterCreate !== 1 || $subtaskCountAfterCreate !== 2) {
    throw new RuntimeException('Criação CCM confirmada não persistiu exatamente uma carta e duas subtarefas sintéticas.');
}

foreach ([
    'confirmation missing' => static function (array $payload): array { unset($payload['confirmacao_ccm']); return $payload; },
    'confirmation false' => static function (array $payload): array { $payload['confirmacao_ccm'] = false; return $payload; },
    'confirmation string' => static function (array $payload): array { $payload['confirmacao_ccm'] = 'true'; return $payload; },
] as $label => $mutate) {
    $lookupCountBeforeDenial = CartasExpedidasRepository::$lookupCalls;
    $schemaChecksBeforeDenial = CartasExpedidasRepository::$schemaCheckCalls;
    try {
        $service->create($mutate($confirmedCcm));
        throw new RuntimeException("CCM replay without strict confirmation was accepted: {$label}");
    } catch (InvalidArgumentException $exception) {
        if ($exception->getMessage() !== CentralMandadosCcm::MESSAGE) {
            throw $exception;
        }
    }
    if (CartasExpedidasRepository::$lookupCalls !== $lookupCountBeforeDenial
        || CartasExpedidasRepository::$schemaCheckCalls !== $schemaChecksBeforeDenial
        || count(CartasExpedidasRepository::$insertedRows) !== $insertCountAfterCreate
        || count(CartasExpedidasSubtarefasRepository::$created) !== $subtaskCountAfterCreate) {
        throw new RuntimeException("CCM sem confirmação chegou à idempotência ou escreveu dados: {$label}");
    }
}

$lookupCountBeforeConfirmedReplay = CartasExpedidasRepository::$lookupCalls;
if ($service->create($confirmedCcm) !== $confirmedId
    || CartasExpedidasRepository::$lookupCalls !== $lookupCountBeforeConfirmedReplay + 1
    || count(CartasExpedidasRepository::$insertedRows) !== $insertCountAfterCreate
    || count(CartasExpedidasSubtarefasRepository::$created) !== $subtaskCountAfterCreate) {
    throw new RuntimeException('Replay CCM confirmado não retornou o mesmo ID sem nova escrita.');
}

$nonCcm = array_merge($base, [
    'cidade' => 'Campinas',
    'idempotency_key' => str_repeat('b', 64),
]);
$nonCcmId = $service->create($nonCcm);
$insertCountAfterNonCcm = count(CartasExpedidasRepository::$insertedRows);
$subtaskCountAfterNonCcm = count(CartasExpedidasSubtarefasRepository::$created);
if ($service->create($nonCcm) !== $nonCcmId
    || count(CartasExpedidasRepository::$insertedRows) !== $insertCountAfterNonCcm
    || count(CartasExpedidasSubtarefasRepository::$created) !== $subtaskCountAfterNonCcm) {
    throw new RuntimeException('Replay idempotente não CCM deixou de ser estável.');
}

echo "PASS REI-141/REI-78: confirmação estrita antes de lookup/replay e replays CCM/não CCM sintéticos\n";
