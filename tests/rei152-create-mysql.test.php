<?php
declare(strict_types=1);

// Apenas incluído pelo entrypoint CI após o schema descartável e o marcador.
$expectedCreateDsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4';
if (!is_file('/.dockerenv') || realpath(__DIR__) !== '/repo/tests'
    || getenv('REI152_CI_REQUIRED') !== '1' || getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql'
    || getenv('REI152_MYSQL_DSN') !== $expectedCreateDsn || getenv('REI152_MYSQL_USER') !== 'root'
    || (string) getenv('REI152_MYSQL_PASSWORD') !== '' || !preg_match('/^[a-f0-9]{64}$/D', (string) getenv('REI152_CI_MARKER'))
    || !isset($pdo, $makeFreshCpf) || !$pdo instanceof PDO || !is_callable($makeFreshCpf)
    || !function_exists('rei152AssertCiConnection')) {
    throw new RuntimeException('Identidade do laboratório recusada.');
}
$assertCreateLab = static function (PDO $db): void {
    rei152AssertCiConnection($db);
    if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei152_test' || $db->inTransaction()) {
        throw new RuntimeException('Banco ou transação do laboratório inesperados.');
    }
};
$assertCreateLab($pdo);
$existsCreate = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='partes_telefones'")->fetchColumn();
if ((int) $existsCreate !== 0) throw new RuntimeException('Tabela da fixture já existe.');
$assertCreateLab($pdo);
$pdo->exec('CREATE TABLE partes_telefones (id VARCHAR(30) PRIMARY KEY, parte_id VARCHAR(30) NOT NULL, tipo VARCHAR(40) NULL, numero VARCHAR(40) NOT NULL, whatsapp TINYINT NOT NULL, principal TINYINT NOT NULL, ativo TINYINT NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB');

$countsCreate = static function (PDO $db): array {
    return array_map(static fn(string $table): int => (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(), ['partes', 'partes_documentos', 'partes_telefones']);
};
$setCreateEngine = static function (string $table, string $engine) use ($pdo, $assertCreateLab): void {
    if (!in_array($table, ['partes', 'partes_documentos', 'partes_telefones'], true) || !in_array($engine, ['MyISAM', 'InnoDB'], true)) throw new LogicException('DDL recusado.');
    $assertCreateLab($pdo);
    $pdo->exec('ALTER TABLE `' . $table . '` ENGINE=' . $engine);
};
$serviceCreate = new PessoasService($pdo);
try {
    foreach (['partes', 'partes_documentos', 'partes_telefones'] as $tableCreate) {
        $setCreateEngine($tableCreate, 'MyISAM');
        foreach ([false, true] as $withCpfCreate) {
            $payloadCreate = ['nome_completo' => 'Fixture Gate Create', 'telefones' => [['numero' => '71999990000']]];
            if ($withCpfCreate) $payloadCreate['cpf'] = $makeFreshCpf();
            $beforeCreate = $countsCreate($pdo);
            $rejectedCreate = false;
            try { $serviceCreate->create($payloadCreate); }
            catch (RuntimeException $errorCreate) {
                if (!str_contains($errorCreate->getMessage(), 'tabelas InnoDB')) throw $errorCreate;
                $rejectedCreate = true;
            }
            if (!$rejectedCreate || $pdo->inTransaction() || $countsCreate($pdo) !== $beforeCreate) throw new RuntimeException('Gate de create não recusou sem resíduos: ' . $tableCreate);
        }
        if ($tableCreate === 'partes_telefones') {
            $beforeCreate = $countsCreate($pdo);
            $idCreate = $serviceCreate->create(['nome_completo' => 'Fixture Sem Telefone', 'telefones' => [['numero' => ' ']]]);
            $afterCreate = $countsCreate($pdo);
            if ($idCreate === '' || $afterCreate !== [$beforeCreate[0] + 1, $beforeCreate[1], $beforeCreate[2]]) throw new RuntimeException('Telefone vazio não deveria exigir engine de telefones.');
        }
        $setCreateEngine($tableCreate, 'InnoDB');
    }
    $beforeCreate = $countsCreate($pdo);
    $idCreate = $serviceCreate->create(['nome_completo' => 'Fixture InnoDB', 'cpf' => $makeFreshCpf(), 'telefones' => [['numero' => '71999990001']]]);
    $afterCreate = $countsCreate($pdo);
    if ($idCreate === '' || $afterCreate !== [$beforeCreate[0] + 1, $beforeCreate[1] + 1, $beforeCreate[2] + 1]) throw new RuntimeException('Cadastro InnoDB não persistiu pessoa, documento e telefone.');

    $beforeCreate = $countsCreate($pdo);
    $failurePdoCreate = new class($expectedCreateDsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]) extends PDO {
        public array $expectedBefore = [];
        public bool $allWritesObserved = false;
        public function commit(): bool {
            if (!$this->inTransaction()) throw new LogicException('Fixture esperava transação ativa.');
            $counts = array_map(fn(string $table): int => (int) $this->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn(), ['partes', 'partes_documentos', 'partes_telefones']);
            $expected = [$this->expectedBefore[0] + 1, $this->expectedBefore[1] + 1, $this->expectedBefore[2] + 1];
            if ($counts !== $expected) throw new LogicException('As três escritas devem existir antes da falha de commit.');
            $this->allWritesObserved = true;
            throw new RuntimeException('falha antes do commit injetada');
        }
    };
    $assertCreateLab($failurePdoCreate);
    $failurePdoCreate->expectedBefore = $beforeCreate;
    $failedCreate = false;
    try { (new PessoasService($failurePdoCreate))->create(['nome_completo' => 'Fixture Rollback', 'cpf' => $makeFreshCpf(), 'telefones' => [['numero' => '71999990002']]]); }
    catch (RuntimeException $errorCreate) {
        // O coordenador pode encapsular a falha do commit; preserva a cadeia causal.
        for ($cause = $errorCreate; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause->getMessage() === 'falha antes do commit injetada') $failedCreate = true;
        }
        if (!$failedCreate) throw $errorCreate;
    }
    if (!$failedCreate || !$failurePdoCreate->allWritesObserved || $failurePdoCreate->inTransaction() || $countsCreate($pdo) !== $beforeCreate) throw new RuntimeException('Rollback não removeu pessoa, documento e telefone após falha antes do commit.');
} finally {
    foreach (['partes', 'partes_documentos', 'partes_telefones'] as $tableCreate) $setCreateEngine($tableCreate, 'InnoDB');
}
echo "PASS REI-152 create: engines, telefone condicional e rollback com falha injetada\n";
