<?php
declare(strict_types=1);

$failureStage = 'setup';
$failureDetail = 'general';
set_exception_handler(static function (Throwable $exception) use (&$failureStage, &$failureDetail): void {
    $allowed = ['setup','guard','positive_create','negative','negative_create_sector','negative_create_unassigned','negative_create_inactive','negative_create_missing','negative_update','negative_delete','rollback_create','rollback_update','positive_update','positive_delete','concurrency','child_uncertain','monitor_setup','monitor_positive','monitor_create','monitor_row','monitor_row_prepare','monitor_row_execute','monitor_row_fetch','monitor_tasks','monitor_rollback','cnj_setup','cnj_wait_a','cnj_wait_b','cnj_finish','cnj_check','c1_setup','c1_wait','c1_finish','c1_check','c2_setup','c2_person_wait','c2_sector_wait','c2_finish','c2_check','c3_setup','c3_person_wait','c3_pauta_wait','c3_finish','c3_check','c3_direct','c3_pauta','c3_tipo','c3_snapshot','c4_setup','c4_link_wait','c4_direct_wait','c4_finish','c4_pauta_finish','c4_direct_finish','c4_check','c5_setup','c5_first_wait','c5_second_wait','c5_finish','c5_check','c6_controls','c6_prepare','c6_wait','c6_finish','c6_check'];
    // The positive read reports only fixed categories, never database messages or values.
    if (in_array($failureStage, ['monitor_row_prepare','monitor_row_execute','monitor_row_fetch'], true) && $exception instanceof PDOException) {
        $state = strtolower((string) $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $failureDetail = $driverCode === 1267 ? 'monitor_sql_collation' : (in_array($state, ['42s22','42s02','23000','40001','hy000'], true) ? 'monitor_sql_'.$state : 'monitor_sql_other');
    }
    if ($failureDetail === 'general') {
        $failureDetail = $exception instanceof PDOException ? 'pdo'
            : ($exception instanceof LogicException ? 'logic'
                : ($exception instanceof RuntimeException ? 'runtime_line_'.$exception->getLine()
                    : ($exception instanceof JsonException ? 'json' : 'other')));
    }
    $stage = in_array($failureStage, $allowed, true) ? $failureStage : 'setup';
    $details=['general','child_exit','child_fatal','pdo','logic','runtime','json','other','monitor_sql_42s22','monitor_sql_42s02','monitor_sql_23000','monitor_sql_40001','monitor_sql_hy000','monitor_sql_other','monitor_sql_collation','monitor_invalid','monitor_logic','monitor_runtime','monitor_error','spawn','early_exit','early_401','early_403','early_404','early_500','pauta_connect','pauta_require','pauta_construct','pauta_sql_42s22','pauta_sql_42s02','pauta_sql_23000','pauta_sql_40001','pauta_sql_hy000','pauta_sql_other','pauta_acl','pauta_invalid','pauta_runtime','pauta_noop','pauta_error','pauta_fatal','connection_id','wait_query','wait_timeout','wait_no_edge','wait_other_table','wait_other_index','wait_key_padded','wait_key_other','wait_key_null','finish_timeout','exit','assert','snapshot_pauta','snapshot_other'];
    $detail=preg_match('/^(?:runtime_line_[0-9]{1,4}|child_http_[1-5][0-9]{2}|child_fatal_line_[0-9]{1,4}|child_fatal_[A-Za-z0-9_.-]{1,80}_[0-9]{1,4})$/D', $failureDetail) === 1 ? $failureDetail : (in_array($failureDetail,$details,true) ? $failureDetail : 'general');
    fwrite(STDERR, 'REI69_DIRECT_FAILURE code=' . $stage . ' detail=' . $detail . "\n");
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
$fixtureTables = ['rei69_cnj_gate', 'gabinete_monitoramento_eletronico_subtarefas', 'gabinete_monitoramento_eletronico', 'sala_audiencias_pauta_parte', 'sala_audiencias_pauta', 'processo_prioridades', 'gabinete_reu_preso_processo', 'gabinete_reu_preso', 'classes', 'processos_partes', 'sync_log', 'logs_sistema', 'partes', 'processos', 'usuario_setor', 'setores'];
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
    "CREATE TABLE classes (id VARCHAR(40) PRIMARY KEY,codigo VARCHAR(40),nome VARCHAR(80),created_at DATETIME,updated_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(40) PRIMARY KEY,ativo INT,processo VARCHAR(40),numero_sequencial INT,ano INT,tipo_audiencia VARCHAR(80),data_audiencia DATE,hora_audiencia TIME,status VARCHAR(40),data_hora_designacao DATETIME,hora_inicio TIME,hora_fim TIME,duracao_estimada_minutos INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sala_audiencias_pauta_parte (id_parte VARCHAR(40) PRIMARY KEY,pauta VARCHAR(40),parte_id_global VARCHAR(40),processo_parte_id VARCHAR(40),nome_parte VARCHAR(100),tipo_parte VARCHAR(40),tipo_defensor VARCHAR(40),defensor_publico_id VARCHAR(40),defensor_publico_outro VARCHAR(100),defensor_privado_id VARCHAR(40),defensor_privado_outro VARCHAR(100),maioridade VARCHAR(40),expedicao VARCHAR(40),cumprimento VARCHAR(40),id_documento_expedido VARCHAR(40),id_documento_cumprido VARCHAR(40),contatos TEXT,criado_por VARCHAR(40),atualizado_por VARCHAR(40),ativo INT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processo_prioridades (id VARCHAR(40) PRIMARY KEY,processo_id VARCHAR(40),codigo VARCHAR(80),origem VARCHAR(80),regra_detalhe TEXT,created_at DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE gabinete_reu_preso (id VARCHAR(40) PRIMARY KEY,status VARCHAR(40)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE gabinete_reu_preso_processo (id VARCHAR(40) PRIMARY KEY,processo_id VARCHAR(40),reu_preso_id VARCHAR(40),situacao VARCHAR(40)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE setores (id_setor CHAR(30) NOT NULL PRIMARY KEY, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE usuario_setor (usuario CHAR(30) NOT NULL, setor CHAR(30) NOT NULL, PRIMARY KEY (usuario,setor), CONSTRAINT fk_rei69_user_sector FOREIGN KEY (setor) REFERENCES setores(id_setor)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id CHAR(30) NOT NULL PRIMARY KEY, numero_processo VARCHAR(64) NULL, classe_id VARCHAR(40) NULL, data_fato DATE NULL, sigiloso INT NULL, created_at DATETIME NULL, id_setor_responsavel CHAR(30) NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE logs_sistema (id_log_sistema CHAR(30) NOT NULL PRIMARY KEY, usuario CHAR(30) NOT NULL, modulo VARCHAR(50) NOT NULL, acao VARCHAR(50) NOT NULL, tabela_afetada VARCHAR(50) NULL, registro_id CHAR(30) NULL, dados_anteriores TEXT NULL, dados_novos TEXT NULL, ip VARCHAR(45) NOT NULL, data_hora DATETIME NULL, KEY idx_registro (registro_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE partes (id CHAR(30) NOT NULL PRIMARY KEY, nome_completo VARCHAR(180) NULL, cpf VARCHAR(32) NULL, sexo VARCHAR(10) NULL, data_nascimento DATE NULL, profissao VARCHAR(255) NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos_partes (id CHAR(30) NOT NULL PRIMARY KEY, processo_id CHAR(30) NOT NULL, parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, polo VARCHAR(50) NOT NULL, prioridade_mulher TINYINT(1) NOT NULL DEFAULT 0, prioridade_crianca_adolescente TINYINT(1) NOT NULL DEFAULT 0, prioridade_idoso TINYINT(1) NOT NULL DEFAULT 0, prioridade_pessoa_deficiencia TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, KEY ix_rei69_link_process (processo_id), CONSTRAINT fk_rei69_link_process FOREIGN KEY (processo_id) REFERENCES processos(id), CONSTRAINT fk_rei69_link_part FOREIGN KEY (parte_id) REFERENCES partes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sync_log (parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

$pdo->exec("INSERT INTO classes (id,codigo,nome,created_at,updated_at) VALUES ('C','C','Classe sintetica',NOW(),NOW())");
$pdo->exec("INSERT INTO setores (id_setor,ativo) VALUES ('SET-CARTORIO',1),('SET-GABINETE',1),('SET-INATIVO',0)");
$pdo->exec("INSERT INTO usuario_setor (usuario,setor) VALUES ('USR-1','SET-CARTORIO'),('USR-2','SET-GABINETE'),('USR-4','SET-INATIVO')");
$pdo->exec("INSERT INTO processos (id,numero_processo,id_setor_responsavel) VALUES ('PRC-1','CNJ-SINTETICO-1','SET-CARTORIO'),('PRC-2','CNJ-SINTETICO-2','SET-GABINETE'),('PRC-3','CNJ-SINTETICO-3',NULL),('PRC-4','CNJ-SINTETICO-4','SET-INATIVO')");
$pdo->exec("UPDATE processos SET classe_id='C', sigiloso=0, created_at=NOW() WHERE id IN ('PRC-1','PRC-2','PRC-3','PRC-4')");
$pdo->exec("INSERT INTO partes (id,nome_completo,cpf,sexo,data_nascimento,profissao,created_at,updated_at) VALUES ('PAR-1','Pessoa Sintética 1','00000000000','X','2000-01-01','Réu','2026-01-01 00:00:00','2026-01-01 00:00:00'),('PAR-2','Pessoa Sintética 2','00000000001','X','2000-01-01','Advogada anterior','2026-01-01 00:00:00','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES ('VNC-1','PRC-1','PAR-1','reu','passivo',NOW(),NOW()),('VNC-2','PRC-2','PAR-2','vitima','ativo',NOW(),NOW()),('VNC-3','PRC-3','PAR-1','testemunha','ativo',NOW(),NOW()),('VNC-4','PRC-4','PAR-1','reu','passivo',NOW(),NOW()),('VNC-5','PRC-2','PAR-1','reu','passivo',NOW(),NOW())");

$pdo->exec("INSERT INTO sala_audiencias_pauta (id_pauta,ativo,processo,numero_sequencial,ano,tipo_audiencia,data_audiencia,hora_audiencia,status) VALUES ('PA',1,'PRC-1',1,2026,'instrucao','2030-01-01','10:00:00','designada')");
$pdo->exec("INSERT INTO sala_audiencias_pauta_parte (id_parte,pauta,parte_id_global,processo_parte_id,nome_parte,tipo_parte,maioridade,expedicao,cumprimento,ativo) VALUES ('PP-A','PA','PAR-1','VNC-1','Pessoa Sintetica 1','vitima','maior','Não expedido','pendente',1)");

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
    'helpers/ApiAccessGuard.php', 'helpers/ApiErrorReporter.php', 'helpers/BootstrapHelper.php', 'helpers/UserAccessHelper.php',
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
        if (getenv('REI69_TEST_LOCK_TIMEOUT') === '10') $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
        if (getenv('TEST_RR') === '1') { $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ'); if ($pdo->query('SELECT @@transaction_isolation')->fetchColumn() !== 'REPEATABLE-READ') throw new RuntimeException('Isolamento inesperado.'); }
        if (getenv('TEST_REPORT_CONNECTION') === '1') fwrite(STDERR, 'CONNECTION:' . $pdo->query('SELECT CONNECTION_ID()')->fetchColumn() . "\n");
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
    foreach (['processos_partes', 'partes', 'processos', 'setores', 'usuario_setor', 'sync_log', 'logs_sistema', 'classes', 'sala_audiencias_pauta', 'sala_audiencias_pauta_parte', 'processo_prioridades', 'gabinete_reu_preso', 'gabinete_reu_preso_processo'] as $table) {
        $out[$table] = ['schema' => $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM), 'rows' => $db->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC)];
    }
    return $out;
};
$invoke = static function (string $endpoint, string $user, string $profile, array $payload, string $method = 'POST', string $origin = 'http://example.invalid', bool $authenticated = true, bool $hasSession = true, bool $failSync = false) use ($lab, &$childUncertain, &$failureStage, &$failureDetail): array {
    $script = $lab . '/public/api/processos-partes/' . $endpoint;
    $code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = (string) getenv('TEST_METHOD');
$_SERVER['HTTP_HOST'] = 'example.invalid';
$_SERVER['HTTP_ORIGIN'] = (string) getenv('TEST_ORIGIN');
$_SESSION = getenv('TEST_SESSION') === '1' ? ['id_usuario' => (string) getenv('TEST_USER')] : [];
$payload = json_decode((string) getenv('TEST_PAYLOAD'), true);
$_POST = is_array($payload) ? $payload : [];
$_GET = is_array($payload) ? $payload : [];
register_shutdown_function(static function (): void { $code = http_response_code(); $last = error_get_last(); if (is_array($last) && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) { $file = basename((string) $last['file']); if (preg_match('/^[A-Za-z0-9_.-]{1,80}$/D', $file) === 1) fwrite(STDERR, 'PHPFATAL:' . $file . ':' . (int) $last['line'] . "\n"); } fwrite(STDERR, 'HTTP:' . ($code ?: 200)); });
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
    if (!is_resource($process)) { $failureDetail='spawn'; throw new RuntimeException('Falha ao iniciar PHP CLI para endpoint sintético.'); }
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
        $failureDetail='finish_timeout';
        throw new RuntimeException('Processo de teste excedeu limite.');
    }
    $exit = $status['exitcode'];
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($process);
    $statusText = substr($stderr, (int) strrpos($stderr, 'HTTP:') + 5);
    if ($statusText === '' || !ctype_digit($statusText)) { $failureDetail='exit'; throw new RuntimeException('Endpoint sem status HTTP controlado: ' . $endpoint . ' (' . $stderr . ')'); }
    if ($exit !== 0 && !in_array((int) $statusText, [400, 401, 403], true)) {
        $failureDetail = preg_match('/PHPFATAL:([A-Za-z0-9_.-]{1,80}):([0-9]+)/', $stderr, $fatalMatch) === 1 ? 'child_fatal_'.$fatalMatch[1].'_'.$fatalMatch[2] : (preg_match('/(?:PHP Fatal error|Uncaught (?:Error|TypeError|PDOException)|Parse error)/', $stderr) === 1 ? 'child_fatal' : 'child_http_'.$statusText);
        throw new RuntimeException('Endpoint CLI falhou fora de uma recusa HTTP controlada.');
    }
    try { $body = $stdout === '' ? [] : json_decode($stdout, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $exception) { $failureDetail='exit'; throw $exception; }
    return [(int) $statusText, is_array($body) ? $body : []];
};
$assert = static function (bool $condition, string $message) use (&$failureDetail): void {
    if (!$condition) { $failureDetail='assert'; throw new RuntimeException($message); }
};
try {
    $failureStage = 'negative';
    $payload = ['processo_id' => 'PRC-1', 'parte_id' => 'PAR-2', 'tipo_parte' => 'advogado', 'polo' => 'passivo'];
    $before = $snapshot($pdo);
    foreach ([['USR-2','PRC-1','negative_create_sector'], ['USR-3','PRC-1','negative_create_sector'], ['USR-1','PRC-3','negative_create_unassigned'], ['USR-4','PRC-4','negative_create_inactive']] as [$user,$process,$failureStage]) {
        [$status] = $invoke('create.php', $user, 'SERVIDOR-CARTORIO', array_replace($payload, ['processo_id'=>$process]));
        $assert($status === 403 && $snapshot($pdo) === $before, 'Negativa setorial de create falhou.');
    }
    $failureStage='negative_create_missing';
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
    $failureStage = 'negative_update';
    foreach (['update.php','delete.php'] as $endpoint) {
        $failureStage = $endpoint === 'update.php' ? 'negative_update' : 'negative_delete';
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
    // Two connections are coordinated by InnoDB wait edges, never by elapsed sleeps alone.
    $failureStage = 'concurrency';
    $concurrentActive = [];
    $spawn = static function (string $kind, ?string $linkId = null) use ($lab, &$concurrentActive, &$failureDetail): array {
        $failureDetail='spawn';
        $code = <<<'PHP'
if (in_array(getenv('TEST_KIND'), ['update','update2','race_update','race_delete'], true)) {
    $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['HTTP_HOST']='example.invalid'; $_SERVER['HTTP_ORIGIN']='http://example.invalid';
    $other=getenv('TEST_KIND')==='update2';
    $race=in_array(getenv('TEST_KIND'), ['race_update','race_delete'],true);
    $_SESSION=['id_usuario'=>$other ? 'USR-2' : 'USR-1']; $_POST=['id'=>$race ? (string)getenv('TEST_LINK') : ($other ? 'VNC-5' : 'VNC-1'),'tipo_parte'=>$race ? 'defensor_publico' : 'advogado'];
    register_shutdown_function(static function (): void { fwrite(STDERR, 'HTTP:' . (http_response_code() ?: 200) . "\n"); });
    require (string)getenv('TEST_ENDPOINT');
} elseif (in_array(getenv('TEST_KIND'), ['cnj_formatted','cnj_digits'], true)) {
    try {
        $dsn=(string)getenv('REI69DIRETOS_MYSQL_DSN');
        $marker=(string)getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER');
        if ($dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_diretos_test;charset=utf8mb4'
            || getenv('REI69DIRETOS_MYSQL_USER') !== 'root' || (string)getenv('REI69DIRETOS_MYSQL_PASSWORD') !== ''
            || preg_match('/^[a-f0-9]{64}$/D',$marker)!==1) throw new RuntimeException('Guard');
        $pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $actual=$pdo->query('SELECT marker FROM rei69_diretos_test_guard LIMIT 1')->fetchColumn();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei69_diretos_test' || !is_string($actual) || !hash_equals($marker,$actual)) throw new RuntimeException('Guard');
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
        fwrite(STDERR,'CONNECTION:'.$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()."\n");
        require '/repo/app/repositories/ProcessosRepository.php';
        $digits=(string)getenv('TEST_LINK');
        if (preg_match('/^[0-9]{20}$/D',$digits)!==1) throw new RuntimeException('Input');
        $numero=getenv('TEST_KIND')==='cnj_digits' ? $digits : substr($digits,0,7).'-'.substr($digits,7,2).'.'.substr($digits,9,4).'.'.substr($digits,13,1).'.'.substr($digits,14,2).'.'.substr($digits,16,4);
        $id=(new ProcessosRepository($pdo))->ensureProcess($numero, null, null, null);
        if ($id==='') throw new RuntimeException('ID');
        fwrite(STDOUT,"CNJ_COMMITTED\n");
    } catch (Throwable $e) {
        fwrite(STDERR,"CNJ_FAILURE code=actor\n");
        exit(1);
    }
} elseif (in_array(getenv('TEST_KIND'), ['pauta','pauta_mandado'], true)) {
    $stage='pauta_connect';
    register_shutdown_function(static function (): void {
        $last=error_get_last();
        if (is_array($last) && in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)) fwrite(STDERR,"PAUTA_FAILURE code=pauta_fatal\n");
    });
    try {
        $dsn=(string)getenv('REI69DIRETOS_MYSQL_DSN');
        $marker=(string)getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER');
        if ($dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_diretos_test;charset=utf8mb4'
            || getenv('REI69DIRETOS_MYSQL_USER') !== 'root' || (string)getenv('REI69DIRETOS_MYSQL_PASSWORD') !== ''
            || preg_match('/^[a-f0-9]{64}$/D',$marker)!==1) throw new RuntimeException('Conexão sintética inválida.');
        $pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $actual=$pdo->query('SELECT marker FROM rei69_diretos_test_guard LIMIT 1')->fetchColumn();
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei69_diretos_test' || !is_string($actual) || !hash_equals($marker,$actual)) throw new RuntimeException('Sentinela inválida.');
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
        $pdo->exec("SET SESSION time_zone = '+00:00'");
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
        fwrite(STDERR,'CONNECTION:'.$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()."\n");
        $stage='pauta_require';
        require '/repo/app/services/SalaAudienciasPautaService.php';
        $stage='pauta_construct';
        $service=new SalaAudienciasPautaService($pdo);
        $stage='pauta_runtime';
        $payload=['parte_id_global'=>'PAR-1','processo_parte_id'=>'VNC-1','nome_parte'=>'Pessoa Sintetica 1','tipo_parte'=>'vitima','maioridade'=>'maior','expedicao'=>'Não expedido','cumprimento'=>'pendente'];
        if (getenv('TEST_KIND') === 'pauta_mandado') $payload['expedicao']='mandado';
        $result=$service->updateParte('PP-A',$payload,'USR-1');
        if (($result['ok'] ?? false)!==true) { $stage='pauta_noop'; throw new RuntimeException('Pauta synthetic update failed.'); }
        fwrite(STDOUT,"PAUTA_COMMITTED\n");
    } catch (Throwable $e) {
        if ($e instanceof PDOException) {
            $state=strtoupper((string)$e->getCode());
            $known=['42S22','42S02','23000','40001','HY000'];
            $code=in_array($state,$known,true) ? 'pauta_sql_'.strtolower($state) : 'pauta_sql_other';
        } elseif (is_a($e,'PautaVinculoSetorNegado')) $code='pauta_acl';
        elseif ($e instanceof InvalidArgumentException) $code='pauta_invalid';
        elseif ($e instanceof Error) $code='pauta_error';
        else $code=$stage;
        fwrite(STDERR,'PAUTA_FAILURE code='.$code."\n");
        exit(1);
    }
} else {
    require (string)getenv('TEST_CONNECTION');
    $pdo=Conection::getInstance()->getConnection();
    $pdo->beginTransaction();
    $pdo->exec("UPDATE processos SET id_setor_responsavel='SET-GABINETE' WHERE id='PRC-1'");
    $pdo->commit();
    fwrite(STDOUT,"SECTOR_COMMITTED\n");
}
PHP;
        $environment = [
            'REI69DIRETOS_TEST_DISPOSABLE_MARKER'=>(string)getenv('REI69DIRETOS_TEST_DISPOSABLE_MARKER'),
            'REI69DIRETOS_MYSQL_DSN'=>(string)getenv('REI69DIRETOS_MYSQL_DSN'),
            'REI69DIRETOS_MYSQL_USER'=>(string)getenv('REI69DIRETOS_MYSQL_USER'),
            'REI69DIRETOS_MYSQL_PASSWORD'=>(string)getenv('REI69DIRETOS_MYSQL_PASSWORD'),
            'REI69_TEST_LOCK_TIMEOUT'=>'10', 'TEST_REPORT_CONNECTION'=>'1', 'TEST_AUTH'=>'1', 'TEST_SESSION'=>'1',
            'TEST_PROFILE'=>'SERVIDOR-CARTORIO', 'TEST_KIND'=>$kind,
            'TEST_ENDPOINT'=>$lab.'/public/api/processos-partes/'.($kind==='race_delete' ? 'delete.php' : 'update.php'), 'TEST_CONNECTION'=>$lab.'/app/helpers/Conection.php',
            'TEST_LINK'=>$linkId ?? '', 'TEST_RR'=>in_array($kind,['race_update','race_delete'],true) ? '1' : '0',
        ];
        $pipes=[];
        $proc=proc_open([PHP_BINARY,'-r',$code], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, $lab, $environment);
        if (!is_resource($proc)) throw new RuntimeException('Falha ao criar ator.');
        fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false);
        $index=count($concurrentActive); $concurrentActive[$index]=['proc'=>$proc,'out'=>$pipes[1],'err'=>$pipes[2]];
        return ['proc'=>$proc,'out'=>$pipes[1],'err'=>$pipes[2],'stdout'=>'','stderr'=>'','id'=>null,'index'=>$index];
    };
    $collect = static function (array &$worker): array {
        $worker['stdout'].=stream_get_contents($worker['out']); $worker['stderr'].=stream_get_contents($worker['err']);
        if (strlen($worker['stdout'])>1048576 || strlen($worker['stderr'])>1048576) throw new RuntimeException('Saída de ator excedeu limite.');
        if ($worker['id']===null && preg_match('/(?:^|\n)CONNECTION:([0-9]+)\n/', $worker['stderr'], $m)) $worker['id']=(int)$m[1];
        return proc_get_status($worker['proc']);
    };
    $verifiedChar30=false;
    $matchesPrimaryLock=static function (mixed $data, string $expected): bool {
        if (!is_string($data) || preg_match('/^[A-Z0-9-]{1,30}$/D',$expected)!==1 || strlen($data)<2
            || $data[0]!=="'" || substr($data,-1)!=="'") return false;
        $inner=substr($data,1,-1);
        if (strlen($inner)>30 || preg_match('/^[A-Z0-9-]+ *$/D',$inner)!==1) return false;
        return rtrim($inner,' ')===$expected;
    };
    $observe = static function (PDO $db, array &$worker, int $blocker, string $table, ?string $indexName=null, ?string $lockData=null) use ($collect, &$failureDetail, &$verifiedChar30, $matchesPrimaryLock): void {
        $failureDetail='wait_query';
        $deadline=microtime(true)+5.0;
        $sql="SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=? AND l.OBJECT_SCHEMA=DATABASE() AND l.OBJECT_NAME=?";
        $paramsSuffix=[];
        if ($indexName!==null || $lockData!==null) {
            if (!$verifiedChar30 || $table!=='processos_partes' || $indexName!=='PRIMARY' || $lockData===null) throw new RuntimeException('Chave de teste não validada.');
            $sql=str_replace('SELECT COUNT(*)','SELECT l.LOCK_DATA',$sql);
            $sql.=' AND l.INDEX_NAME=?'; $paramsSuffix=[$indexName];
        }
        $query=$db->prepare($sql);
        do {
            $state=$collect($worker);
            if (!$state['running']) {
                // A última saída pode ter chegado depois da leitura anterior e antes do status de término.
                $collect($worker);
                $failureDetail='early_exit';
                foreach ([401,403,404,500] as $status) if (str_contains($worker['stderr'], 'HTTP:' . $status . "\n")) { $failureDetail='early_' . $status; break; }
                foreach (['pauta_connect','pauta_require','pauta_construct','pauta_sql_42s22','pauta_sql_42s02','pauta_sql_23000','pauta_sql_40001','pauta_sql_hy000','pauta_sql_other','pauta_acl','pauta_invalid','pauta_runtime','pauta_noop','pauta_error','pauta_fatal'] as $code) if (str_contains($worker['stderr'], 'PAUTA_FAILURE code=' . $code . "\n")) { $failureDetail=$code; break; }
                throw new RuntimeException('Ator terminou antes da espera esperada.');
            }
            $failureDetail=$worker['id']===null ? 'connection_id' : 'wait_query';
            if ($worker['id']!==null) {
                $query->execute(array_merge([$worker['id'],$blocker,$table],$paramsSuffix));
                if ($indexName===null) { if ((int)$query->fetchColumn()>0) return; }
                else foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $data) if ($matchesPrimaryLock($data,$lockData)) return;
            }
            usleep(10000);
        } while (microtime(true)<$deadline);
        if ($worker['id']!==null) {
            $failureDetail='wait_timeout';
            if ($indexName!==null && $lockData!==null) {
                // Diagnose only fixed categories; never emit lock data or SQL.
                $failureDetail='wait_no_edge';
                $diagnostic=$db->prepare("SELECT l.OBJECT_SCHEMA,l.OBJECT_NAME,l.INDEX_NAME,l.LOCK_DATA FROM performance_schema.data_lock_waits w JOIN performance_schema.threads r ON r.THREAD_ID=w.REQUESTING_THREAD_ID JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE=w.ENGINE WHERE r.PROCESSLIST_ID=? AND b.PROCESSLIST_ID=?");
                $diagnostic->execute([$worker['id'],$blocker]);
                $edges=$diagnostic->fetchAll(PDO::FETCH_ASSOC);
                if ($edges!==[]) $failureDetail='wait_other_table';
                foreach ($edges as $edge) {
                    if (($edge['OBJECT_SCHEMA'] ?? null)!=='rei69_diretos_test' || ($edge['OBJECT_NAME'] ?? null)!==$table) continue;
                    $failureDetail='wait_other_index';
                    if (($edge['INDEX_NAME'] ?? null)!==$indexName) continue;
                    $actual=$edge['LOCK_DATA'] ?? null;
                    $failureDetail=$actual===null ? 'wait_key_null' : 'wait_key_other';
                    if (is_string($actual) && strlen($actual)>=2 && $actual[0]==="'" && substr($actual,-1)==="'"
                        && rtrim(substr($actual,1,-1),' ')===$lockData) $failureDetail='wait_key_padded';
                    break;
                }
            }
        }
        throw new RuntimeException('Espera InnoDB identificada não observada no prazo.');
    };
    $finish = static function (array &$worker) use ($collect, &$concurrentActive, &$failureDetail): array {
        $failureDetail='finish_timeout';
        $deadline=microtime(true)+8.0;
        do { $state=$collect($worker); if (!$state['running']) break; usleep(10000); } while (microtime(true)<$deadline);
        if ($state['running']) {
            proc_terminate($worker['proc'],9); $deadline=microtime(true)+2.0;
            do { usleep(10000); $state=$collect($worker); } while ($state['running'] && microtime(true)<$deadline);
            if (!$state['running']) { $concurrentActive[$worker['index']]=false; fclose($worker['out']); fclose($worker['err']); proc_close($worker['proc']); }
            throw new RuntimeException('Ator excedeu prazo.');
        }
        // Drain again after confirmed exit: output can arrive between the preceding read and proc_get_status().
        $worker['stdout'].=stream_get_contents($worker['out']);
        $worker['stderr'].=stream_get_contents($worker['err']);
        $concurrentActive[$worker['index']]=false; fclose($worker['out']); fclose($worker['err']); proc_close($worker['proc']);
        if (strlen($worker['stdout'])>1048576 || strlen($worker['stderr'])>1048576) throw new RuntimeException('Saída de ator excedeu limite.');
        if ($state['exitcode']!==0) {
            $failureDetail='exit';
            foreach (['pauta_connect','pauta_require','pauta_construct','pauta_sql_42s22','pauta_sql_42s02','pauta_sql_23000','pauta_sql_40001','pauta_sql_hy000','pauta_sql_other','pauta_acl','pauta_invalid','pauta_runtime','pauta_noop','pauta_error','pauta_fatal'] as $code) {
                if (str_contains($worker['stderr'], 'PAUTA_FAILURE code=' . $code . "\n")) { $failureDetail=$code; break; }
            }
            throw new RuntimeException('Ator terminou com erro.');
        }
        return [$worker['stdout'],$worker['stderr']];
    };
    $failureStage='c1_setup'; $failureDetail='general';
    $observer=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $parentId=(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $beforeConcurrent=$snapshot($pdo);
    $pdo->beginTransaction();
    $pdo->exec("UPDATE processos SET id_setor_responsavel='SET-GABINETE' WHERE id='PRC-1'");
    $failureStage='c1_wait'; $failureDetail='general';
    $worker=$spawn('update');
    $observe($observer,$worker,$parentId,'processos');
    $pdo->commit();
    $failureStage='c1_finish'; $failureDetail='general';
    [$out,$err]=$finish($worker);
    $failureStage='c1_check'; $failureDetail='general';
    $assert(str_contains($err,"HTTP:404\n") && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn()==='SET-GABINETE', 'Update não reavaliou setor após espera.');
    $afterDenied=$snapshot($pdo);
    foreach ($beforeConcurrent as $table => $stateBefore) if ($table !== 'processos') $assert($stateBefore===$afterDenied[$table], 'Update negado após espera produziu efeitos.');
    $expectedProcesses=$beforeConcurrent['processos'];
    foreach ($expectedProcesses['rows'] as &$row) if ($row['id']==='PRC-1') $row['id_setor_responsavel']='SET-GABINETE';
    unset($row);
    $assert($afterDenied['processos']===$expectedProcesses, 'Mudança de setor alterou outros campos de processo.');
    $assertUnchanged = static function (array $before, array $after, array $mayChange) use (&$failureDetail): void {
        foreach ($before as $table=>$state) {
            $detail=$table==='sala_audiencias_pauta' ? 'snapshot_pauta' : 'snapshot_other';
            if (!array_key_exists($table,$mayChange)) {
                if ($state!==$after[$table]) { $failureDetail=$detail; throw new RuntimeException('Mudança inesperada após concorrência.'); }
                continue;
            }
            if ($state['schema']!==$after[$table]['schema']) { $failureDetail=$detail; throw new RuntimeException('Schema mudou durante concorrência.'); }
            [$column,$ids]=$mayChange[$table];
            $keep=static fn(array $row): bool => !in_array((string)($row[$column] ?? ''),$ids,true);
            if (array_values(array_filter($state['rows'],$keep))!==array_values(array_filter($after[$table]['rows'],$keep))) { $failureDetail=$detail; throw new RuntimeException('Outra linha mudou após concorrência.'); }
        }
    };
    $pdo->exec("UPDATE processos SET id_setor_responsavel='SET-CARTORIO' WHERE id='PRC-1'");
    // Hold person so the endpoint owns process lock while waiting for its real profession update.
    $failureStage='c2_setup'; $failureDetail='general';
    $beforeInverse=$snapshot($pdo);
    $pdo->beginTransaction(); $pdo->query("SELECT id FROM partes WHERE id='PAR-1' FOR UPDATE")->fetchColumn();
    $failureStage='c2_person_wait'; $failureDetail='general';
    $updater=$spawn('update'); $observe($observer,$updater,$parentId,'partes');
    $failureStage='c2_sector_wait'; $failureDetail='general';
    $changer=$spawn('sector'); $observe($observer,$changer,(int)$updater['id'],'processos');
    $pdo->commit();
    $failureStage='c2_finish'; $failureDetail='general';
    [$updateOut,$updateErr]=$finish($updater); [$sectorOut,$sectorErr]=$finish($changer);
    $failureStage='c2_check'; $failureDetail='general';
    $assert(str_contains($updateErr,"HTTP:200\n") && str_contains($sectorOut,"SECTOR_COMMITTED\n"), 'Serialização inversa não completou.');
    $assert($pdo->query("SELECT tipo_parte FROM processos_partes WHERE id='VNC-1'")->fetchColumn()==='advogado' && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-1'")->fetchColumn()==='SET-GABINETE', 'Estado serializado final divergente.');
    $afterInverse=$snapshot($pdo); $assertUnchanged($beforeInverse,$afterInverse,['processos'=>['id',['PRC-1']],'processos_partes'=>['id',['VNC-1']],'partes'=>['id',['PAR-1']]]);
    // Direct update owns process then waits for person; Pauta must wait for that same process.
    $pdo->exec("UPDATE processos SET id_setor_responsavel='SET-CARTORIO' WHERE id='PRC-1'");
    $failureStage='c3_setup'; $failureDetail='general';
    $beforeDirectFirst=$snapshot($pdo);
    $pdo->beginTransaction(); $pdo->query("SELECT id FROM partes WHERE id='PAR-1' FOR UPDATE")->fetchColumn();
    $failureStage='c3_person_wait'; $failureDetail='general';
    $directFirst=$spawn('update'); $observe($observer,$directFirst,$parentId,'partes');
    $failureStage='c3_pauta_wait'; $failureDetail='general';
    $pautaSecond=$spawn('pauta'); $observe($observer,$pautaSecond,(int)$directFirst['id'],'processos');
    $pdo->commit();
    $failureStage='c3_finish'; $failureDetail='general';
    [$directOut,$directErr]=$finish($directFirst); [$pautaOut,$pautaErr]=$finish($pautaSecond);
    $failureStage='c3_check'; $failureDetail='general';
    $failureStage='c3_direct'; $failureDetail='general';
    $assert(str_contains($directErr,"HTTP:200\n"), 'Operação direta não confirmou sucesso.');
    $failureStage='c3_pauta'; $failureDetail='general';
    $assert(str_contains($pautaOut,"PAUTA_COMMITTED\n"), 'Pauta não confirmou sucesso.');
    $failureStage='c3_tipo'; $failureDetail='general';
    $assert($pdo->query("SELECT tipo_parte FROM processos_partes WHERE id='VNC-1'")->fetchColumn()==='vitima', 'Tipo final não corresponde à Pauta.');
    $failureStage='c3_snapshot'; $failureDetail='general';
    $afterDirectFirst=$snapshot($pdo);
    // One synthetic, active, pending person contributes zero minutes. The real
    // post-commit sync derives exactly these four fields from the fixed schedule.
    $pautaBefore=$beforeDirectFirst['sala_audiencias_pauta'];
    $pautaAfter=$afterDirectFirst['sala_audiencias_pauta'];
    $assert(count($pautaBefore['rows'])===1 && count($pautaAfter['rows'])===1
        && $pautaBefore['rows'][0]['id_pauta']==='PA' && $pautaAfter['rows'][0]['id_pauta']==='PA', 'Fixture exige uma pauta sintética conhecida.');
    $assert($pautaAfter['rows'][0]['data_hora_designacao']==='2030-01-01 10:00:00'
        && $pautaAfter['rows'][0]['hora_inicio']==='10:00:00'
        && $pautaAfter['rows'][0]['hora_fim']==='10:00:00'
        && (string)$pautaAfter['rows'][0]['duracao_estimada_minutos']==='0', 'Sincronização não produziu os quatro valores esperados.');
    $expected=$beforeDirectFirst;
    $expected['sala_audiencias_pauta']['rows'][0]['data_hora_designacao']='2030-01-01 10:00:00';
    $expected['sala_audiencias_pauta']['rows'][0]['hora_inicio']='10:00:00';
    $expected['sala_audiencias_pauta']['rows'][0]['hora_fim']='10:00:00';
    // Preserve the PDO integer/string representation after separately asserting zero.
    $expected['sala_audiencias_pauta']['rows'][0]['duracao_estimada_minutos']=$pautaAfter['rows'][0]['duracao_estimada_minutos'];
    $assertUnchanged($expected,$afterDirectFirst,['processos_partes'=>['id',['VNC-1']],'partes'=>['id',['PAR-1']],'sala_audiencias_pauta_parte'=>['id_parte',['PP-A']],'processo_prioridades'=>['processo_id',['PRC-1']]]);
    // Pauta owns process and pauta rows then waits for the link; direct update waits for process.
    $failureStage='c4_setup'; $failureDetail='general';
    $beforePautaFirst=$snapshot($pdo);
    $pdo->beginTransaction(); $pdo->query("SELECT id FROM processos_partes WHERE id='VNC-1' FOR UPDATE")->fetchColumn();
    $failureStage='c4_link_wait'; $failureDetail='general';
    $pautaFirst=$spawn('pauta_mandado'); $observe($observer,$pautaFirst,$parentId,'processos_partes');
    $failureStage='c4_direct_wait'; $failureDetail='general';
    $directSecond=$spawn('update'); $observe($observer,$directSecond,(int)$pautaFirst['id'],'processos');
    $pdo->commit();
    $failureStage='c4_finish'; $failureDetail='general';
    $failureStage='c4_pauta_finish'; $failureDetail='general';
    [$pautaOut,$pautaErr]=$finish($pautaFirst);
    $failureStage='c4_direct_finish'; $failureDetail='general';
    [$directOut,$directErr]=$finish($directSecond);
    $failureStage='c4_check'; $failureDetail='general';
    $assert(str_contains($pautaOut,"PAUTA_COMMITTED\n") && str_contains($directErr,"HTTP:200\n") && $pdo->query("SELECT tipo_parte FROM processos_partes WHERE id='VNC-1'")->fetchColumn()==='advogado', 'Ordem pauta seguida de direta falhou.');
    $afterPautaFirst=$snapshot($pdo);
    $expectedC4=$beforePautaFirst;
    $rowIndex=static function (array $rows, string $key, string $id): int {
        $found=[]; foreach ($rows as $i=>$row) if (($row[$key] ?? null)===$id) $found[]=$i;
        if (count($found)!==1) throw new RuntimeException('Linha sintética esperada ausente ou duplicada.');
        return $found[0];
    };
    $pautaRow=$rowIndex($expectedC4['sala_audiencias_pauta_parte']['rows'],'id_parte','PP-A');
    $assert($expectedC4['sala_audiencias_pauta_parte']['rows'][$pautaRow]['expedicao']==='Não expedido', 'Pré-condição de expedição anterior divergente.');
    $expectedC4['sala_audiencias_pauta_parte']['rows'][$pautaRow]['expedicao']='mandado';
    foreach (['processos_partes'=>['id','VNC-1'],'partes'=>['id','PAR-1']] as $table=>[$key,$id]) {
        $beforeIndex=$rowIndex($expectedC4[$table]['rows'],$key,$id);
        $afterIndex=$rowIndex($afterPautaFirst[$table]['rows'],$key,$id);
        $beforeTimestamp=$expectedC4[$table]['rows'][$beforeIndex]['updated_at'];
        $afterTimestamp=$afterPautaFirst[$table]['rows'][$afterIndex]['updated_at'];
        $assert(is_string($beforeTimestamp) && is_string($afterTimestamp) && $afterTimestamp >= $beforeTimestamp, 'Timestamp de atualização inválido.');
        $expectedC4[$table]['rows'][$beforeIndex]['updated_at']=$afterTimestamp;
        if ($table==='processos_partes') $expectedC4[$table]['rows'][$beforeIndex]['tipo_parte']='advogado';
    }
    $assertUnchanged($expectedC4,$afterPautaFirst,['processo_prioridades'=>['processo_id',['PRC-1']]]);
    // Different process locks with one shared person: both waits must be observed and terminate.
    $failureStage='c5_setup'; $failureDetail='general';
    $beforeShared=$snapshot($pdo);
    $pdo->beginTransaction(); $pdo->query("SELECT id FROM partes WHERE id='PAR-1' FOR UPDATE")->fetchColumn();
    $failureStage='c5_first_wait'; $failureDetail='general';
    $firstProcess=$spawn('update'); $observe($observer,$firstProcess,$parentId,'partes');
    $failureStage='c5_second_wait'; $failureDetail='general';
    $secondProcess=$spawn('update2'); $observe($observer,$secondProcess,$parentId,'partes');
    $pdo->commit();
    $failureStage='c5_finish'; $failureDetail='general';
    [$firstOut,$firstErr]=$finish($firstProcess); [$secondOut,$secondErr]=$finish($secondProcess);
    $failureStage='c5_check'; $failureDetail='general';
    $assert(str_contains($firstErr,"HTTP:200\n") && str_contains($secondErr,"HTTP:200\n") && (int)$pdo->query("SELECT COUNT(*) FROM processos_partes WHERE id IN ('VNC-1','VNC-5') AND tipo_parte='advogado'")->fetchColumn()===2, 'Operações em processos diferentes com pessoa compartilhada falharam.');
    $afterShared=$snapshot($pdo); $assertUnchanged($beforeShared,$afterShared,['processos_partes'=>['id',['VNC-1','VNC-5']],'partes'=>['id',['PAR-1']]]);
    // No instrumented source: A owns the link row, B performs its initial
    // consistent read and process lock, then waits on that exact PRIMARY record.
    $failureStage='c6_controls'; $failureDetail='general';
    $column=$pdo->query("SELECT DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='processos_partes' AND COLUMN_NAME='id'")->fetchAll(PDO::FETCH_ASSOC);
    $primary=$pdo->query("SELECT COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='processos_partes' AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($column)===1 && strtolower((string)$column[0]['DATA_TYPE'])==='char' && (int)$column[0]['CHARACTER_MAXIMUM_LENGTH']===30
        && strtolower((string)$column[0]['CHARACTER_SET_NAME'])==='utf8mb4' && strtolower((string)$column[0]['COLLATION_NAME'])==='utf8mb4_unicode_ci'
        && strtoupper((string)$column[0]['COLUMN_KEY'])==='PRI' && count($primary)===1 && $primary[0]['COLUMN_NAME']==='id'
        && (int)$primary[0]['SEQ_IN_INDEX']===1 && $primary[0]['SUB_PART']===null, 'Metadados da chave sintética divergentes.');
    $verifiedChar30=true;
    $assert($matchesPrimaryLock("'BR-U-MOVE                     '",'BR-U-MOVE')
        && $matchesPrimaryLock("'BR-U-MOVE'",'BR-U-MOVE')
        && !$matchesPrimaryLock("'BR-U-MOVEX                    '",'BR-U-MOVE')
        && !$matchesPrimaryLock("' BR-U-MOVE'",'BR-U-MOVE')
        && !$matchesPrimaryLock("'BR-U-MOVE X'",'BR-U-MOVE')
        && !$matchesPrimaryLock(null,'BR-U-MOVE'), 'Comparação da chave sintética inválida.');
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $assert($pdo->query('SELECT @@transaction_isolation')->fetchColumn()==='REPEATABLE-READ', 'Isolamento de coordenação inesperado.');
    $ids=['BR-CTRL-U','BR-CTRL-D','BR-U-MOVE','BR-U-DELETE','BR-D-MOVE','BR-D-DELETE'];
    $insert=$pdo->prepare("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES (?,'PRC-1','PAR-1','vitima','ativo',NOW(),NOW())");
    foreach ($ids as $id) $insert->execute([$id]);
    [$controlUpdate]=$invoke('update.php','USR-1','SERVIDOR-CARTORIO',['id'=>'BR-CTRL-U','tipo_parte'=>'advogado']);
    $query=$pdo->prepare('SELECT tipo_parte FROM processos_partes WHERE id=?'); $query->execute(['BR-CTRL-U']);
    $assert($controlUpdate===200 && $query->fetchColumn()==='advogado', 'Controle positivo de update falhou.');
    [$controlDelete,$controlBody]=$invoke('delete.php','USR-1','SERVIDOR-CARTORIO',['id'=>'BR-CTRL-D']);
    $query->execute(['BR-CTRL-D']);
    $assert($controlDelete===200 && ($controlBody['deleted'] ?? false)===true && $query->fetchColumn()===false, 'Controle positivo de delete falhou.');
    foreach ([['race_update','BR-U-MOVE','move'], ['race_update','BR-U-DELETE','delete'], ['race_delete','BR-D-MOVE','move'], ['race_delete','BR-D-DELETE','delete']] as [$kind,$link,$action]) {
        $failureStage='c6_prepare'; $failureDetail='general';
        $beforeRace=$snapshot($pdo);
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT processo_id FROM processos_partes WHERE id=? FOR UPDATE');
        $lock->execute([$link]);
        $assert($lock->fetchColumn()==='PRC-1', 'Alvo sintético não corresponde ao processo inicial.');
        $failureStage='c6_wait'; $failureDetail='general';
        $racer=$spawn($kind,$link);
        $observe($observer,$racer,$parentId,'processos_partes','PRIMARY',$link);
        // Expected data are constructed before COMMIT, never sampled during the race.
        $expectedRace=$beforeRace;
        $row=$rowIndex($expectedRace['processos_partes']['rows'],'id',$link);
        if ($action==='move') {
            $change=$pdo->prepare("UPDATE processos_partes SET processo_id='PRC-2' WHERE id=?");
            $change->execute([$link]);
            $assert($change->rowCount()===1, 'Movimentação adversária sintética falhou.');
            $expectedRace['processos_partes']['rows'][$row]['processo_id']='PRC-2';
        } else {
            $change=$pdo->prepare('DELETE FROM processos_partes WHERE id=?');
            $change->execute([$link]);
            $assert($change->rowCount()===1, 'Remoção adversária sintética falhou.');
            array_splice($expectedRace['processos_partes']['rows'],$row,1);
        }
        $pdo->commit();
        $failureStage='c6_finish'; $failureDetail='general';
        [$raceOut,$raceErr]=$finish($racer);
        $failureStage='c6_check'; $failureDetail='general';
        $result=json_decode($raceOut,true,512,JSON_THROW_ON_ERROR);
        $assert(str_contains($raceErr,"HTTP:404\n") && $result===['error'=>'Vínculo não encontrado.'], 'Endpoint não rejeitou vínculo alterado.');
        $assert($snapshot($pdo)===$expectedRace, 'Endpoint rejeitado gerou efeitos adicionais.');
    }
    // Diagnostic only: synthetic schema has no CNJ uniqueness. The BEFORE
    // INSERT trigger holds both real repository calls after their lookup.
    $failureStage='cnj_setup'; $failureDetail='general';
    require_once '/repo/app/helpers/CnjValidator.php';
    $uniqueNumberIndexes=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='processos' AND column_name='numero_processo' AND non_unique=0")->fetchColumn();
    $assert((int)$uniqueNumberIndexes===0, 'Fixture CNJ deve ser sem índice único por número.');
    $pdo->exec('CREATE TABLE rei69_cnj_gate (id INT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO rei69_cnj_gate (id) VALUES (1)');
    $pdo->exec('CREATE TRIGGER rei69_cnj_barrier BEFORE INSERT ON processos FOR EACH ROW BEGIN DECLARE gate_id INT; SELECT id INTO gate_id FROM rei69_cnj_gate WHERE id=1 FOR UPDATE; END');
    $base='0000004'.'20208050001';
    for ($i=0;$i<100;$i++) { $cnjDigits=substr($base,0,7).str_pad((string)$i,2,'0',STR_PAD_LEFT).substr($base,7); if (CnjValidator::isValid($cnjDigits)) break; }
    $assert(CnjValidator::isValid($cnjDigits), 'CNJ de concorrência inválido.');
    $cnjBefore=$snapshot($pdo);
    $pdo->beginTransaction();
    $pdo->query('SELECT id FROM rei69_cnj_gate WHERE id=1 FOR UPDATE')->fetchColumn();
    $cnjA=$spawn('cnj_digits',$cnjDigits); $cnjB=$spawn('cnj_formatted',$cnjDigits);
    $failureStage='cnj_wait_a'; $failureDetail='general';
    $observe($observer,$cnjA,$parentId,'rei69_cnj_gate');
    $failureStage='cnj_wait_b'; $failureDetail='general';
    $observe($observer,$cnjB,$parentId,'rei69_cnj_gate');
    $pdo->commit();
    $failureStage='cnj_finish'; $failureDetail='general';
    [$cnjOutA]=$finish($cnjA); [$cnjOutB]=$finish($cnjB);
    $assert($cnjOutA==="CNJ_COMMITTED\n" && $cnjOutB==="CNJ_COMMITTED\n", 'Atores CNJ não confirmaram inserções.');
    $failureStage='cnj_check'; $failureDetail='general';
    $cnjAfter=$snapshot($pdo);
    foreach ($cnjBefore as $table=>$before) if ($table!=='processos') $assert($cnjAfter[$table]===$before, 'Outra tabela foi alterada.');
    $q=$pdo->prepare("SELECT id,numero_processo FROM processos WHERE REGEXP_REPLACE(numero_processo,'[^0-9]','')=? ORDER BY id");
    $q->execute([$cnjDigits]); $cnjRows=$q->fetchAll(PDO::FETCH_ASSOC);
    $assert(count($cnjRows)===2 && $cnjRows[0]['id']!==$cnjRows[1]['id'] && $cnjRows[0]['numero_processo']===$cnjRows[1]['numero_processo'], 'Duplicidade CNJ não reproduzida.');
    $assert(count($cnjAfter['processos']['rows'])===count($cnjBefore['processos']['rows'])+2 && $cnjAfter['processos']['schema']===$cnjBefore['processos']['schema'], 'Efeitos de processo divergentes.');
    $oldIds=array_column($cnjBefore['processos']['rows'],'id');
    $preserved=array_values(array_filter($cnjAfter['processos']['rows'],static fn(array $row): bool => in_array($row['id'],$oldIds,true)));
    $assert($preserved===$cnjBefore['processos']['rows'], 'Processos anteriores foram alterados.');
    $pdo->exec('DROP TRIGGER rei69_cnj_barrier');
    // Real monitoring create uses this same isolated MySQL schema. Only atomicity
    // is under test here; sector policy and all concurrent writers remain open.
    $failureStage='monitor_setup'; $failureDetail='general';
    // Match the isolated processos_partes fixture collation for joins; this does not attest production schema.
    $pdo->exec("CREATE TABLE gabinete_monitoramento_eletronico (id VARCHAR(30) PRIMARY KEY,processo_id VARCHAR(30),numero_processo VARCHAR(30) NOT NULL,ano SMALLINT NOT NULL,numero_sequencial INT NOT NULL,processo_parte_id VARCHAR(30),reu_nome_exibicao VARCHAR(500) NOT NULL,reu_complemento VARCHAR(500),status VARCHAR(30) NOT NULL,data_inicio DATE,data_encerramento DATE,criado_por VARCHAR(30),atualizado_por VARCHAR(30),created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY (ano,numero_sequencial)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE gabinete_monitoramento_eletronico_subtarefas (id VARCHAR(30) PRIMARY KEY,monitoramento_id VARCHAR(30) NOT NULL,tipo VARCHAR(50) NOT NULL,ordem TINYINT NOT NULL,status VARCHAR(30) NOT NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY (monitoramento_id,ordem),FOREIGN KEY (monitoramento_id) REFERENCES gabinete_monitoramento_eletronico(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO processos (id,numero_processo,id_setor_responsavel,sigiloso) VALUES ('MON-P','0000001-69.2020.8.05.0001','SET-CARTORIO',0)");
    $pdo->exec("INSERT INTO partes (id,nome_completo,profissao,created_at,updated_at) VALUES ('MON-A','Pessoa Monitoramento A','Outra',NOW(),NOW()),('MON-B','Pessoa Monitoramento B','Outra',NOW(),NOW())");
    require_once '/repo/app/services/GabineteMonitoramentoEletronicoService.php';
    $monitorTables=['classes','processos','partes','processos_partes','gabinete_monitoramento_eletronico','gabinete_monitoramento_eletronico_subtarefas','setores','usuario_setor','sync_log','logs_sistema','sala_audiencias_pauta','sala_audiencias_pauta_parte','processo_prioridades','gabinete_reu_preso','gabinete_reu_preso_processo'];
    $monitorSnapshot=static function () use ($pdo,$monitorTables): array { $out=[]; foreach ($monitorTables as $table) $out[$table]=['ddl'=>$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM),'rows'=>$pdo->query('SELECT * FROM `'.$table.'` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC)]; return $out; };
    $monitor=new GabineteMonitoramentoEletronicoService($pdo);
    $failureStage='monitor_positive'; $failureDetail='general';
    $failureStage='monitor_create';
    try {
        $monitorId=$monitor->create(['numero_processo'=>'0000001-69.2020.8.05.0001','reu_id'=>'MON-A','usuario_cadastro_id'=>'USR-1']);
    } catch (PDOException $e) {
        $state=strtolower((string)$e->getCode());
        $failureDetail=in_array($state,['42s22','42s02','23000','40001','hy000'],true) ? 'monitor_sql_'.$state : 'monitor_sql_other';
        throw $e;
    } catch (InvalidArgumentException $e) { $failureDetail='monitor_invalid'; throw $e;
    } catch (LogicException $e) { $failureDetail='monitor_logic'; throw $e;
    } catch (RuntimeException $e) { $failureDetail='monitor_runtime'; throw $e;
    } catch (Throwable $e) { $failureDetail='monitor_error'; throw $e; }
    $failureStage='monitor_row_prepare'; $failureDetail='general';
    $q=$pdo->prepare('SELECT m.processo_id,m.criado_por,pp.parte_id,pp.processo_id AS link_processo FROM gabinete_monitoramento_eletronico m JOIN processos_partes pp ON pp.id=m.processo_parte_id WHERE m.id=?');
    $failureStage='monitor_row_execute';
    $q->execute([$monitorId]);
    $failureStage='monitor_row_fetch';
    $row=$q->fetch(PDO::FETCH_ASSOC);
    $assert($row===['processo_id'=>'MON-P','criado_por'=>'USR-1','parte_id'=>'MON-A','link_processo'=>'MON-P'], 'Controle positivo de monitoramento falhou.');
    $failureStage='monitor_tasks'; $failureDetail='general';
    $q=$pdo->prepare('SELECT COUNT(*) FROM gabinete_monitoramento_eletronico_subtarefas WHERE monitoramento_id=?'); $q->execute([$monitorId]);
    $assert((int)$q->fetchColumn()===2 && !$pdo->inTransaction(), 'Subtarefas ou encerramento do controle positivo falharam.');
    $failureStage='monitor_rollback'; $failureDetail='general';
    $beforeMonitor=$monitorSnapshot();
    $pdo->beginTransaction();
    try { $monitor->create(['numero_processo'=>'0000001-69.2020.8.05.0001','reu_id'=>'MON-B']); throw new RuntimeException('Transação externa aceita.'); }
    catch (LogicException $e) { $assert($e->getMessage()==='Criação de monitoramento não pode participar de uma transação externa.' && $pdo->inTransaction(), 'Erro ou estado de transação externa inesperado.'); }
    $pdo->rollBack(); $assert($monitorSnapshot()===$beforeMonitor, 'Recusa de transação externa gerou efeitos.');
    try { $monitor->create(['numero_processo'=>'0000001-69.2020.8.05.0001','processo_parte_id'=>'VNC-2']); throw new RuntimeException('Vínculo cruzado aceito.'); }
    catch (InvalidArgumentException $e) { $assert($e->getMessage()==='Réu selecionado não pertence ao processo informado.', 'Erro de vínculo cruzado inesperado.'); }
    $assert(!$pdo->inTransaction() && $monitorSnapshot()===$beforeMonitor, 'Vínculo cruzado deixou efeitos.');
    $pdo->exec("CREATE TRIGGER rei69_monitor_fail BEFORE INSERT ON gabinete_monitoramento_eletronico_subtarefas FOR EACH ROW BEGIN IF NEW.ordem=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic monitor failure'; END IF; END");
    try {
        $probe=$pdo->prepare("INSERT INTO gabinete_monitoramento_eletronico_subtarefas (id,monitoramento_id,tipo,ordem,status,created_at,updated_at) VALUES ('MON-PROBE',?,'teste',2,'pendente',NOW(),NOW())");
        $probe->execute([$monitorId]); throw new RuntimeException('Controle do trigger falhou.');
    } catch (PDOException $e) { $assert($e->getCode()==='45000' && str_contains($e->getMessage(),'synthetic monitor failure'), 'Trigger não recusou pelo motivo esperado.'); }
    $assert($monitorSnapshot()===$beforeMonitor, 'Controle do trigger deixou efeitos.');
    foreach ([
        ['numero_processo'=>'0000001-69.2020.8.05.0001','reu_id'=>'MON-B','classe_codigo'=>'MON-FILL','classe_descricao'=>'Classe preenchida sintetica','usuario_cadastro_id'=>'USR-1'],
        ['numero_processo'=>'0000002-00.2020.8.05.0001','reu_complemento'=>'Pessoa manual sintetica','classe_codigo'=>'MON-SYN','classe_descricao'=>'Classe sintetica','usuario_cadastro_id'=>'USR-1'],
    ] as $case) {
        if (isset($case['reu_complemento'])) {
            $base='0000002'.'20208050001';
            for ($i=0;$i<100;$i++) { $digits=substr($base,0,7).str_pad((string)$i,2,'0',STR_PAD_LEFT).substr($base,7); if (CnjValidator::isValid($digits)) break; }
            $assert(CnjValidator::isValid($digits), 'CNJ sintético inválido.'); $case['numero_processo']=$digits;
        }
        try { $monitor->create($case); throw new RuntimeException('Falha sintética não foi propagada.'); }
        catch (PDOException $e) { $assert($e->getCode()==='45000' && str_contains($e->getMessage(),'synthetic monitor failure'), 'Erro anterior ao trigger pretendido.'); }
        $assert(!$pdo->inTransaction() && $monitorSnapshot()===$beforeMonitor, 'Rollback de monitoramento deixou efeitos.');
    }
    $pdo->exec('DROP TRIGGER rei69_monitor_fail');
    fwrite(STDOUT, "PASS REI69 direct endpoints synthetic MySQL regression\n");
} finally {
    // Kill and confirm all live actors before releasing any database lock.
    foreach ($concurrentActive ?? [] as $index => $active) {
        if ($active === false) continue;
        $state=proc_get_status($active['proc']);
        if ($state['running']) {
            proc_terminate($active['proc'], 9);
            $deadline=microtime(true)+2.0;
            do { usleep(10000); $state=proc_get_status($active['proc']); } while ($state['running'] && microtime(true)<$deadline);
        }
        if ($state['running']) { $childUncertain=true; $failureStage='child_uncertain'; continue; }
        fclose($active['out']); fclose($active['err']); proc_close($active['proc']);
        $concurrentActive[$index]=false;
    }
    if (!$childUncertain && $pdo->inTransaction()) $pdo->rollBack();
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
