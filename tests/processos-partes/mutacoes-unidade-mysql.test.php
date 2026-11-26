<?php
declare(strict_types=1);

if (getenv('REI69_TEST_DISPOSABLE') !== '1') {
    throw new RuntimeException('Recusado: execute somente pelo runner MySQL descartável.');
}
if (!extension_loaded('pdo_mysql') || !function_exists('proc_open')) {
    throw new RuntimeException('Recusado: pdo_mysql e proc_open são necessários no executor isolado.');
}

$repoRoot = getenv('REI69_TEST_REPO_ROOT') ?: '/repo';
$appRoot = realpath($repoRoot . '/app');
$endpointRoot = realpath($repoRoot . '/public/api/processos-partes');
$responsibleEndpointRoot = realpath($repoRoot . '/public/api/processos');
$testPath = realpath(__FILE__);
if ($appRoot === false || $endpointRoot === false || $responsibleEndpointRoot === false || $testPath === false
    || is_file($repoRoot . '/.env') || str_contains($repoRoot, '.env')) {
    throw new RuntimeException('Recusado: raízes da fixture inválidas ou arquivo .env presente.');
}
require_once $repoRoot . '/scripts/migrate-process-responsible-sector.php';

$dsn = (string) getenv('REI69_MYSQL_DSN');
if (!str_contains($dsn, 'unix_socket=/var/run/mysqld/mysqld.sock')
    || preg_match('/(?:^|;)host\s*=/i', $dsn) === 1
    || preg_match('/(?:^|;)port\s*=/i', $dsn) === 1) {
    throw new RuntimeException('Recusado: use somente socket local do MySQL descartável.');
}
$expectedMarker = (string) getenv('REI69_TEST_MARKER');
if (preg_match('/^[a-f0-9]{64}$/D', $expectedMarker) !== 1) {
    throw new RuntimeException('Recusado: marcador sintético ausente ou inválido.');
}

