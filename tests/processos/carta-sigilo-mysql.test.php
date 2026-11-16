<?php
declare(strict_types=1);

if (getenv('REI88_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Recusado: defina REI88_TEST_DISPOSABLE=1 somente em MySQL descartável.');
}
$repoRoot = getenv('REI88_TEST_REPO_ROOT') ?: dirname(__DIR__, 2);
if (!is_dir($repoRoot . '/app') || is_file($repoRoot . '/.env')) {
    throw new RuntimeException('Recusado: raiz de código inválida ou arquivo .env presente.');
}

$dsn = getenv('REI88_MYSQL_DSN') ?: 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4';
if (!str_contains($dsn, 'unix_socket=/var/run/mysqld/mysqld.sock')
    || preg_match('/(?:^|;)host\s*=/i', $dsn) === 1) {
    throw new RuntimeException('Recusado: fixture requer socket local, sem rede TCP.');
}
$expectedMarker = getenv('REI88_TEST_DISPOSABLE_MARKER') ?: '';
if (preg_match('/^[a-f0-9]{64}$/', $expectedMarker) !== 1) {
    throw new RuntimeException('Recusado: marcador efêmero de laboratório ausente ou inválido.');
}
$user = getenv('REI88_MYSQL_USER') ?: 'root';
$password = getenv('REI88_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
if ($dbName !== 'rei88_test' || !str_contains($versionComment, 'MySQL') || !str_starts_with($version, '8.4.')) {
    throw new RuntimeException('Recusado: use somente MySQL 8.4 no banco sintético rei88_test.');
}
$tables = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $tables);
if (count($tables) !== 1
    || strtolower((string) ($tables[0]['table_name'] ?? '')) !== 'rei88_test_guard'
    || strtolower((string) ($tables[0]['engine'] ?? '')) !== 'innodb') {
    throw new RuntimeException('Recusado: banco deve conter somente a tabela sentinela InnoDB criada pelo runner efêmero.');
}
$marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
if (!is_string($marker) || !hash_equals($expectedMarker, $marker)) {
    throw new RuntimeException('Recusado: sentinela do runner não corresponde à execução corrente.');
}
$fixtureTables = ['cartas_expedidas_subtarefas', 'cartas_expedidas', 'varas', 'comarcas', 'tribunais', 'processos', 'classes'];
register_shutdown_function(static function () use ($pdo, $fixtureTables): void {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        fwrite(STDOUT, "CLEANUP schema temporário removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "Falha ao remover o schema sintético: " . $exception->getMessage() . "\n");
        exit(1);
    }
});
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");

