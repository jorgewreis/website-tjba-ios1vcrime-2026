<?php
declare(strict_types=1);

$failureStage = 'setup';
set_exception_handler(static function (Throwable $exception) use (&$failureStage): void {
    $allowed = ['setup','guard','positive_create','negative','rollback_create','rollback_update','positive_update','positive_delete','child_uncertain'];
    $stage = in_array($failureStage, $allowed, true) ? $failureStage : 'setup';
    fwrite(STDERR, 'REI69_DIRECT_FAILURE code=' . $stage . "\n");
    exit(1);
});
if (getenv('REI69DIRETOS_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Recusado: execute somente pelo runner MySQL descartável.');
}
if (!extension_loaded('pdo_mysql') || !function_exists('proc_open')) {
    throw new RuntimeException('Recusado: pdo_mysql e proc_open são necessários no executor isolado.');
}

$repoRoot = getenv('REI69DIRETOS_TEST_REPO_ROOT') ?: '/repo';
if ($repoRoot !== '/repo') throw new RuntimeException('Raiz da fixture inválida.');
$appRoot = realpath($repoRoot . '/app');
$endpointRoot = realpath($repoRoot . '/public/api/processos-partes');
$testPath = realpath(__FILE__);
if ($appRoot === false || $endpointRoot === false || $testPath === false
    || is_file($repoRoot . '/.env') || str_contains($repoRoot, '.env')) {
    throw new RuntimeException('Recusado: raízes da fixture inválidas ou arquivo .env presente.');
}

$dsn = (string) getenv('REI69DIRETOS_MYSQL_DSN');
if ($dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_diretos_test;charset=utf8mb4' || getenv('REI69DIRETOS_MYSQL_USER') !== 'root' || (string) getenv('REI69DIRETOS_MYSQL_PASSWORD') !== '') {
    throw new RuntimeException('Recusado: use somente socket local do MySQL descartável.');
}
$expectedMarker = (string) getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER');
if (preg_match('/^[a-f0-9]{64}$/D', $expectedMarker) !== 1) {
    throw new RuntimeException('Recusado: marcador sintético ausente ou inválido.');
}

$pdo = new PDO($dsn, (string) getenv('REI69DIRETOS_MYSQL_USER'), (string) getenv('REI69DIRETOS_MYSQL_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
if ($database !== 'rei69_diretos_test' || !str_starts_with($version, '8.4.') || !str_contains($versionComment, 'MySQL')) {
    throw new RuntimeException('Recusado: a fixture exige MySQL 8.4 no banco rei69_diretos_test.');
}
$tables = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $tables);
if (count($tables) !== 1
    || strtolower((string) ($tables[0]['table_name'] ?? '')) !== 'rei69_diretos_test_guard'
    || strtolower((string) ($tables[0]['engine'] ?? '')) !== 'innodb') {
    throw new RuntimeException('Recusado: o schema deve conter somente a sentinela InnoDB do runner.');
}
$marker = $pdo->query('SELECT marker FROM rei69_diretos_test_guard LIMIT 1')->fetchColumn();
if (!is_string($marker) || !hash_equals($expectedMarker, $marker)) {
    throw new RuntimeException('Recusado: a sentinela não corresponde a esta execução.');
}

$childUncertain = false;
$fixtureTables = ['processos_partes', 'sync_log', 'logs_sistema', 'partes', 'processos', 'usuario_setor', 'setores'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $expectedMarker, &$childUncertain): void {
    if ($childUncertain) { return; }
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $remaining = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $marker = $pdo->query('SELECT marker FROM rei69_diretos_test_guard LIMIT 1')->fetchColumn();
        if ($remaining !== ['rei69_diretos_test_guard'] || !is_string($marker) || !hash_equals($expectedMarker, $marker)) {
            throw new RuntimeException('Schema temporário ou sentinela não passou na verificação de limpeza.');
        }
        fwrite(STDOUT, "CLEANUP schema sintético removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Falha ao remover/verificar schema sintético: ' . $exception->getMessage() . "\n");
        exit(1);
    }
});

