<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
if (PHP_SAPI !== 'cli' || is_file($root . '/.env')) {
    throw new RuntimeException('Execute somente em checkout isolado, sem .env.');
}
if (isset($argv[1])) {
    foreach (['APP_ENV' => 'fixture', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1',
        'DB_NAME' => 'fixture_sqlite', 'DB_USER' => 'fixture', 'DB_PASS' => 'fixture'] as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
    require_once $root . '/app/helpers/BootstrapHelper.php';
    require_once $root . '/config/security.php';
    $db = new PDO('sqlite::memory:');
    $db->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
    $db->exec('CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER)');
    $db->exec('CREATE TABLE sala_audiencias_tipos (id_tipo TEXT, codigo TEXT, nome TEXT, ordem INTEGER, ativo INTEGER)');
    $db->exec("INSERT INTO sala_audiencias_tipos VALUES ('fixture-1', 'INSTR', 'Instrução', 1, 1), ('fixture-2', 'OLD', 'Inativo', 2, 0)");
    $token = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO usuarios VALUES (?, ?, ?, 1)')->execute([
        'fixture-user', Security::hashAccessToken($token), date('Y-m-d H:i:s', time() + 600),
    ]);
    $reflect = new ReflectionClass(Conection::class);
    $connection = $reflect->newInstanceWithoutConstructor();
    $reflect->getProperty('pdo')->setValue($connection, $db);
    $reflect->getProperty('instance')->setValue(null, $connection);
    $_SESSION = $argv[1] === 'autenticado'
        ? ['id_usuario' => 'fixture-user', 'token' => $token, 'last_activity_at' => time()]
        : [];
    register_shutdown_function(static function (): void {
        fwrite(STDERR, 'status=' . (http_response_code() ?: 200));
    });
    require $root . '/public/api/sala-audiencias-pauta/tipos-list.php';
    exit;
}

foreach (['anonimo' => 401, 'autenticado' => 200] as $scenario => $expected) {
    $proc = proc_open([PHP_BINARY, __FILE__, $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Não foi possível iniciar fixture PHP.');
    }
    $body = stream_get_contents($pipes[1]);
    $status = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if ($exit !== 0 || $status !== 'status=' . $expected
        || ($expected === 401 && (!isset($data['error']) || isset($data['data'])))
        || ($expected === 200 && array_column($data['data'] ?? [], 'codigo') !== ['INSTR'])) {
        throw new RuntimeException("Resposta inesperada na fixture: " . $scenario);
    }
}
echo "PASS REI-339: sessão ausente recusada e catálogo autenticado preservado\n";
