<?php
declare(strict_types=1);

require __DIR__ . '/../../app/services/InstituicoesPublicasService.php';

function rei149Connection(): PDO
{
    $host = getenv('REI149_DB_HOST') ?: 'mysql';
    $name = getenv('REI149_DB_NAME') ?: 'rei149_test';
    $user = getenv('REI149_DB_USER') ?: 'root';
    $password = getenv('REI149_DB_PASSWORD') ?: '';
    $expectedHostname = getenv('REI149_EXPECTED_DB_HOSTNAME') ?: '';
    if ($host !== 'mysql' || $name !== 'rei149_test' || !preg_match('/^[a-f0-9]{12,64}$/', $expectedHostname)) {
        throw new RuntimeException('Fixture exige identidade injetada pelo runner MySQL isolado.');
    }
    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
    $identity = $pdo->query('SELECT @@hostname AS hostname, DATABASE() AS database_name')->fetch(PDO::FETCH_ASSOC);
    if (($identity['hostname'] ?? null) !== $expectedHostname || ($identity['database_name'] ?? null) !== $name) {
        throw new RuntimeException('Conexão não corresponde à instância MySQL descartável esperada.');
    }
    return $pdo;
}

function rei149Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei149Marker(string $token, string $name): string
{
    if (!preg_match('/^[a-f0-9]{16}$/', $token) || !in_array($name, ['ready', 'attempting', 'done', 'release'], true)) {
        throw new InvalidArgumentException('Parâmetro de coordenação inválido.');
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rei149-' . $token . '-' . $name;
}

function rei149WaitForMarker(string $path, int $timeoutMs): bool
{
    $deadline = microtime(true) + ($timeoutMs / 1000);
    do {
        if (is_file($path)) {
            return true;
        }
        usleep(20_000);
    } while (microtime(true) < $deadline);
    return false;
}

if (($argv[1] ?? '') === 'lock-worker') {
    $token = (string) ($argv[2] ?? '');
    $pdo = rei149Connection();
    $pdo->beginTransaction();
    try {
        $service = new InstituicoesPublicasService($pdo);
        rei149Assert($service->update('INS-1', []), 'Payload sem campos não confirmou a instituição bloqueada.');
        file_put_contents(rei149Marker($token, 'ready'), 'locked');
        rei149Assert(rei149WaitForMarker(rei149Marker($token, 'release'), 10_000), 'Timeout aguardando liberação do teste concorrente.');
        rei149Assert(file_get_contents(rei149Marker($token, 'release')) === 'release', 'O teste cancelou a espera de lock.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    exit(0);
}

if (($argv[1] ?? '') === 'replace-worker') {
    $token = (string) ($argv[2] ?? '');
    rei149Assert(rei149WaitForMarker(rei149Marker($token, 'ready'), 10_000), 'Timeout aguardando a leitura bloqueante.');
    $pdo = rei149Connection();
    $pdo->beginTransaction();
    try {
        $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        file_put_contents(rei149Marker($token, 'attempting'), (string) $connectionId);
        $pdo->exec("DELETE FROM instituicoes_publicas WHERE id = 'INS-1'");
        $statement = $pdo->prepare(
            'INSERT INTO instituicoes_publicas (id, nome, nome_fantasia, cnpj, observacoes, ativo, created_at, updated_at) VALUES (?, ?, NULL, NULL, NULL, 1, NOW(), NOW())'
        );
        $statement->execute(['INS-1', 'Recriada']);
        $pdo->commit();
        file_put_contents(rei149Marker($token, 'done'), 'replaced');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    exit(0);
}

$pdo = rei149Connection();
$pdo->exec('DROP TABLE IF EXISTS instituicoes_publicas');
$pdo->exec(
    'CREATE TABLE instituicoes_publicas (' .
    'id VARCHAR(64) NOT NULL PRIMARY KEY, nome VARCHAR(255) NOT NULL, nome_fantasia VARCHAR(100) NULL,' .
    'cnpj VARCHAR(32) NULL, observacoes TEXT NULL, ativo TINYINT NOT NULL DEFAULT 1,' .
    'created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL' .
    ') ENGINE=InnoDB'
);
$pdo->exec("INSERT INTO instituicoes_publicas (id,nome,ativo,created_at,updated_at) VALUES ('INS-1','Inicial',1,NOW(),NOW())");

$service = new InstituicoesPublicasService($pdo);
rei149Assert(!$service->update('AUSENTE', ['nome' => 'X']), 'ID ausente não foi recusado.');
rei149Assert($service->update('INS-1', ['observacoes' => 'atualizada']), 'Atualização simples falhou.');
rei149Assert($service->update('INS-1', ['nome' => 'Inicial']), 'Update sem mudança foi tratado como falha.');

$token = bin2hex(random_bytes(8));
$ready = rei149Marker($token, 'ready');
$attempting = rei149Marker($token, 'attempting');
$done = rei149Marker($token, 'done');
$release = rei149Marker($token, 'release');
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$lockWorker = proc_open([PHP_BINARY, __FILE__, 'lock-worker', $token], $descriptors, $lockPipes);
rei149Assert(is_resource($lockWorker), 'Não foi possível iniciar o worker de bloqueio.');

try {
    rei149Assert(rei149WaitForMarker($ready, 10_000), 'Timeout aguardando aquisição do bloqueio.');
    $replaceWorker = proc_open([PHP_BINARY, __FILE__, 'replace-worker', $token], $descriptors, $replacePipes);
    rei149Assert(is_resource($replaceWorker), 'Não foi possível iniciar o worker concorrente.');
    rei149Assert(rei149WaitForMarker($attempting, 10_000), 'Timeout aguardando início da tentativa concorrente.');
    $connectionId = (int) file_get_contents($attempting);
    $waitQuery = $pdo->prepare(
        'SELECT COUNT(*) FROM performance_schema.data_lock_waits w ' .
        'JOIN performance_schema.threads t ON t.THREAD_ID = w.REQUESTING_THREAD_ID ' .
        'JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID AND l.ENGINE = w.ENGINE ' .
        'WHERE t.PROCESSLIST_ID = :connection_id AND l.OBJECT_SCHEMA = DATABASE() ' .
        'AND l.OBJECT_NAME = \'instituicoes_publicas\' AND l.INDEX_NAME = \'PRIMARY\' AND l.LOCK_STATUS = \'WAITING\''
    );
    $lockWaitObserved = false;
    $waitDeadline = microtime(true) + 2.0;
    do {
        $waitQuery->execute([':connection_id' => $connectionId]);
        if ((int) $waitQuery->fetchColumn() > 0) {
            $lockWaitObserved = true;
            break;
        }
        usleep(20_000);
    } while (microtime(true) < $waitDeadline);
    rei149Assert($lockWaitObserved, 'Performance Schema não confirmou a espera do worker concorrente no registro PRIMARY.');
    rei149Assert(!is_file($done), 'Delete/reinsert atravessou a transação que deveria manter o lock.');
    file_put_contents($release, 'release');

    $lockExit = proc_close($lockWorker);
    $lockWorker = null;
    $replaceExit = proc_close($replaceWorker);
    $replaceWorker = null;
    rei149Assert($lockExit === 0, 'Worker de bloqueio falhou.');
    rei149Assert($replaceExit === 0 && is_file($done), 'Worker concorrente não concluiu após liberar o lock.');
} finally {
    if (is_resource($lockWorker) || (isset($lockWorker) && $lockWorker === null)) {
        if (!is_file($release)) {
            file_put_contents($release, 'abort');
        }
    }
    if (is_resource($lockWorker)) {
        proc_terminate($lockWorker);
        proc_close($lockWorker);
    }
    if (isset($replaceWorker) && is_resource($replaceWorker)) {
        proc_terminate($replaceWorker);
        proc_close($replaceWorker);
    }
    @unlink($ready);
    @unlink($attempting);
    @unlink($done);
    @unlink($release);
}

$row = $pdo->query("SELECT nome FROM instituicoes_publicas WHERE id = 'INS-1'")->fetchColumn();
rei149Assert($row === 'Recriada', 'O writer concorrente não recriou a linha após a conclusão serializada.');
echo "PASS REI-149 MySQL/InnoDB: update, missing ID, no-op e exclusão/recriação serializadas pelo lock de linha.\n";