$expectedLower = (string) getenv('REI69DIRETOS_EXPECT_LOWER_CASE');
if (!in_array($expectedLower, ['0','1'], true) || (string) $pdo->query('SELECT @@lower_case_table_names')->fetchColumn() !== $expectedLower) throw new RuntimeException('Modo de tabelas não corresponde ao runner.');
$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$pdo->exec("SET SESSION time_zone = '+00:00'");
$ddl = [
    "CREATE TABLE setores (id_setor CHAR(30) NOT NULL PRIMARY KEY, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE usuario_setor (usuario CHAR(30) NOT NULL, setor CHAR(30) NOT NULL, PRIMARY KEY (usuario,setor), CONSTRAINT fk_rei69_user_sector FOREIGN KEY (setor) REFERENCES setores(id_setor)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id CHAR(30) NOT NULL PRIMARY KEY, numero_processo VARCHAR(64) NULL, id_setor_responsavel CHAR(30) NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE logs_sistema (id_log_sistema CHAR(30) NOT NULL PRIMARY KEY, usuario CHAR(30) NOT NULL, modulo VARCHAR(50) NOT NULL, acao VARCHAR(50) NOT NULL, tabela_afetada VARCHAR(50) NULL, registro_id CHAR(30) NULL, dados_anteriores TEXT NULL, dados_novos TEXT NULL, ip VARCHAR(45) NOT NULL, data_hora DATETIME NULL, KEY idx_registro (registro_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE partes (id CHAR(30) NOT NULL PRIMARY KEY, nome_completo VARCHAR(180) NULL, cpf VARCHAR(32) NULL, sexo VARCHAR(10) NULL, data_nascimento DATE NULL, profissao VARCHAR(255) NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos_partes (id CHAR(30) NOT NULL PRIMARY KEY, processo_id CHAR(30) NOT NULL, parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, polo VARCHAR(50) NOT NULL, prioridade_mulher TINYINT(1) NOT NULL DEFAULT 0, prioridade_crianca_adolescente TINYINT(1) NOT NULL DEFAULT 0, prioridade_idoso TINYINT(1) NOT NULL DEFAULT 0, prioridade_pessoa_deficiencia TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, KEY ix_rei69_link_process (processo_id), CONSTRAINT fk_rei69_link_process FOREIGN KEY (processo_id) REFERENCES processos(id), CONSTRAINT fk_rei69_link_part FOREIGN KEY (parte_id) REFERENCES partes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sync_log (parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO setores (id_setor,ativo) VALUES ('SET-CARTORIO',1),('SET-GABINETE',1),('SET-INATIVO',0)");
$pdo->exec("INSERT INTO usuario_setor (usuario,setor) VALUES ('USR-1','SET-CARTORIO'),('USR-2','SET-GABINETE'),('USR-4','SET-INATIVO')");
$pdo->exec("INSERT INTO processos (id,numero_processo,id_setor_responsavel) VALUES ('PRC-1','CNJ-SINTETICO-1','SET-CARTORIO'),('PRC-2','CNJ-SINTETICO-2','SET-GABINETE'),('PRC-3','CNJ-SINTETICO-3',NULL),('PRC-4','CNJ-SINTETICO-4','SET-INATIVO')");
$pdo->exec("INSERT INTO partes (id,nome_completo,cpf,sexo,data_nascimento,profissao,created_at,updated_at) VALUES ('PAR-1','Pessoa Sintética 1','00000000000','X','2000-01-01','Réu','2026-01-01 00:00:00','2026-01-01 00:00:00'),('PAR-2','Pessoa Sintética 2','00000000001','X','2000-01-01','Advogada anterior','2026-01-01 00:00:00','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES ('VNC-1','PRC-1','PAR-1','reu','passivo',NOW(),NOW()),('VNC-2','PRC-2','PAR-2','vitima','ativo',NOW(),NOW()),('VNC-3','PRC-3','PAR-1','testemunha','ativo',NOW(),NOW()),('VNC-4','PRC-4','PAR-1','reu','passivo',NOW(),NOW())");

