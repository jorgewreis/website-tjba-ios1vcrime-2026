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

$fixtureTables = ['sala_audiencias_pauta_parte', 'sala_audiencias_pauta', 'processos', 'classes'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $expectedMarker): void {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $remaining = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $marker = $pdo->query('SELECT marker FROM rei88_test_guard LIMIT 1')->fetchColumn();
        if ($remaining !== ['rei88_test_guard'] || !is_string($marker) || !hash_equals($expectedMarker, $marker)) {
            throw new RuntimeException('Verificação final da sentinela ou do schema falhou.');
        }
        fwrite(STDOUT, "CLEANUP schema temporário removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Falha ao remover/verificar o schema sintético: ' . $exception->getMessage() . "\n");
        exit(1);
    }
});

$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$ddl = [
    "CREATE TABLE classes (id VARCHAR(30) PRIMARY KEY, codigo VARCHAR(80) NOT NULL UNIQUE, nome VARCHAR(200) NOT NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id VARCHAR(30) PRIMARY KEY, numero_processo VARCHAR(32) NOT NULL UNIQUE, classe_id VARCHAR(30) NULL, data_fato DATE NULL, sigiloso TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_pauta_processo_classe FOREIGN KEY (classe_id) REFERENCES classes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(30) PRIMARY KEY, numero_sequencial INT NOT NULL, ano SMALLINT NOT NULL, processo VARCHAR(30) NOT NULL, tipo_audiencia VARCHAR(120) NOT NULL, data_audiencia DATE NOT NULL, hora_audiencia TIME NOT NULL, localizacao VARCHAR(255) NOT NULL, status VARCHAR(40) NOT NULL, observacoes TEXT NULL, link_audiencia VARCHAR(1000) NULL, criado_por VARCHAR(30) NULL, atualizado_por VARCHAR(30) NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, data_hora_designacao DATETIME NULL, hora_inicio TIME NULL, hora_fim TIME NULL, duracao_estimada_minutos INT NULL, data_hora_inicio_efetivo DATETIME NULL, data_hora_fim_efetivo DATETIME NULL, created_at DATETIME NULL, updated_at DATETIME NULL, CONSTRAINT fk_test_pauta_processo FOREIGN KEY (processo) REFERENCES processos(id), KEY ix_test_pauta_slot (ativo, status, data_audiencia, hora_audiencia), KEY ix_test_pauta_processo (processo, ativo, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta_parte (id_parte VARCHAR(30) PRIMARY KEY, pauta VARCHAR(30) NOT NULL, ativo TINYINT(1) NOT NULL DEFAULT 1, CONSTRAINT fk_test_pauta_parte_pauta FOREIGN KEY (pauta) REFERENCES sala_audiencias_pauta(id_pauta)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

require_once $repoRoot . '/app/services/SalaAudienciasPautaService.php';

$futureDate = static fn (): string => (new DateTimeImmutable('today +10 days'))->format('Y-m-d');
$basePayload = [
    'numero_processo' => '0000001-69.2020.8.05.0001',
    'tipo_audiencia' => 'Instrução',
    'data_audiencia' => $futureDate(),
    'hora_audiencia' => '10:00',
    'localizacao' => 'Sala sintética',
];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$allRows = static function (PDO $db, string $table, string $orderBy): array {
    return $db->query('SELECT * FROM `' . $table . '` ORDER BY ' . $orderBy)->fetchAll(PDO::FETCH_ASSOC);
};
$snapshot = static function (PDO $db) use ($allRows): array {
    return [
        'classes' => $allRows($db, 'classes', 'id'),
        'processos' => $allRows($db, 'processos', 'id'),
        'pauta' => $allRows($db, 'sala_audiencias_pauta', 'id_pauta'),
        'partes' => $allRows($db, 'sala_audiencias_pauta_parte', 'id_parte'),
    ];
};
$reset = static function (PDO $db): void {
    $db->exec('DROP TRIGGER IF EXISTS rei88_fail_after_pauta_insert');
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['sala_audiencias_pauta_parte', 'sala_audiencias_pauta', 'processos', 'classes'] as $table) {
        $db->exec('TRUNCATE TABLE `' . $table . '`');
    }
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
};
$seedExistingProcess = static function (PDO $db, string $number) use ($assert): void {
    $db->exec("INSERT INTO classes (id, codigo, nome, created_at, updated_at) VALUES ('CLA-ORIGINAL', 'ORIGINAL', 'Classe original', '2000-01-01 00:00:00', '2000-01-01 00:00:00')");
    $st = $db->prepare('INSERT INTO processos (id, numero_processo, classe_id, data_fato, sigiloso, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute(['PRC-EXISTENTE', $number, 'CLA-ORIGINAL', '2010-05-04', 1, '2000-01-01 00:00:00', '2000-01-01 00:00:00']);
    $row = $db->query("SELECT classe_id, sigiloso FROM processos WHERE id='PRC-EXISTENTE'")->fetch(PDO::FETCH_ASSOC);
    $assert($row === ['classe_id' => 'CLA-ORIGINAL', 'sigiloso' => 1], 'Fixture do processo sigiloso não foi criada corretamente.');
};

// Processo existente: agendar deve preservar sigilo e classe existentes quando payload não traz classe nova.
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$acceptedId = (new SalaAudienciasPautaService($pdo))->create($basePayload, null);
$existing = $pdo->query("SELECT classe_id, sigiloso FROM processos WHERE id='PRC-EXISTENTE'")->fetch(PDO::FETCH_ASSOC);
$accepted = $pdo->prepare('SELECT id_pauta, processo, status, ativo FROM sala_audiencias_pauta WHERE id_pauta=:id');
$accepted->execute([':id' => $acceptedId]);
$acceptedRow = $accepted->fetch(PDO::FETCH_ASSOC);
$assert($existing === ['classe_id' => 'CLA-ORIGINAL', 'sigiloso' => 1], 'Agendamento aceito alterou classe ou sigilo do processo existente.');
$assert($acceptedRow !== false && $acceptedRow['processo'] === 'PRC-EXISTENTE' && $acceptedRow['status'] === 'designada' && (int) $acceptedRow['ativo'] === 1, 'Agendamento aceito não persistiu a pauta esperada.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn() === 1, 'Agendamento aceito criou ou removeu classe inesperadamente.');
fwrite(STDOUT, "PASS processo existente: pauta aceita preservou classe e sigilo.\n");

// Pauta ativa do mesmo processo deve rejeitar dentro da transação e preservar snapshot integral.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$blockingDate = $basePayload['data_audiencia'];
$st = $pdo->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, numero_sequencial, ano, processo, tipo_audiencia, data_audiencia, hora_audiencia, localizacao, status, ativo, created_at, updated_at) VALUES ('SAP-ATIVA', 1, YEAR(CURDATE()), 'PRC-EXISTENTE', 'Instrução', :data, '09:00:00', 'Sala sintética', 'designada', 1, '2000-01-01 00:00:00', '2000-01-01 00:00:00')");
$st->execute([':data' => $blockingDate]);
$before = $snapshot($pdo);
$payloadWithNewClass = $basePayload + ['classe_codigo' => 'NOVACLASSE', 'classe_descricao' => 'Classe proposta'];
try {
    (new SalaAudienciasPautaService($pdo))->create($payloadWithNewClass, null);
    throw new RuntimeException('Pauta ativa do processo deveria rejeitar o cadastro.');
} catch (InvalidArgumentException $exception) {
    if (!str_contains($exception->getMessage(), 'Já existe audiência ativa para este número de processo')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Rejeição por pauta ativa alterou o snapshot de processo, classe ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após rejeição por pauta ativa.');
fwrite(STDOUT, "PASS pauta ativa: rejeição preservou snapshot integral.\n");

// Trigger AFTER INSERT comprova falha depois de a escrita da pauta ser iniciada; o rollback deve desfazer todos os writes.
$reset($pdo);
$seedExistingProcess($pdo, $basePayload['numero_processo']);
$before = $snapshot($pdo);
$pdo->exec("CREATE TRIGGER rei88_fail_after_pauta_insert AFTER INSERT ON sala_audiencias_pauta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI88_FAIL_AFTER_Pauta_INSERT'");
try {
    (new SalaAudienciasPautaService($pdo))->create($payloadWithNewClass, null);
    throw new RuntimeException('Falha posterior ao insert da pauta não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'REI88_FAIL_AFTER_Pauta_INSERT')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Falha após insert deixou alterações de classe, processo ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após falha induzida.');
fwrite(STDOUT, "PASS falha pós-insert: rollback reverteu classe, processo e pauta.\n");

// Processo inexistente: falha após criação deve reverter processo, classe e pauta.
$reset($pdo);
$before = $snapshot($pdo);
$newProcessPayload = array_replace($basePayload, [
    'numero_processo' => '0000002-54.2020.8.05.0001',
    'classe_codigo' => 'CLASSE-NOVA',
    'classe_descricao' => 'Classe nova sintética',
]);
$pdo->exec("CREATE TRIGGER rei88_fail_after_pauta_insert AFTER INSERT ON sala_audiencias_pauta FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI88_FAIL_AFTER_Pauta_INSERT'");
try {
    (new SalaAudienciasPautaService($pdo))->create($newProcessPayload, null);
    throw new RuntimeException('Falha do processo novo não ocorreu.');
} catch (PDOException $exception) {
    if (!str_contains($exception->getMessage(), 'REI88_FAIL_AFTER_Pauta_INSERT')) {
        throw $exception;
    }
}
$assert($snapshot($pdo) === $before, 'Rollback de processo novo deixou processo, classe ou pauta.');
$assert(!$pdo->inTransaction(), 'Transação permaneceu aberta após rollback de processo novo.');
fwrite(STDOUT, "PASS processo novo com falha: rollback removeu processo, classe e pauta.\n");

// Processo novo com fluxo aceito deve persistir o processo, sua classe e a pauta relacionada.
$reset($pdo);
$newProcessPayload['numero_processo'] = '0000002-54.2020.8.05.0001';
$newId = (new SalaAudienciasPautaService($pdo))->create($newProcessPayload, null);
$newProcess = $pdo->prepare('SELECT id, classe_id, sigiloso FROM processos WHERE numero_processo=:numero');
$newProcess->execute([':numero' => $newProcessPayload['numero_processo']]);
$newProcessRow = $newProcess->fetch(PDO::FETCH_ASSOC);
$newPauta = $pdo->prepare('SELECT processo, status FROM sala_audiencias_pauta WHERE id_pauta=:id');
$newPauta->execute([':id' => $newId]);
$newPautaRow = $newPauta->fetch(PDO::FETCH_ASSOC);
$class = $pdo->prepare('SELECT codigo, nome FROM classes WHERE id=:id');
$class->execute([':id' => $newProcessRow['classe_id'] ?? '']);
$classRow = $class->fetch(PDO::FETCH_ASSOC);
$assert($newProcessRow !== false && $newProcessRow['id'] !== '' && (int) $newProcessRow['sigiloso'] === 0, 'Fluxo aceito não criou processo novo com sigilo padrão.');
$assert($classRow === ['codigo' => 'CLASSENOVA', 'nome' => 'Classe Nova Sintética'], 'Fluxo aceito não persistiu a classe nova esperada.');
$assert($newPautaRow !== false && $newPautaRow['processo'] === $newProcessRow['id'] && $newPautaRow['status'] === 'designada', 'Pauta aceita não foi vinculada ao processo novo.');
$assert(!$pdo->inTransaction(), 'Fluxo aceito deixou transação aberta.');
fwrite(STDOUT, "PASS processo novo: fluxo aceito persistiu processo, classe e pauta.\n");

echo "PASS 5 cenários SalaAudienciasPautaService::create() (MySQL/InnoDB sintético).\n";
