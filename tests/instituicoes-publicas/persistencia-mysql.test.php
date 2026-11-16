<?php
declare(strict_types=1);

// Somente no runner descartável inspecionado; nunca apaga tabelas preexistentes.
$dsn = (string) getenv('MYSQL_TEST_DSN');
$marker = (string) getenv('MYSQL_TEST_MARKER');
if ($dsn !== 'mysql:host=rei70-mysql;port=3306;dbname=instituicoes_test;charset=utf8mb4'
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)) {
    throw new RuntimeException('Ambiente descartável não identificado.');
}
$pdo = new PDO($dsn, (string) getenv('MYSQL_TEST_USER'), (string) getenv('MYSQL_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$proof = $pdo->query('SELECT token FROM `_sandbox_proof`')->fetchAll(PDO::FETCH_COLUMN);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
if (count($proof) !== 1 || !hash_equals($marker, (string) $proof[0]) || $tables !== ['_sandbox_proof']) {
    throw new RuntimeException('Marcador ou banco vazio não conferem.');
}

// A cópia descartável impede sobrescrever configuração ou helpers do checkout.
$sourceRoot = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/instituicoes-' . bin2hex(random_bytes(16));
if (!mkdir($root, 0700)) throw new RuntimeException('Não foi possível criar diretório temporário.');
$fixtureTables = ['instituicoes_telefones', 'instituicoes_setores', 'instituicoes_publicas'];
register_shutdown_function(static function () use ($pdo, $fixtureTables, $root): void {
    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($fixtureTables as $table) $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $tempRoot = realpath(sys_get_temp_dir());
        $fixtureRoot = realpath($root);
        if ($tempRoot === false || $fixtureRoot === false
            || dirname($fixtureRoot) !== $tempRoot
            || !str_starts_with(basename($fixtureRoot), 'instituicoes-')
            || is_link($fixtureRoot)) {
            throw new RuntimeException('Recusada limpeza fora do diretório temporário próprio.');
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) {
                if (!unlink($item->getPathname())) throw new RuntimeException('Falha ao remover arquivo temporário.');
            } elseif ($item->isDir() && !rmdir($item->getPathname())) {
                throw new RuntimeException('Falha ao remover diretório temporário.');
            }
        }
        if (!rmdir($fixtureRoot)) throw new RuntimeException('Falha ao remover raiz temporária.');
        fwrite(STDOUT, "CLEANUP schema e arquivos sintéticos removidos; sentinela preservada.\n");
    } catch (Throwable $exception) {
        fwrite(STDERR, "Falha na limpeza sintética: " . $exception->getMessage() . "\n");
        exit(1);
    }
});
$files = [
    'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php',
    'app/helpers/IdGenerator.php', 'app/helpers/TextFormatter.php', 'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php',
    'app/access/AccessPolicyVersion.php', 'app/helpers/InstituicoesConsultaPolicy.php', 'app/services/InstituicoesPublicasService.php',
    'app/repositories/InstituicoesPublicasRepository.php', 'app/repositories/InstituicoesSetoresRepository.php',
    'app/repositories/InstituicoesTelefonesRepository.php',
];
foreach (['create.php', 'update.php', 'get.php', 'list.php', 'search.php', 'setores.php', 'setores/create.php', 'setores/update.php', 'setores/delete.php',
    'telefones/create.php', 'telefones/update.php', 'telefones/delete.php'] as $file) {
    $files[] = 'public/api/instituicoes-publicas/' . $file;
}
foreach ($files as $file) {
    $target = $root . '/' . $file;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) throw new RuntimeException('Diretório de teste indisponível.');
    if (!copy($sourceRoot . '/' . $file, $target)) throw new RuntimeException('Falha ao copiar arquivo de teste.');
}
mkdir($root . '/config', 0700);
file_put_contents($root . '/config/security.php', <<<'PHP'
<?php final class Security {
    public static function isAuthenticated(): bool { return getenv('TEST_AUTH') === '1'; }
    public static function validarCNPJ($v): bool { return true; }
}
PHP);
file_put_contents($root . '/app/helpers/UsuarioPerfisAutorizacao.php', <<<'PHP'
<?php final class UsuarioPerfisAutorizacao {
    public static function perfisDoUsuario(string $id): array { return [getenv('TEST_PROFILE')]; }
}
PHP);
file_put_contents($root . '/app/helpers/Conection.php', <<<'PHP'
<?php function appIsHttpsRequest(): bool { return false; }
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO { return new PDO(getenv('MYSQL_TEST_DSN'), getenv('MYSQL_TEST_USER'), getenv('MYSQL_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
}
PHP);
file_put_contents($root . '/app/helpers/AutoloadHelper.php', '<?php');

foreach ([
    'CREATE TABLE instituicoes_publicas (id VARCHAR(64) PRIMARY KEY, nome VARCHAR(255), nome_fantasia VARCHAR(120), cnpj VARCHAR(20), observacoes TEXT, ativo INT, created_at DATETIME, updated_at DATETIME)',
    'CREATE TABLE instituicoes_setores (id VARCHAR(64) PRIMARY KEY, instituicao_id VARCHAR(64), nome_setor VARCHAR(120), sigla VARCHAR(120), ativo INT, created_at DATETIME, updated_at DATETIME)',
    'CREATE TABLE instituicoes_telefones (id VARCHAR(64) PRIMARY KEY, instituicao_id VARCHAR(64), tipo VARCHAR(80), numero VARCHAR(80), ramal VARCHAR(80), principal INT, ativo INT, created_at DATETIME, updated_at DATETIME)',
] as $sql) $pdo->exec($sql . ' ENGINE=InnoDB');
$pdo->exec("INSERT INTO instituicoes_publicas VALUES ('INS-1','Instituicao Inicial',NULL,NULL,NULL,1,NOW(),NOW())");
$pdo->exec("INSERT INTO instituicoes_publicas VALUES ('INS-2','Outra Instituicao',NULL,NULL,NULL,1,NOW(),NOW())");
$pdo->exec("INSERT INTO instituicoes_setores VALUES ('SET-1','INS-1','Setor Inicial',NULL,1,NOW(),NOW())");
$pdo->exec("INSERT INTO instituicoes_telefones VALUES ('TEL-1','INS-1',NULL,'00000000',NULL,0,1,NOW(),NOW())");
$endpoints = [
    'create.php' => ['nome' => 'Instituicao Teste'],
    'update.php' => ['id' => 'INS-1', 'nome' => 'Instituicao Alterada'],
    'setores/create.php' => ['instituicao_id' => 'INS-1', 'nome_setor' => 'Setor Teste'],
    'setores/update.php' => ['id' => 'SET-1', 'nome_setor' => 'Setor Alterado'],
    'setores/delete.php' => ['id' => 'SET-1'],
    'telefones/create.php' => ['instituicao_id' => 'INS-1', 'numero' => '12345678'],
    'telefones/update.php' => ['id' => 'TEL-1', 'numero' => '87654321'],
    'telefones/delete.php' => ['id' => 'TEL-1'],
];
$snapshot = static function () use ($pdo): array {
    $out = [];
    foreach (['instituicoes_publicas', 'instituicoes_setores', 'instituicoes_telefones'] as $table) {
        $out[$table] = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }
    return $out;
};
$invoke = static function (string $endpoint, array $payload, string $profile, bool $auth, string $origin, string $method) use ($root): array {
    putenv('TEST_PROFILE=' . $profile);
    putenv('TEST_AUTH=' . ($auth ? '1' : '0'));
    putenv('TEST_PAYLOAD=' . json_encode($payload));
    putenv('TEST_ORIGIN=' . $origin);
    putenv('TEST_METHOD=' . $method);
    putenv('TEST_ENDPOINT=' . $root . '/public/api/instituicoes-publicas/' . $endpoint);
    $code = <<<'PHP'
$_SERVER['REQUEST_METHOD']=getenv('TEST_METHOD'); $_SERVER['HTTP_HOST']='example.invalid';
$_SERVER['HTTP_ORIGIN']=getenv('TEST_ORIGIN'); $_SESSION=['id_usuario'=>'synthetic'];
$_POST=json_decode(getenv('TEST_PAYLOAD'),true);
$_GET=$_POST;
register_shutdown_function(function(){ $status=http_response_code(); fwrite(STDERR, 'HTTP:'.($status === false ? 200 : $status)); });
require getenv('TEST_ENDPOINT');
PHP;
    $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Não iniciou processo de teste.');
    fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]); $exit = proc_close($process);
    if ($exit !== 0 || !preg_match('/HTTP:(\d+)/', $stderr, $match) || preg_match('/(?:Fatal error|Parse error|Uncaught (?:Error|Exception))/', $stderr)) {
        throw new RuntimeException("Falha sintética $endpoint (exit=$exit): $stderr");
    }
    $body = json_decode($stdout, true);
    if (!is_array($body)) throw new RuntimeException("Resposta inválida $endpoint: " . substr($stdout, 0, 500));
    return [(int) $match[1], $body];
};
$reads = ['get.php' => ['id' => 'INS-2'], 'list.php' => [], 'search.php' => ['nome' => 'Instituicao'], 'setores.php' => ['instituicao_id' => 'INS-1']];
foreach ($reads as $endpoint => $payload) {
    foreach ([['ESTUDANTE', true, 403], ['CONSULTOR', false, 401], ['CONSULTOR', true, 200], ['SERVIDOR-GABINETE', true, 200]] as [$profile, $auth, $expected]) {
        $before = $snapshot(); [$status] = $invoke($endpoint, $payload, $profile, $auth, '', 'GET');
        if ($status !== $expected || $snapshot() !== $before) throw new RuntimeException("Leitura falhou: $endpoint/$profile ($status).");
    }
    [$status] = $invoke($endpoint, $payload, 'SERVIDOR-CARTORIO', true, '', 'POST');
    if ($status !== 405) throw new RuntimeException("Método de leitura não foi recusado: $endpoint ($status).");
}
foreach ($endpoints as $endpoint => $payload) {
    foreach ([
        ['SERVIDOR-CARTORIO', false, 'http://example.invalid', 'POST', 401],
        ['CONSULTOR', true, 'http://example.invalid', 'POST', 403],
        ['SERVIDOR-GABINETE', true, 'http://example.invalid', 'POST', 403],
        ['SERVIDOR-CARTORIO', true, 'http://evil.invalid', 'POST', 403],
        ['SERVIDOR-CARTORIO', true, 'http://example.invalid', 'GET', 403],
    ] as [$profile, $auth, $origin, $method, $expected]) {
        $before = $snapshot(); [$status] = $invoke($endpoint, $payload, $profile, $auth, $origin, $method);
        if ($status !== $expected || $snapshot() !== $before) throw new RuntimeException("Recusa falhou: $endpoint/$profile/$method ($status).");
    }
}
foreach ($endpoints as $endpoint => $payload) {
    $before = $snapshot(); [$status] = $invoke($endpoint, $payload, 'SERVIDOR-CARTORIO', true, 'http://example.invalid', 'POST');
    if (!in_array($status, [200, 201], true) || $snapshot() === $before) throw new RuntimeException("Escrita autorizada falhou: $endpoint ($status).");
}
echo "PASS MySQL: 20 leituras, 40 recusas sem escrita e 8 gravações autorizadas (identidade simulada)\n";