// Copia só as dependências estritamente necessárias para uma fixture local em /tmp.
// A configuração é stub sintético; config real e .env nunca entram no executor.
$tempBase = realpath(sys_get_temp_dir());
if ($tempBase === false || is_link(sys_get_temp_dir())) {
    throw new RuntimeException('Recusado: diretório temporário inseguro.');
}
$lab = $tempBase . '/rei69-' . bin2hex(random_bytes(16));
if (!mkdir($lab, 0700) || realpath($lab) !== $lab) {
    throw new RuntimeException('Não foi possível criar diretório isolado da fixture.');
}
if (!mkdir($lab . '/config', 0700)) {
    rmdir($lab);
    throw new RuntimeException('Não foi possível criar configuração sintética isolada.');
}
$copyRelative = static function (string $root, string $relative, string $destinationRoot, string $targetRelative): void {
    if (str_starts_with($relative, '/') || str_contains($relative, '..') || str_contains($targetRelative, '..')) {
        throw new RuntimeException('Caminho inválido na fixture.');
    }
    $source = realpath($root . '/' . $relative);
    $prefix = rtrim($root, '/') . '/';
    if ($source === false || !str_starts_with($source, $prefix) || is_link($root . '/' . $relative) || !is_file($source)) {
        throw new RuntimeException('Arquivo fonte fora da lista regular da fixture.');
    }
    $target = $destinationRoot . '/' . $targetRelative;
    $parent = dirname($target);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Falha ao criar pasta da fixture.');
    }
    if (is_link($parent) || !copy($source, $target)) {
        throw new RuntimeException('Falha ao copiar arquivo para fixture temporária.');
    }
};
$appFiles = [
    'helpers/ApiAccessGuard.php', 'helpers/BootstrapHelper.php', 'helpers/UserAccessHelper.php',
    'helpers/ProcessoSetorAuthorization.php', 'helpers/IdGenerator.php',
    'helpers/TextFormatter.php', 'helpers/ParteDocumentoHelper.php',
    'access/AccessEvaluator.php', 'access/ProfileNormalizer.php', 'access/PageAccessRegistry.php',
    'access/AccessAreas.php', 'access/AccessPolicyVersion.php',
    'repositories/ProcessosPartesRepository.php', 'repositories/PartesDocumentosRepository.php',
];
foreach ($appFiles as $relative) {
    $copyRelative($appRoot, $relative, $lab, 'app/' . $relative);
}
$copyRelative($appRoot, 'repositories/PartesRepository.php', $lab, 'app/repositories/PartesRepository.php');
foreach (['create.php', 'update.php', 'delete.php'] as $endpoint) {
    $copyRelative($endpointRoot, $endpoint, $lab, 'public/api/processos-partes/' . $endpoint);
}
file_put_contents($lab . '/app/helpers/AutoloadHelper.php', "<?php\ndeclare(strict_types=1);\n");
file_put_contents($lab . '/app/helpers/Conection.php', <<<'PHP'
<?php
declare(strict_types=1);
function appIsHttpsRequest(): bool { return false; }
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO {
        if (getenv('REI69DIRETOS_MYSQL_DSN') !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_diretos_test;charset=utf8mb4' || getenv('REI69DIRETOS_MYSQL_USER') !== 'root' || (string) getenv('REI69DIRETOS_MYSQL_PASSWORD') !== '') throw new RuntimeException('Conexão de teste inválida.');
        $pdo = new PDO((string) getenv('REI69DIRETOS_MYSQL_DSN'), (string) getenv('REI69DIRETOS_MYSQL_USER'), (string) getenv('REI69DIRETOS_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
        $marker = $pdo->query('SELECT marker FROM rei69_diretos_test_guard LIMIT 1')->fetchColumn();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei69_diretos_test' || !is_string($marker) || !hash_equals((string) getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER'), $marker)) throw new RuntimeException('Sentinela inválida.');
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        if (getenv('REI69_TEST_LOCK_TIMEOUT') === '1') $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        return $pdo;
    }
}
PHP);
file_put_contents($lab . '/app/helpers/UsuarioPerfisAutorizacao.php', <<<'PHP'
<?php
declare(strict_types=1);
final class UsuarioPerfisAutorizacao {
    public static function perfisDoUsuario(string $id): array {
        $profiles = [(string) getenv('TEST_PROFILE')];
        if (getenv('TEST_SUPPRESS_ADMIN') === '1') $profiles = array_values(array_diff($profiles, ['ADMIN']));
        return $profiles;
    }
}
PHP);
file_put_contents($lab . '/config/security.php', <<<'PHP'
<?php
declare(strict_types=1);
final class Security {
    public static function isAuthenticated(): bool { return getenv('TEST_AUTH') === '1' && getenv('TEST_SESSION') === '1' && !empty($_SESSION['id_usuario']); }
}
PHP);
$snapshot = static function (PDO $db): array {
    $out = [];
    foreach (['processos_partes', 'partes', 'processos', 'setores', 'usuario_setor', 'sync_log', 'logs_sistema'] as $table) {
        $out[$table] = ['schema' => $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM), 'rows' => $db->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC)];
    }
    return $out;
};
$invoke = static function (string $endpoint, string $user, string $profile, array $payload, string $method = 'POST', string $origin = 'http://example.invalid', bool $authenticated = true, bool $hasSession = true, bool $failSync = false) use ($lab, &$childUncertain, &$failureStage): array {
    $script = $lab . '/public/api/processos-partes/' . $endpoint;
    $code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = (string) getenv('TEST_METHOD');
$_SERVER['HTTP_HOST'] = 'example.invalid';
$_SERVER['HTTP_ORIGIN'] = (string) getenv('TEST_ORIGIN');
$_SESSION = getenv('TEST_SESSION') === '1' ? ['id_usuario' => (string) getenv('TEST_USER')] : [];
$payload = json_decode((string) getenv('TEST_PAYLOAD'), true);
$_POST = is_array($payload) ? $payload : [];
$_GET = is_array($payload) ? $payload : [];
register_shutdown_function(static function (): void { $code = http_response_code(); fwrite(STDERR, 'HTTP:' . ($code ?: 200)); });
require (string) getenv('TEST_ENDPOINT');
PHP;
    $environment = [
        'REI69DIRETOS_TEST_DISPOSABLE_MARKER' => (string) getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER'),
        'REI69DIRETOS_MYSQL_DSN' => (string) getenv('REI69DIRETOS_MYSQL_DSN'),
        'REI69DIRETOS_MYSQL_USER' => (string) getenv('REI69DIRETOS_MYSQL_USER'),
        'REI69DIRETOS_MYSQL_PASSWORD' => (string) getenv('REI69DIRETOS_MYSQL_PASSWORD'),
        'REI69_TEST_LOCK_TIMEOUT' => '1',
        'TEST_AUTH' => $authenticated ? '1' : '0', 'TEST_SESSION' => $hasSession ? '1' : '0',
        'TEST_USER' => $user, 'TEST_PROFILE' => $profile,
        'TEST_METHOD' => $method, 'TEST_ORIGIN' => $origin,
        'TEST_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR),
        'TEST_ENDPOINT' => $script,
        'TEST_FAIL_SYNC' => $failSync ? '1' : '0',
    ];
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $lab, $environment);
    if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar PHP CLI para endpoint sintético.');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 10.0;
    $status = proc_get_status($process);
    while ($status['running'] && microtime(true) < $deadline) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        if (strlen($stdout) > 1048576 || strlen($stderr) > 1048576) break;
        usleep(10000);
        $status = proc_get_status($process);
    }
    if ($status['running']) {
        proc_terminate($process, 9);
        $killDeadline = microtime(true) + 2.0;
        do {
            usleep(10000);
            $status = proc_get_status($process);
        } while ($status['running'] && microtime(true) < $killDeadline);
        if ($status['running']) { $childUncertain = true; $failureStage = 'child_uncertain'; throw new RuntimeException('Encerramento do processo de teste não comprovado.'); }
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        throw new RuntimeException('Processo de teste excedeu limite.');
    }
    $exit = $status['exitcode'];
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($process);
    if ($exit !== 0) throw new RuntimeException('Endpoint CLI falhou.');
    $statusText = substr($stderr, (int) strrpos($stderr, 'HTTP:') + 5);
    if ($statusText === '' || !ctype_digit($statusText)) throw new RuntimeException('Endpoint sem status HTTP controlado: ' . $endpoint . ' (' . $stderr . ')');
    $body = $stdout === '' ? [] : json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    return [(int) $statusText, is_array($body) ? $body : []];
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
try {
    $failureStage = 'negative';
    $payload = ['processo_id' => 'PRC-1', 'parte_id' => 'PAR-2', 'tipo_parte' => 'advogado', 'polo' => 'passivo'];
    $before = $snapshot($pdo);
    foreach ([['USR-2','PRC-1'], ['USR-3','PRC-1'], ['USR-1','PRC-3'], ['USR-4','PRC-4']] as [$user,$process]) {
        [$status] = $invoke('create.php', $user, 'SERVIDOR-CARTORIO', array_replace($payload, ['processo_id'=>$process]));
        $assert($status === 403 && $snapshot($pdo) === $before, 'Negativa setorial de create falhou.');
    }
    [$missing] = $invoke('create.php', 'USR-1', 'SERVIDOR-CARTORIO', array_replace($payload, ['parte_id'=>'INEXISTENTE']));
    $assert($missing === 400 && $snapshot($pdo) === $before, 'Pessoa ausente não foi rejeitada sem efeitos.');
    $failureStage = 'rollback_create';
    $pdo->exec("CREATE TRIGGER rei69_fail_sync BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic sync failure'");
    try {
        $pdo->exec("UPDATE partes SET profissao='falha sintetica' WHERE id='PAR-2'");
        throw new RuntimeException('Trigger de controle não rejeitou atualização.');
    } catch (PDOException $expected) {
        $assert($expected->getCode() === '45000' && str_contains($expected->getMessage(), 'synthetic sync failure') && $snapshot($pdo) === $before, 'Trigger de controle não produziu falha específica sem efeitos.');
    }
    [$failure] = $invoke('create.php', 'USR-1', 'SERVIDOR-CARTORIO', $payload);
    $assert($failure === 500 && $snapshot($pdo) === $before, 'Falha da sincronização real não reverteu create.');
    $pdo->exec('DROP TRIGGER rei69_fail_sync');
    $failureStage = 'positive_create';
    [$status,$body] = $invoke('create.php', 'USR-1', 'SERVIDOR-CARTORIO', $payload);
    $assert($status === 201 && isset($body['id']) && $pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Advogado', 'Create ou sincronização real não persistiu.');
    $created = $snapshot($pdo);
    $assert(count($created['processos_partes']['rows']) === count($before['processos_partes']['rows']) + 1, 'Create não inseriu exatamente um vínculo.');
    $failureStage = 'guard';
    foreach (['create.php','update.php','delete.php'] as $endpoint) {
        $request = $endpoint === 'create.php' ? $payload : ['id'=>$body['id'],'tipo_parte'=>'defensor_publico'];
        [$anonymous] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $request, 'POST', 'http://example.invalid', false, false);
        [$method] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $request, 'GET');
        [$origin] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $request, 'POST', 'http://evil.invalid');
        $assert($anonymous === 401 && $method === 403 && $origin === 403 && $snapshot($pdo) === $created, 'Guarda de sessão, método ou origem falhou.');
    }
    $failureStage = 'negative';
    foreach (['update.php','delete.php'] as $endpoint) {
        [$denied] = $invoke($endpoint, 'USR-2', 'SERVIDOR-CARTORIO', ['id'=>$body['id'],'tipo_parte'=>'defensor_publico']);
        [$nullSector] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', ['id'=>'VNC-3','tipo_parte'=>'defensor_publico']);
        [$inactiveSector] = $invoke($endpoint, 'USR-4', 'SERVIDOR-CARTORIO', ['id'=>'VNC-4','tipo_parte'=>'defensor_publico']);
        [$absent] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', ['id'=>'VNC-INEXISTENTE','tipo_parte'=>'defensor_publico']);
        $assert($denied === 404 && $nullSector === 404 && $inactiveSector === 404 && $absent === 404 && $snapshot($pdo) === $created, 'Negativa ou vínculo ausente alterou dados.');
    }
    $failureStage = 'rollback_update';
    $pdo->exec("CREATE TRIGGER rei69_fail_sync BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic sync failure'");
    [$failure] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id'=>$body['id'],'tipo_parte'=>'defensor_publico']);
    $assert($failure === 500 && $snapshot($pdo) === $created, 'Falha da sincronização real não reverteu update.');
    $pdo->exec('DROP TRIGGER rei69_fail_sync');
    $failureStage = 'positive_update';
    [$status] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id'=>$body['id'],'tipo_parte'=>'defensor_publico']);
    $assert($status === 200 && $pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Defensor Público' && $pdo->query('SELECT tipo_parte FROM processos_partes WHERE id=' . $pdo->quote($body['id']))->fetchColumn() === 'defensor_publico', 'Update ou sincronização real falhou.');
    $failureStage = 'positive_delete';
    [$status,$deleted] = $invoke('delete.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id'=>$body['id']]);
    $check = $pdo->prepare('SELECT COUNT(*) FROM processos_partes WHERE id=:id');
    $check->execute([':id'=>$body['id']]);
    $assert($status === 200 && ($deleted['deleted'] ?? false) === true && (int)$check->fetchColumn() === 0 && $pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Defensor Público', 'Delete ou preservação da profissão falhou.');
    fwrite(STDOUT, "PASS REI69 direct endpoints synthetic MySQL regression\n");
} finally {
    if ($childUncertain) { throw new RuntimeException('Estado do subprocesso incerto; limpeza de fixture preservada.'); }
    // O laboratório é um tmpfs descartável, criado e validado no diretório temporário.
    $resolvedLab = realpath($lab);
    $prefix = rtrim($tempBase, '/') . '/rei69-';
    if ($resolvedLab === false || !str_starts_with($resolvedLab, $prefix) || is_link($lab)) {
        throw new RuntimeException('Diretório de fixture saiu do tmpfs ou virou link.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolvedLab, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isLink()) throw new RuntimeException('Link inesperado na fixture temporária.');
        if ($item->isDir()) rmdir($item->getPathname()); else unlink($item->getPathname());
    }
    rmdir($resolvedLab);
}
