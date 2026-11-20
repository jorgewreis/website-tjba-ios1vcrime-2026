<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';

function assertConcurrency(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$userId = 'USR-00000000000000000000000001';
$resetId = 'REC-00000000000000000000000001';
$plainToken = bin2hex(random_bytes(32));
$passwords = ['ConcorrenteA!123', 'ConcorrenteB!123'];

$pdo->prepare('INSERT INTO usuarios (id_usuario, nome, email, cpf, senha_hash) VALUES (?, ?, ?, ?, ?)')->execute([
    $userId,
    'Conta Sintética',
    'conta@auth-test.invalid',
    '123.456.789-09',
    Security::hashPassword('Inicial!123'),
]);
$pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)')->execute([
    $resetId,
    $userId,
    Security::hashAccessToken($plainToken),
    '127.0.0.1',
]);

$barrierPath = __DIR__ . '/.reset-concurrency-barrier.runtime';
@unlink($barrierPath);
$worker = __DIR__ . '/reset-concurrency.worker.php';
$processes = [];
foreach ($passwords as $password) {
    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, $worker, $plainToken, $password, $barrierPath],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__, 2)
    );
    assertConcurrency(is_resource($process), 'Não foi possível iniciar processo concorrente.');
    $processes[] = ['process' => $process, 'pipes' => $pipes, 'password' => $password];
}

file_put_contents($barrierPath, 'release');
$results = [];
foreach ($processes as $entry) {
    $stdout = stream_get_contents($entry['pipes'][1]);
    $stderr = stream_get_contents($entry['pipes'][2]);
    fclose($entry['pipes'][1]);
    fclose($entry['pipes'][2]);
    $exitCode = proc_close($entry['process']);
    assertConcurrency($exitCode === 0, 'Worker concorrente falhou: ' . trim($stderr));
    $decoded = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
    $results[] = ['result' => $decoded, 'password' => $entry['password']];
}
@unlink($barrierPath);

$winners = array_values(array_filter($results, static fn (array $entry): bool => ($entry['result']['success'] ?? false) === true));
assertConcurrency(count($winners) === 1, 'O mesmo token deve permitir exatamente um reset bem-sucedido.');
assertConcurrency((int) $pdo->query("SELECT usado FROM recuperacao_senha WHERE id_recuperacao = '{$resetId}'")->fetchColumn() === 1, 'O token precisa ficar marcado como usado.');
$storedHash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '{$userId}'")->fetchColumn();
assertConcurrency(Security::verifyPassword($winners[0]['password'], $storedHash), 'A senha final deve ser a do único worker vencedor.');

echo "PASS reset-token-concurrency\n";