$pdo = new PDO($dsn, (string) getenv('REI69_MYSQL_USER'), (string) getenv('REI69_MYSQL_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
$versionComment = (string) $pdo->query('SELECT @@version_comment')->fetchColumn();
if ($database !== 'rei69_test' || !str_starts_with($version, '8.4.') || !str_contains($versionComment, 'MySQL')) {
    throw new RuntimeException('Recusado: a fixture exige MySQL 8.4 no banco rei69_test.');
}
$tables = $pdo->query("SELECT table_name, engine FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_ASSOC);
$tables = array_map(static fn (array $row): array => array_change_key_case($row, CASE_LOWER), $tables);
if (count($tables) !== 1
    || strtolower((string) ($tables[0]['table_name'] ?? '')) !== 'rei69_test_guard'
    || strtolower((string) ($tables[0]['engine'] ?? '')) !== 'innodb') {
    throw new RuntimeException('Recusado: o schema deve conter somente a sentinela InnoDB do runner.');
}
$marker = $pdo->query('SELECT marker FROM rei69_test_guard LIMIT 1')->fetchColumn();
if (!is_string($marker) || !hash_equals($expectedMarker, $marker)) {
    throw new RuntimeException('Recusado: a sentinela não corresponde a esta execução.');
}

$fixtureTables = ['processos_partes', 'sync_log', 'logs_sistema', 'partes', 'processos', 'usuario_setor', 'setores'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $expectedMarker): void {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $remaining = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $marker = $pdo->query('SELECT marker FROM rei69_test_guard LIMIT 1')->fetchColumn();
        if ($remaining !== ['rei69_test_guard'] || !is_string($marker) || !hash_equals($expectedMarker, $marker)) {
            throw new RuntimeException('Schema temporário ou sentinela não passou na verificação de limpeza.');
        }
        fwrite(STDOUT, "CLEANUP schema sintético removido; sentinela do runner preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Falha ao remover/verificar schema sintético: ' . $exception->getMessage() . "\n");
        exit(1);
    }
});

$pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES'");
$ddl = [
    "CREATE TABLE setores (id_setor CHAR(30) NOT NULL PRIMARY KEY, ativo TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE usuario_setor (usuario CHAR(30) NOT NULL, setor CHAR(30) NOT NULL, PRIMARY KEY (usuario,setor), CONSTRAINT fk_rei69_user_sector FOREIGN KEY (setor) REFERENCES setores(id_setor)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos (id CHAR(30) NOT NULL PRIMARY KEY, numero_processo VARCHAR(64) NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE logs_sistema (id_log_sistema CHAR(30) NOT NULL PRIMARY KEY, usuario CHAR(30) NOT NULL, modulo VARCHAR(50) NOT NULL, acao VARCHAR(50) NOT NULL, tabela_afetada VARCHAR(50) NULL, registro_id CHAR(30) NULL, dados_anteriores TEXT NULL, dados_novos TEXT NULL, ip VARCHAR(45) NOT NULL, data_hora DATETIME NULL, KEY idx_registro (registro_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE partes (id CHAR(30) NOT NULL PRIMARY KEY, nome_completo VARCHAR(180) NULL, cpf VARCHAR(32) NULL, sexo VARCHAR(10) NULL, data_nascimento DATE NULL, profissao VARCHAR(255) NULL, created_at DATETIME NULL, updated_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE processos_partes (id CHAR(30) NOT NULL PRIMARY KEY, processo_id CHAR(30) NOT NULL, parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, polo VARCHAR(50) NOT NULL, prioridade_mulher TINYINT(1) NOT NULL DEFAULT 0, prioridade_crianca_adolescente TINYINT(1) NOT NULL DEFAULT 0, prioridade_idoso TINYINT(1) NOT NULL DEFAULT 0, prioridade_pessoa_deficiencia TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NULL, updated_at DATETIME NULL, KEY ix_rei69_link_process (processo_id), CONSTRAINT fk_rei69_link_process FOREIGN KEY (processo_id) REFERENCES processos(id), CONSTRAINT fk_rei69_link_part FOREIGN KEY (parte_id) REFERENCES partes(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE sync_log (parte_id CHAR(30) NOT NULL, tipo_parte VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($ddl as $sql) {
    $pdo->exec($sql);
}

ob_start();
$migration = new ProcessResponsibleSectorMigration($pdo);
$migration->migrate();
$firstMigrationOutput = (string) ob_get_clean();
ob_start();
$secondMigrationPlan = $migration->migrate();
$secondMigrationOutput = (string) ob_get_clean();
if ($firstMigrationOutput === '' || $secondMigrationOutput === ''
    || $secondMigrationPlan !== ['add_column' => false, 'add_index' => false, 'add_foreign_key' => false]
    || (int) $pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn() !== 1
    || (int) $pdo->query('SELECT COUNT(*) FROM processos WHERE id_setor_responsavel IS NOT NULL')->fetchColumn() !== 0) {
    throw new RuntimeException('Migração REI-69 não foi aditiva, idempotente ou deixou processo legado sem atribuição.');
}

$pdo->exec("INSERT INTO setores (id_setor,ativo) VALUES ('SET-CARTORIO',1),('SET-GABINETE',1),('SET-INATIVO',0)");
$pdo->exec("INSERT INTO usuario_setor (usuario,setor) VALUES ('USR-1','SET-CARTORIO'),('USR-2','SET-GABINETE'),('USR-4','SET-INATIVO')");
$pdo->exec("INSERT INTO processos (id,numero_processo,id_setor_responsavel) VALUES ('PRC-1','CNJ-SINTETICO-1','SET-CARTORIO'),('PRC-2','CNJ-SINTETICO-2','SET-GABINETE'),('PRC-3','CNJ-SINTETICO-3',NULL),('PRC-4','CNJ-SINTETICO-4','SET-INATIVO')");
$pdo->exec("INSERT INTO partes (id,nome_completo,cpf,sexo,data_nascimento,profissao,created_at,updated_at) VALUES ('PAR-1','Pessoa Sintética 1','00000000000','X','2000-01-01','Réu','2026-01-01 00:00:00','2026-01-01 00:00:00'),('PAR-2','Pessoa Sintética 2','00000000001','X','2000-01-01','Advogada anterior','2026-01-01 00:00:00','2026-01-01 00:00:00')");
$pdo->exec("INSERT INTO processos_partes (id,processo_id,parte_id,tipo_parte,polo,created_at,updated_at) VALUES ('VNC-1','PRC-1','PAR-1','reu','passivo',NOW(),NOW()),('VNC-2','PRC-2','PAR-2','vitima','ativo',NOW(),NOW()),('VNC-3','PRC-3','PAR-1','testemunha','ativo',NOW(),NOW())");

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
    'helpers/ProcessoSetorAuthorization.php', 'helpers/ProcessResponsibleSectorAdminPolicy.php', 'helpers/IdGenerator.php',
    'helpers/TextFormatter.php', 'helpers/ParteDocumentoHelper.php',
    'services/ProcessResponsibleSectorService.php',
    'access/AccessEvaluator.php', 'access/ProfileNormalizer.php', 'access/PageAccessRegistry.php',
    'access/AccessAreas.php', 'access/AccessPolicyVersion.php',
    'repositories/ProcessosPartesRepository.php', 'repositories/PartesDocumentosRepository.php',
];
foreach ($appFiles as $relative) {
    $copyRelative($appRoot, $relative, $lab, 'app/' . $relative);
}
$copyRelative($appRoot, 'repositories/PartesRepository.php', $lab, 'app/repositories/PartesRepositoryReal.php');
foreach (['create.php', 'update.php', 'delete.php', 'list.php'] as $endpoint) {
    $copyRelative($endpointRoot, $endpoint, $lab, 'public/api/processos-partes/' . $endpoint);
}
$copyRelative($responsibleEndpointRoot, 'setor-responsavel.php', $lab, 'public/api/processos/setor-responsavel.php');
require_once $lab . '/app/services/ProcessResponsibleSectorService.php';
file_put_contents($lab . '/app/helpers/AutoloadHelper.php', "<?php\ndeclare(strict_types=1);\n");
file_put_contents($lab . '/app/helpers/Conection.php', <<<'PHP'
<?php
declare(strict_types=1);
function appIsHttpsRequest(): bool { return false; }
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO {
        $pdo = new PDO((string) getenv('REI69_MYSQL_DSN'), (string) getenv('REI69_MYSQL_USER'), (string) getenv('REI69_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
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
$pdo->exec("INSERT INTO partes (id,nome_completo,cpf,sexo,data_nascimento,profissao,created_at,updated_at) VALUES ('PAR-REAL','Pessoa Repositório Real','00000000002','X','1990-01-01','Profissão anterior',NOW(),NOW())");
require_once $lab . '/app/repositories/PartesRepositoryReal.php';
$realPartesRepository = new PartesRepository($pdo);
$realPartesRepository->syncProfissaoFromTipoProcessoParte('PAR-REAL', 'advogado');
if ($pdo->query("SELECT profissao FROM partes WHERE id='PAR-REAL'")->fetchColumn() !== PartesRepository::PROFISSAO_ADVOGADO) {
    throw new RuntimeException('PartesRepository real não atualizou a profissão global de advogado.');
}
$realPartesRepository->syncProfissaoFromTipoProcessoParte('PAR-REAL', 'defensor_publico');
if ($pdo->query("SELECT profissao FROM partes WHERE id='PAR-REAL'")->fetchColumn() !== PartesRepository::PROFISSAO_DEFENSOR_PUBLICO) {
    throw new RuntimeException('PartesRepository real não atualizou a profissão global de defensor público.');
}
$realPartesRepository->syncProfissaoFromTipoProcessoParte('PAR-REAL', 'reu');
if ($pdo->query("SELECT profissao FROM partes WHERE id='PAR-REAL'")->fetchColumn() !== PartesRepository::PROFISSAO_DEFENSOR_PUBLICO) {
    throw new RuntimeException('PartesRepository real alterou profissão em tipo sem sincronização.');
}
file_put_contents($lab . '/app/repositories/PartesRepository.php', <<<'PHP'
<?php
declare(strict_types=1);
final class PartesRepository {
    private PDO $connection;
    public function __construct(PDO $connection) { $this->connection = $connection; }
    public function syncProfissaoFromTipoProcessoParte(string $parteId, string $tipoParte): void {
        $statement = $this->connection->prepare('INSERT INTO sync_log (parte_id,tipo_parte) VALUES (:parte_id,:tipo_parte)');
        $statement->execute([':parte_id' => $parteId, ':tipo_parte' => $tipoParte]);
        $profissao = match (strtolower($tipoParte)) {
            'advogado' => 'Advogado',
            'defensor' => 'Defensor Público',
            'defensor_publico' => 'Defensor Público',
            default => null,
        };
        if ($profissao !== null) {
            $update = $this->connection->prepare('UPDATE partes SET profissao = :profissao WHERE id = :parte_id');
            $update->execute([':profissao' => $profissao, ':parte_id' => $parteId]);
        }
        if (getenv('TEST_FAIL_SYNC') === '1') throw new RuntimeException('Falha sintética após sincronização.');
    }
}
PHP);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$responsibleSectorService = new ProcessResponsibleSectorService($pdo);
$assignment = $responsibleSectorService->assign('PRC-3', 'SET-CARTORIO', 'Atribuição administrativa sintética', 'ADMIN-1', '192.0.2.20');
$assert($assignment['changed'] === true
    && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-CARTORIO'
    && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3' AND acao='setor-responsavel'")->fetchColumn() === 1,
    'Atribuição ADMIN no MySQL não persistiu processo e trilha juntos.');
$beforeAuditCount = (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn();
try {
    $responsibleSectorService->assign('PRC-3', 'SET-INATIVO', 'Setor inativo precisa ser negado', 'ADMIN-1', '192.0.2.20');
    throw new RuntimeException('Atribuição a setor inativo foi aceita.');
} catch (InvalidArgumentException $expected) {
    $assert($pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-CARTORIO'
        && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn() === $beforeAuditCount,
        'Tentativa de atribuir setor inativo alterou processo ou trilha.');
}
$pdo->exec("CREATE TRIGGER reject_responsible_sector_audit BEFORE INSERT ON logs_sistema FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'");
try {
    $responsibleSectorService->assign('PRC-3', 'SET-GABINETE', 'Falha de auditoria sintética', 'ADMIN-1', '192.0.2.20');
    throw new RuntimeException('Atribuição sem trilha foi aceita.');
} catch (PDOException $expected) {
    $assert($pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-CARTORIO'
        && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn() === $beforeAuditCount,
        'Falha no insert da trilha não reverteu o setor responsável.');
}
$pdo->exec('DROP TRIGGER reject_responsible_sector_audit');
$snapshot = static function (PDO $db): array {
    $out = [];
    foreach (['processos_partes', 'sync_log', 'partes'] as $table) {
        $out[$table] = $db->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC);
    }
    return $out;
};
$invoke = static function (string $endpoint, string $user, string $profile, array $payload, string $method = 'POST', string $origin = 'http://example.invalid', bool $authenticated = true, bool $hasSession = true, bool $failSync = false) use ($lab): array {
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
        'REI69_MYSQL_DSN' => (string) getenv('REI69_MYSQL_DSN'),
        'REI69_MYSQL_USER' => (string) getenv('REI69_MYSQL_USER'),
        'REI69_MYSQL_PASSWORD' => (string) getenv('REI69_MYSQL_PASSWORD'),
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
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) throw new RuntimeException('Endpoint CLI falhou: ' . $endpoint . ' (' . $stderr . ')');
    $statusText = substr($stderr, (int) strrpos($stderr, 'HTTP:') + 5);
    if ($statusText === '' || !ctype_digit($statusText)) throw new RuntimeException('Endpoint sem status HTTP controlado: ' . $endpoint . ' (' . $stderr . ')');
    $body = $stdout === '' ? [] : json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    return [(int) $statusText, is_array($body) ? $body : []];
};
$invokeResponsibleSector = static function (string $user, string $profile, array $payload, string $method = 'POST', string $origin = 'http://example.invalid', bool $authenticated = true, bool $hasSession = true, bool $suppressAdmin = false) use ($lab): array {
    $script = $lab . '/public/api/processos/setor-responsavel.php';
    $code = <<<'PHP'
$_SERVER['REQUEST_METHOD'] = (string) getenv('TEST_METHOD');
$_SERVER['HTTP_HOST'] = 'example.invalid';
$_SERVER['HTTP_ORIGIN'] = (string) getenv('TEST_ORIGIN');
$_SERVER['REMOTE_ADDR'] = '192.0.2.30';
$_SESSION = getenv('TEST_SESSION') === '1' ? ['id_usuario' => (string) getenv('TEST_USER')] : [];
$_POST = json_decode((string) getenv('TEST_PAYLOAD'), true) ?: [];
register_shutdown_function(static function (): void { $code = http_response_code(); fwrite(STDERR, 'HTTP:' . ($code ?: 200)); });
require (string) getenv('TEST_ENDPOINT');
PHP;
    $environment = [
        'REI69_MYSQL_DSN' => (string) getenv('REI69_MYSQL_DSN'),
        'REI69_MYSQL_USER' => (string) getenv('REI69_MYSQL_USER'),
        'REI69_MYSQL_PASSWORD' => (string) getenv('REI69_MYSQL_PASSWORD'),
        'REI69_TEST_LOCK_TIMEOUT' => '1',
        'TEST_AUTH' => $authenticated ? '1' : '0', 'TEST_SESSION' => $hasSession ? '1' : '0',
        'TEST_USER' => $user, 'TEST_PROFILE' => $profile,
        'TEST_SUPPRESS_ADMIN' => $suppressAdmin ? '1' : '0',
        'TEST_METHOD' => $method, 'TEST_ORIGIN' => $origin,
        'TEST_PAYLOAD' => json_encode($payload, JSON_THROW_ON_ERROR), 'TEST_ENDPOINT' => $script,
    ];
    $pipes = [];
    $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $lab, $environment);
    if (!is_resource($process)) throw new RuntimeException('Falha ao iniciar endpoint de atribuição.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) throw new RuntimeException('Endpoint de atribuição falhou (' . $stderr . ')');
    $statusText = substr($stderr, (int) strrpos($stderr, 'HTTP:') + 5);
    if ($statusText === '' || !ctype_digit($statusText)) throw new RuntimeException('Endpoint de atribuição sem status HTTP.');
    $body = $stdout === '' ? [] : json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    return [(int) $statusText, is_array($body) ? $body : []];
};

try {
    $apiAssignmentPayload = ['processo_id' => 'PRC-3', 'setor_id' => 'SET-GABINETE', 'justificativa' => 'Revisão administrativa pela API'];
    [$apiAssignmentStatus, $apiAssignmentBody] = $invokeResponsibleSector('ADMIN-1', 'ADMIN', $apiAssignmentPayload);
    $assert($apiAssignmentStatus === 200 && ($apiAssignmentBody['success'] ?? false) === true
        && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-GABINETE',
        'A API não autorizou ADMIN efetivo na atribuição auditada.');
    $apiAuditCount = (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn();
    [$chiefAssignmentStatus] = $invokeResponsibleSector('CHIEF-1', 'CHEFIA', array_replace($apiAssignmentPayload, ['setor_id' => 'SET-CARTORIO']));
    $assert($chiefAssignmentStatus === 403
        && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-GABINETE'
        && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn() === $apiAuditCount,
        'CHEFIA sem ADMIN foi autorizada ou alterou dados.');
    [$suppressedAdminStatus] = $invokeResponsibleSector('ADMIN-1', 'ADMIN', array_replace($apiAssignmentPayload, ['setor_id' => 'SET-CARTORIO']), 'POST', 'http://example.invalid', true, true, true);
    $assert($suppressedAdminStatus === 403
        && $pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-GABINETE'
        && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn() === $apiAuditCount,
        'ADMIN suprimido pelo modo operacional foi autorizado ou alterou dados.');
    [$badOriginAssignmentStatus] = $invokeResponsibleSector('ADMIN-1', 'ADMIN', $apiAssignmentPayload, 'POST', 'http://hostile.invalid');
    [$badMethodAssignmentStatus] = $invokeResponsibleSector('ADMIN-1', 'ADMIN', $apiAssignmentPayload, 'GET');
    [$anonymousAssignmentStatus] = $invokeResponsibleSector('ADMIN-1', 'ADMIN', $apiAssignmentPayload, 'POST', 'http://example.invalid', false, false);
    $assert($badOriginAssignmentStatus === 403 && $badMethodAssignmentStatus === 403 && $anonymousAssignmentStatus === 401,
        'API de atribuição não protegeu origem, método e sessão.');
    $assert($pdo->query("SELECT id_setor_responsavel FROM processos WHERE id='PRC-3'")->fetchColumn() === 'SET-GABINETE'
        && (int) $pdo->query("SELECT COUNT(*) FROM logs_sistema WHERE registro_id='PRC-3'")->fetchColumn() === $apiAuditCount,
        'Requisição de atribuição rejeitada alterou processo ou trilha.');

    $before = $snapshot($pdo);
    $sharedPartProfession = $pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn();
    $createPayload = ['processo_id' => 'PRC-1', 'parte_id' => 'PAR-2', 'tipo_parte' => 'advogado', 'polo' => 'passivo'];
    [$failedCreateStatus] = $invoke('create.php', 'USR-1', 'SERVIDOR-CARTORIO', $createPayload, 'POST', 'http://example.invalid', true, true, true);
    $assert($failedCreateStatus === 500 && $snapshot($pdo) === $before, 'Falha na sincronização do create deixou vínculo ou profissão parcial.');
    [$status, $body] = $invoke('create.php', 'USR-1', 'SERVIDOR-CARTORIO', $createPayload);
    $assert($status === 201 && isset($body['id']), 'Mesmo setor com permissão não criou o vínculo.');
    $afterCreate = $snapshot($pdo);
    $assert(count($afterCreate['processos_partes']) === count($before['processos_partes']) + 1, 'Create autorizado não persistiu exatamente um vínculo.');
    $assert(count($afterCreate['sync_log']) === count($before['sync_log']) + 1, 'Create autorizado não sincronizou profissão global.');
    $assert($pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Advogado' && $sharedPartProfession !== 'Advogado', 'Create autorizado não atualizou profissão global da pessoa compartilhada.');

    [$readAllowedStatus, $readAllowedBody] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['processo_id' => 'PRC-1'], 'GET');
    [$readCrossSectorStatus, $readCrossSectorBody] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['processo_id' => 'PRC-2'], 'GET');
    [$readLegacyStatus, $readLegacyBody] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['processo_id' => 'PRC-3'], 'GET');
    [$readByPartStatus, $readByPartBody] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['parte_id' => 'PAR-2'], 'GET');
    $readAllowedIds = array_column($readAllowedBody['data'] ?? [], 'id');
    $assert($readAllowedStatus === 200 && count($readAllowedIds) === 2 && in_array('VNC-1', $readAllowedIds, true)
        && in_array((string) $body['id'], $readAllowedIds, true), 'Leitura autorizada no setor não retornou os vínculos esperados.');
    $assert($readCrossSectorStatus === 200 && ($readCrossSectorBody['data'] ?? null) === [], 'Leitura por processo expôs vínculo de outro setor.');
    $assert($readLegacyStatus === 200 && ($readLegacyBody['data'] ?? null) === [], 'Leitura expôs vínculo de processo legado sem setor.');
    $readByPartIds = array_column($readByPartBody['data'] ?? [], 'id');
    $assert($readByPartStatus === 200 && $readByPartIds === [(string) $body['id']], 'Leitura por parte não filtrou exatamente os vínculos fora do setor.');
    [$anonymousReadStatus] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['processo_id' => 'PRC-1'], 'GET', 'http://example.invalid', false, false);
    [$invalidReadMethodStatus] = $invoke('list.php', 'USR-1', 'SERVIDOR-CARTORIO', ['processo_id' => 'PRC-1'], 'POST');
    $assert($anonymousReadStatus === 401 && $invalidReadMethodStatus === 405, 'Leitura aceitou usuário anônimo ou método inválido.');

    foreach ([
        ['USR-2', 'PRC-1', 'setor diferente'],
        ['USR-3', 'PRC-1', 'usuário sem setor'],
        ['USR-1', 'PRC-3', 'processo sem setor responsável'],
        ['USR-4', 'PRC-4', 'setor inativo'],
    ] as [$user, $processId, $case]) {
        $payload = array_replace($createPayload, ['processo_id' => $processId]);
        [$deniedStatus] = $invoke('create.php', $user, 'SERVIDOR-CARTORIO', $payload);
        $assert($deniedStatus === 403, 'Create não negou: ' . $case);
        $assert($snapshot($pdo) === $afterCreate, 'Create negado gravou dados: ' . $case);
    }

    [$crossStatus, $crossBody] = $invoke('create.php', 'USR-2', 'SERVIDOR-CARTORIO', $createPayload);
    [$missingProcessStatus, $missingProcessBody] = $invoke('create.php', 'USR-2', 'SERVIDOR-CARTORIO', array_replace($createPayload, ['processo_id' => 'PRC-AUSENTE']));
    $assert($crossStatus === 403 && $crossStatus === $missingProcessStatus && $crossBody === $missingProcessBody, 'Create permite enumerar processos por resposta.');
    $assert($snapshot($pdo) === $afterCreate, 'Create cross-setor ou processo ausente alterou dados.');

    [$permissionStatus] = $invoke('update.php', 'USR-1', 'SERVIDOR-GABINETE', ['id' => 'VNC-1', 'tipo_parte' => 'vitima']);
    $assert($permissionStatus === 403, 'Update aceitou perfil sem permissão da operação.');
    $assert($snapshot($pdo) === $afterCreate, 'Update sem permissão da operação alterou dados.');

    [$unauthorizedUpdateStatus, $unauthorizedUpdateBody] = $invoke('update.php', 'USR-2', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1', 'tipo_parte' => 'vitima']);
    [$missingUpdateStatus, $missingUpdateBody] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-MISSING', 'tipo_parte' => 'vitima']);
    $assert($unauthorizedUpdateStatus === 404 && $unauthorizedUpdateStatus === $missingUpdateStatus && $unauthorizedUpdateBody === $missingUpdateBody, 'Update expõe existência de vínculo fora do setor.');
    [$authorizedNoopStatus] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => $body['id']]);
    $assert($authorizedNoopStatus === 200 && $snapshot($pdo) === $afterCreate, 'Update sem campos autorizados não preservou sucesso idempotente sem escrita.');
    [$unauthorizedNoopStatus, $unauthorizedNoopBody] = $invoke('update.php', 'USR-2', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1']);
    [$missingNoopStatus, $missingNoopBody] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-MISSING']);
    $assert($unauthorizedNoopStatus === 404 && $unauthorizedNoopStatus === $missingNoopStatus && $unauthorizedNoopBody === $missingNoopBody, 'Update vazio não aplicou autorização por objeto.');
    [$unauthorizedDeleteStatus, $unauthorizedDeleteBody] = $invoke('delete.php', 'USR-2', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1']);
    [$missingDeleteStatus, $missingDeleteBody] = $invoke('delete.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-MISSING']);
    $assert($unauthorizedDeleteStatus === 404 && $unauthorizedDeleteStatus === $missingDeleteStatus && $unauthorizedDeleteBody === $missingDeleteBody, 'Delete expõe existência de vínculo fora do setor.');
    $assert($snapshot($pdo) === $afterCreate, 'Update/delete negados alteraram dados.');

    foreach (['create.php', 'update.php', 'delete.php'] as $endpoint) {
        $payload = $endpoint === 'create.php' ? $createPayload : ['id' => 'VNC-1', 'tipo_parte' => 'vitima'];
        [$anonymousStatus] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $payload, 'POST', 'http://example.invalid', false, false);
        $assert($anonymousStatus === 401, $endpoint . ' aceitou usuário anônimo.');
        [$noSessionStatus] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $payload, 'POST', 'http://example.invalid', true, false);
        $assert($noSessionStatus === 401, $endpoint . ' aceitou requisição sem sessão.');
        foreach ([['GET', 'http://example.invalid'], ['POST', 'http://evil.invalid']] as [$method, $origin]) {
            [$badStatus] = $invoke($endpoint, 'USR-1', 'SERVIDOR-CARTORIO', $payload, $method, $origin);
            $assert($badStatus === 403, $endpoint . ' aceitou método/origem inválidos.');
        }
        $assert($snapshot($pdo) === $afterCreate, $endpoint . ' inválido alterou dados.');
    }

    // Testa que update bloqueia o processo antes de autorizar e que delete bloqueia o vínculo após o processo.
    $pdo->exec('SET GLOBAL innodb_lock_wait_timeout = 1');
    $holder = new PDO($dsn, (string) getenv('REI69_MYSQL_USER'), (string) getenv('REI69_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $holder->beginTransaction();
    $holder->query("SELECT id FROM processos WHERE id='PRC-1' FOR UPDATE")->fetchColumn();
    [$lockedUpdateStatus] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1', 'tipo_parte' => 'vitima']);
    $assert($lockedUpdateStatus === 500, 'Update não aguardou lock InnoDB do processo.');
    $holder->rollBack();
    $assert($snapshot($pdo) === $afterCreate, 'Update durante lock deixou alteração parcial.');

    $holder->beginTransaction();
    $holder->query("SELECT id FROM processos_partes WHERE id='VNC-1' FOR UPDATE")->fetchColumn();
    [$lockedDeleteStatus] = $invoke('delete.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1']);
    $assert($lockedDeleteStatus === 500, 'Delete não aguardou lock InnoDB do vínculo.');
    $holder->rollBack();
    $assert($snapshot($pdo) === $afterCreate, 'Delete durante lock deixou alteração parcial.');

    $beforeFailedUpdate = $snapshot($pdo);
    [$failedUpdateStatus] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => $body['id'], 'tipo_parte' => 'defensor_publico'], 'POST', 'http://example.invalid', true, true, true);
    $assert($failedUpdateStatus === 500 && $snapshot($pdo) === $beforeFailedUpdate, 'Falha na sincronização do update deixou vínculo ou profissão parcial.');
    [$updateStatus] = $invoke('update.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => $body['id'], 'tipo_parte' => 'defensor_publico']);
    $assert($updateStatus === 200 && $pdo->query('SELECT tipo_parte FROM processos_partes WHERE id=' . $pdo->quote($body['id']))->fetchColumn() === 'defensor_publico', 'Update autorizado no mesmo setor não persistiu.');
    $assert($pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Defensor Público' && count($snapshot($pdo)['sync_log']) === count($afterCreate['sync_log']) + 1, 'Update autorizado não sincronizou profissão global.');
    $beforeCreatedDelete = $snapshot($pdo);
    [$createdDeleteStatus] = $invoke('delete.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => $body['id']]);
    $assert($createdDeleteStatus === 200 && $pdo->query("SELECT profissao FROM partes WHERE id='PAR-2'")->fetchColumn() === 'Defensor Público'
        && count($snapshot($pdo)['sync_log']) === count($beforeCreatedDelete['sync_log']), 'Delete de vínculo recalculou ou limpou profissão global.');
    [$deleteStatus, $deleteBody] = $invoke('delete.php', 'USR-1', 'SERVIDOR-CARTORIO', ['id' => 'VNC-1']);
    $assert($deleteStatus === 200 && ($deleteBody['deleted'] ?? false) === true, 'Delete autorizado no mesmo setor não persistiu.');

    fwrite(STDOUT, "PASS MySQL/InnoDB: migração real idempotente sem backfill e com FK checks habilitados; leitura e mutação protegidas por setor; sincronização global de profissão no mesmo commit; rollback conjunto após falha; negações sem gravação; respostas sem enumeração e locks em update/delete. Sessão/perfis sintéticos via PHP CLI; sem navegador ou autenticação real.\n");
} finally {
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
