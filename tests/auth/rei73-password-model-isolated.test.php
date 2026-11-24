<?php
declare(strict_types=1);
// The wrapper supplies copied production classes and a connection stub that always throws.
define('PASSWORD_MIN_LENGTH', 10);
define('PASSWORD_MAX_LENGTH', 128);
define('PASSWORD_HISTORY_ON_CHANGE_ENABLED', false);
require $argv[1] . '/app/models/Usuario.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('NOW', static fn() => '2099-01-01 00:00:00');
$pdo->exec('CREATE TABLE usuarios (id_usuario TEXT PRIMARY KEY, senha_hash TEXT NOT NULL, updated_at TEXT)');
$insert = $pdo->prepare('INSERT INTO usuarios (id_usuario, senha_hash) VALUES (?, ?)');
$original = Security::hashPassword('SenhaAntiga!123');
$other = Security::hashPassword('OutraSenha!123');
$insert->execute(['synthetic-user', $original]);
$insert->execute(['other-user', $other]);
$reflection = new ReflectionClass(Usuario::class);
$user = $reflection->newInstanceWithoutConstructor();
$property = $reflection->getProperty('pdo');
$property->setValue($user, $pdo);
$read = static function (string $id) use ($pdo): string {
    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id_usuario = ?');
    $stmt->execute([$id]);
    return (string) $stmt->fetchColumn();
};
$cases = [
    ['', 'SenhaAntiga!123', 'SenhaNova!456'],
    ['missing', 'SenhaAntiga!123', 'SenhaNova!456'],
    ['synthetic-user', 'errada', 'SenhaNova!456'],
    ['synthetic-user', 'SenhaAntiga!123', 'fraca'],
    ['synthetic-user', 'SenhaAntiga!123', 'SenhaAntiga!123'],
];
foreach ($cases as $case) {
    $result = $user->alterarSenha(...$case);
    if (($result['success'] ?? null) !== false || $read('synthetic-user') !== $original || $read('other-user') !== $other) {
        throw new RuntimeException('Rejected case changed data or returned unexpected result');
    }
}
$result = $user->alterarSenha('synthetic-user', 'SenhaAntiga!123', 'SenhaNova!456');
if (($result['success'] ?? null) !== true || !Security::verifyPassword('SenhaNova!456', $read('synthetic-user')) || $read('other-user') !== $other) {
    throw new RuntimeException('Successful change did not update only the expected user');
}
$beforeFailure = $read('synthetic-user');
$pdo->exec("CREATE TRIGGER fail_password_update BEFORE UPDATE ON usuarios BEGIN SELECT RAISE(ABORT, 'synthetic-write-failure'); END");
$failureObserved = false;
try {
    $user->alterarSenha('synthetic-user', 'SenhaNova!456', 'SenhaFinal!789');
} catch (PDOException $e) {
    $failureObserved = str_contains($e->getMessage(), 'synthetic-write-failure');
}
if (!$failureObserved || $read('synthetic-user') !== $beforeFailure || $read('other-user') !== $other) {
    throw new RuntimeException('Injected write failure was not observed or changed data');
}
echo "PASS: password model validations and SQLite in-memory persistence\n";
