<?php
declare(strict_types=1);

// Executa o verificador real em subprocessos isolados com catálogo MySQL simulado.
$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rei78-migration-index-' . bin2hex(random_bytes(6));
mkdir($tmp . '/scripts', 0777, true);
mkdir($tmp . '/app/helpers', 0777, true);
mkdir($tmp . '/staging', 0777, true);
copy($root . '/scripts/migrate-cartas-expedidas-idempotency.php', $tmp . '/scripts/migrate-cartas-expedidas-idempotency.php');
copy($root . '/scripts/migrate-cartas-expedidas-idempotency.php', $tmp . '/staging/migrate.php');
file_put_contents($tmp . '/staging/migration.sql', "-- external staged migration fixture\n");
file_put_contents($tmp . '/app/helpers/Conection.php', <<<'STUB'
<?php
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): object { return new Rei78CatalogPdo(); }
}
final class Rei78CatalogPdo {
    public function prepare(string $sql): Rei78CatalogStatement { return new Rei78CatalogStatement($sql); }
    public function exec(string $sql): int {
        if (trim($sql) === '') throw new RuntimeException('SQL vazio');
        putenv('REI78_SCHEMA_APPLIED=1');
        return 0;
    }
}
final class Rei78CatalogStatement {
    private array $params = [];
    public function __construct(private string $sql) {}
    public function execute(array $params = []): bool { $this->params = $params; return true; }
    public function fetchColumn(): string { return 'InnoDB'; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT): array {
        if (str_contains($this->sql, 'information_schema.COLUMNS')) {
            if (getenv('REI78_EMPTY_SCHEMA') === '1' && getenv('REI78_SCHEMA_APPLIED') !== '1') return [];
            return $mode === PDO::FETCH_COLUMN
                ? ['idempotency_key', 'idempotency_payload_hash']
                : [
                    ['COLUMN_NAME'=>'idempotency_key','COLUMN_TYPE'=>'char(64)','IS_NULLABLE'=>'YES'],
                    ['COLUMN_NAME'=>'idempotency_payload_hash','COLUMN_TYPE'=>'char(64)','IS_NULLABLE'=>'YES'],
                ];
        }
        if (str_contains($this->sql, 'information_schema.STATISTICS')) {
            if (getenv('REI78_EMPTY_SCHEMA') === '1' && getenv('REI78_SCHEMA_APPLIED') !== '1') return [];
            $subPart = getenv('REI78_PREFIX_INDEX') === '1' ? 10 : null;
            return [
                ['NON_UNIQUE'=>0,'SEQ_IN_INDEX'=>1,'COLUMN_NAME'=>'criado_por','SUB_PART'=>null],
                ['NON_UNIQUE'=>0,'SEQ_IN_INDEX'=>2,'COLUMN_NAME'=>'idempotency_key','SUB_PART'=>$subPart],
            ];
        }
        return [];
    }
}
STUB
);

try {
    foreach ([
        ['0', '0', false, 0],
        ['1', '0', false, 1],
        ['0', '1', true, 0],
    ] as [$prefix, $emptySchema, $staged, $expectedFailure]) {
        $env = getenv();
        $env['REI78_PREFIX_INDEX'] = $prefix;
        $env['REI78_EMPTY_SCHEMA'] = $emptySchema;
        unset($env['REI78_SCHEMA_APPLIED']);
        if ($staged) {
            $env['CARTAS_PROJECT_ROOT'] = $tmp;
            $env['CARTAS_MIGRATION_SQL'] = $tmp . '/staging/migration.sql';
        } else {
            unset($env['CARTAS_PROJECT_ROOT'], $env['CARTAS_MIGRATION_SQL']);
        }
        $pipes = [];
        $script = $staged ? $tmp . '/staging/migrate.php' : $tmp . '/scripts/migrate-cartas-expedidas-idempotency.php';
        $process = proc_open([PHP_BINARY, $script], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $tmp, $env);
        if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o verificador isolado.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exit = proc_close($process);
        $unexpected = $expectedFailure === 1
            ? ($exit === 0 || !str_contains($stderr, 'definição existente'))
            : ($exit !== 0 || ($staged
                ? !str_contains($stdout, 'schema aplicado e verificado')
                : !str_contains($stdout, 'schema já aplicado')));
        if ($unexpected) {
            throw new RuntimeException("Resultado inesperado para prefixo={$prefix}, vazio={$emptySchema}, staged=" . (int) $staged . "; exit={$exit}; stdout={$stdout}; stderr={$stderr}");
        }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($tmp);
}
echo "PASS migration verifier: full index accepted; prefix rejected; staged external migration applied\n";
