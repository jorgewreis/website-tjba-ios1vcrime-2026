<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/GuiasService.php';

final class Rei250TimeoutStatement extends PDOStatement
{
    public function __construct() {}
    public function fetchColumn(int $column = 0): mixed { return 7; }
    public function closeCursor(): bool { return true; }
}

final class Rei250TimeoutPdo extends PDO
{
    public int $execCount = 0;
    public bool $transaction = false;
    public function __construct(private bool $metadataMode) {}
    public function getAttribute(int $attribute): mixed { return 'mysql'; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (($this->metadataMode && str_contains($query, '@@SESSION.lock_wait_timeout'))
            || (!$this->metadataMode && str_contains($query, '@@SESSION.innodb_lock_wait_timeout'))) {
            return new Rei250TimeoutStatement();
        }
        throw new RuntimeException('falha primaria sintetica');
    }
    public function exec(string $statement): int|false
    {
        if (++$this->execCount === 1) return 0;
        throw new RuntimeException('falha ao restaurar sintetica');
    }
}

final class Rei250GuardStatement extends PDOStatement
{
    public function __construct(private mixed $value) {}
    public function fetchColumn(int $column = 0): mixed { return $this->value; }
    public function closeCursor(): bool { return true; }
    public function execute(?array $params = null): bool { return true; }
}
final class Rei250BeforeCommitPdo extends PDO
{
    public bool $transaction = false;
    public bool $committed = false;
    public bool $rolledBack = false;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return 'mysql'; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function rollBack(): bool { $this->rolledBack = true; $this->transaction = false; return true; }
    public function commit(): bool { $this->committed = true; $this->transaction = false; return true; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (str_contains($query, 'lower_case_table_names')) return new Rei250GuardStatement(0);
        return new Rei250GuardStatement(7);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new Rei250GuardStatement('InnoDB'); }
    public function exec(string $statement): int|false
    {
        if ($statement === 'SET SESSION innodb_lock_wait_timeout = 7') throw new RuntimeException('restauração pre-commit falhou');
        return 0;
    }
}

$metadata = new Rei250TimeoutPdo(true);
$metadata->beginTransaction();
try {
    GuiasCadastroTransactionalGuard::requireTransactionalTables($metadata);
    throw new RuntimeException('Falha primária não propagada.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'falha primaria sintetica') throw $e;
}
try {
    GuiasCadastroTransactionalGuard::requireTransactionalTables($metadata);
    throw new RuntimeException('Guard reutilizou conexão insegura.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Conexão indisponível para nova operação de guia.') throw $e;
}

$rowTimeout = new Rei250TimeoutPdo(false);
try {
    (new GuiasService($rowTimeout))->create([
        'numero_processo' => '0000001-69.2020.8.05.0001',
        'denunciado' => 'Pessoa Sintetica',
        'confirmar_nova_pessoa' => true,
    ]);
    throw new RuntimeException('Falha primária não propagada.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'falha primaria sintetica') throw $e;
}
try {
    (new GuiasService($rowTimeout))->create([]);
    throw new RuntimeException('Serviço reutilizou conexão insegura.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Conexão indisponível para cadastro ou edição de guia.') throw $e;
}

$beforeCommit = new Rei250BeforeCommitPdo();
$ran = false;
$method = new ReflectionMethod(GuiasService::class, 'executarTransacaoCadastroEdicao');
try {
    $method->invoke(new GuiasService($beforeCommit), static function () use (&$ran): string { $ran = true; return 'ok'; });
    throw new RuntimeException('Restauração defeituosa foi aceita.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'restauração pre-commit falhou') throw $e;
}
if (!$ran || $beforeCommit->committed || !$beforeCommit->rolledBack || $beforeCommit->inTransaction()) {
    throw new RuntimeException('Restauração falha não reverteu antes do commit.');
}
try {
    (new GuiasService($beforeCommit))->create([]);
    throw new RuntimeException('Conexão insegura foi reutilizada.');
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'Conexão indisponível para cadastro ou edição de guia.') throw $e;
}
echo "PASS REI-250: restaurações falhas preservam exceção e impedem reuso neste serviço.\n";