$ddl = [
    "CREATE TABLE classes (id VARCHAR(30) PRIMARY KEY, codigo VARCHAR(80) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id VARCHAR(30) PRIMARY KEY, numero_processo VARCHAR(32) NOT NULL UNIQUE, classe_id VARCHAR(30) NULL, data_fato DATE NULL, sigiloso TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_processo_classe FOREIGN KEY (classe_id) REFERENCES classes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE tribunais (id VARCHAR(30) PRIMARY KEY, sigla VARCHAR(16) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE comarcas (id VARCHAR(30) PRIMARY KEY, tribunal_id VARCHAR(30) NOT NULL, uf CHAR(2) NOT NULL, nome VARCHAR(200) NOT NULL, cidade VARCHAR(200) NULL, status_cidade VARCHAR(32) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY uq_test_comarca (tribunal_id, uf, nome), CONSTRAINT fk_test_comarca_tribunal FOREIGN KEY (tribunal_id) REFERENCES tribunais(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE varas (id VARCHAR(30) PRIMARY KEY, comarca_id VARCHAR(30) NOT NULL, codigo VARCHAR(80) NOT NULL DEFAULT '', nome VARCHAR(200) NOT NULL, email VARCHAR(200) NOT NULL DEFAULT '', telefone VARCHAR(80) NOT NULL DEFAULT '', created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_vara_comarca FOREIGN KEY (comarca_id) REFERENCES comarcas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE cartas_expedidas (id VARCHAR(30) PRIMARY KEY, processo_origem_id VARCHAR(30) NOT NULL, numero_processo_origem VARCHAR(32) NOT NULL, finalidade VARCHAR(255) NOT NULL, prazo_dias INT NOT NULL, comarca_destino_id VARCHAR(30) NOT NULL, cidade_destino VARCHAR(200) NOT NULL, uf_destino CHAR(2) NOT NULL, numero_processo_destino VARCHAR(32) NULL, vara_destino_id VARCHAR(30) NULL, vara_destino_nome VARCHAR(200) NULL, status VARCHAR(40) NOT NULL, observacoes TEXT NULL, criado_por VARCHAR(30) NULL, atualizado_por VARCHAR(30) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_carta_processo FOREIGN KEY (processo_origem_id) REFERENCES processos(id), CONSTRAINT fk_test_carta_comarca FOREIGN KEY (comarca_destino_id) REFERENCES comarcas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE cartas_expedidas_subtarefas (id VARCHAR(30) PRIMARY KEY, carta_expedida_id VARCHAR(30) NOT NULL, tipo VARCHAR(40) NOT NULL, ordem INT NOT NULL, status VARCHAR(32) NOT NULL, dados_especificos JSON NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_carta_subtarefa FOREIGN KEY (carta_expedida_id) REFERENCES cartas_expedidas(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

require_once $repoRoot . '/app/services/CartasExpedidasService.php';
require_once $repoRoot . '/app/repositories/ProcessosRepository.php';
require_once $repoRoot . '/app/helpers/CnjValidator.php';

$existingCnj = '0000001-69.2020.8.05.0001';
$pdo->exec("INSERT INTO classes (id, codigo, nome) VALUES ('CLA-OLD', 'OLD', 'Classe anterior')");
$stmt = $pdo->prepare('INSERT INTO processos (id, numero_processo, classe_id, sigiloso) VALUES (?, ?, ?, 1)');
$stmt->execute(['PRC-EXISTING', $existingCnj, 'CLA-OLD']);
$pdo->exec("INSERT INTO processos (id, numero_processo, classe_id, sigiloso) VALUES ('PRC-EXPLICIT', '0000004-24.2020.8.05.0001', 'CLA-OLD', 0)");
$base = [
    'numero_processo' => $existingCnj,
    'estado' => 'SP',
    'cidade' => 'Campinas',
    'classe_codigo' => 'CARTA-TESTE',
    'classe_descricao' => 'Classe sintética',
    'finalidade' => 'Carta precatória sintética',
];
$inputs = [
    'ausente' => [],
    'nulo' => ['nivel_sigilo' => null],
    'false' => ['nivel_sigilo' => false],
    'zero' => ['nivel_sigilo' => 0],
    'zero-string' => ['nivel_sigilo' => '0'],
];
$service = new CartasExpedidasService($pdo);
$expectedClassId = (new ClassesRepository($pdo))->ensureClass('CARTA-TESTE', 'Classe sintética');
foreach ($inputs as $case => $extra) {
    $service->create($base + $extra);
    $row = $pdo->query("SELECT sigiloso, classe_id FROM processos WHERE id = 'PRC-EXISTING'")->fetch(PDO::FETCH_ASSOC);
    if ($row === false || (int) $row['sigiloso'] !== 1 || $row['classe_id'] !== $expectedClassId) {
        throw new RuntimeException("Processo existente perdeu sigilo ou não recebeu a classe esperada no caso: $case");
    }
}
$letterCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$subtaskCount = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
if ($letterCount !== count($inputs) || $subtaskCount !== count($inputs) * 2) {
    throw new RuntimeException('Criação completa de cartas/subtarefas não persistiu as quantidades esperadas.');
}

$newCnj = '0000002-54.2020.8.05.0001';
$service->create(array_merge($base, ['numero_processo' => $newCnj]));
$newProcess = (new ProcessosRepository($pdo))->findByNumero($newCnj);
if ($newProcess === null || (int) $newProcess['sigiloso'] !== 0 || $newProcess['classe_id'] !== $expectedClassId) {
    throw new RuntimeException('Processo novo com classe conhecida não recebeu estado não sigiloso esperado.');
}

$classlessCnj = '0000003-39.2020.8.05.0001';
$classlessPayload = $base;
unset($classlessPayload['classe_codigo'], $classlessPayload['classe_descricao']);
$classlessPayload['numero_processo'] = $classlessCnj;
$service->create($classlessPayload);
$classless = (new ProcessosRepository($pdo))->findByNumero($classlessCnj);
if ($classless === null || (int) $classless['sigiloso'] !== 1 || $classless['classe_id'] !== null) {
    throw new RuntimeException('Processo novo sem classe não foi marcado sigiloso como esperado.');
}

$explicitPayload = array_merge($base, [
    'numero_processo' => '0000004-24.2020.8.05.0001',
    'nivel_sigilo' => 1,
]);
$service->create($explicitPayload);
$explicit = (new ProcessosRepository($pdo))->findByNumero('0000004-24.2020.8.05.0001');
if ($explicit === null || (int) $explicit['sigiloso'] !== 1) {
    throw new RuntimeException('Nível de sigilo explicitamente restrito não foi aplicado.');
}

$beforeFailureProcess = (new ProcessosRepository($pdo))->findByNumero($existingCnj);
$beforeFailureLetters = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn();
$beforeFailureSubtasks = (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn();
$beforeFailureClasses = (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn();
$pdo->exec("CREATE TRIGGER rei88_fail_subtask BEFORE INSERT ON cartas_expedidas_subtarefas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rei88 falha induzida na subtarefa'");
$failurePayload = array_merge($base, [
    'classe_codigo' => 'REI88-ROLLBACK-CLASS',
    'classe_descricao' => 'Classe sintética para rollback',
]);
try {
    $service->create($failurePayload);
    throw new RuntimeException('Falha induzida na subtarefa não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'rei88 falha induzida na subtarefa')) {
        throw $exception;
    }
}
$afterFailureProcess = (new ProcessosRepository($pdo))->findByNumero($existingCnj);
if ($beforeFailureProcess === null || $afterFailureProcess === null
    || $afterFailureProcess['classe_id'] !== $beforeFailureProcess['classe_id']
    || (int) $afterFailureProcess['sigiloso'] !== (int) $beforeFailureProcess['sigiloso']
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas')->fetchColumn() !== $beforeFailureLetters
    || (int) $pdo->query('SELECT COUNT(*) FROM cartas_expedidas_subtarefas')->fetchColumn() !== $beforeFailureSubtasks
    || (int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() !== $beforeFailureClasses
    || $pdo->inTransaction()) {
    throw new RuntimeException('Falha na criação deixou processo, classe, carta, subtarefas ou transação parcial.');
}

echo 'PASS CartasExpedidasService completo em MySQL/InnoDB ' . $version
    . ': sigilo e classe existentes preservados (' . count($inputs) . ' entradas), criação completa, processo novo/classe ausente e rollback após falha em subtarefa verificados.' . PHP_EOL;
