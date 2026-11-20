<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';

function assertFixture(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->prepare('SELECT u.email, u.senha_hash, u.cadastro_ativo, up.perfil FROM usuarios u LEFT JOIN usuario_perfil up ON up.usuario = u.id_usuario WHERE u.id_usuario = ?');
$stmt->execute(['USR-11111111111111111111111111']);
$active = $stmt->fetch(PDO::FETCH_ASSOC);

assertFixture(is_array($active), 'Fixture de usuário ativo ausente.');
assertFixture($active['email'] === 'ativo@auth-test.invalid', 'E-mail da fixture ativa incorreto.');
assertFixture((int) $active['cadastro_ativo'] === 1, 'Fixture ativa deve permanecer habilitada.');
assertFixture($active['perfil'] === 'ADMIN', 'Perfil da fixture ativa incorreto.');
assertFixture(Security::verifyPassword('Ativo!12345', (string) $active['senha_hash']), 'Senha Argon2id da fixture ativa incorreta.');

$stmt->execute(['USR-22222222222222222222222222']);
$inactive = $stmt->fetch(PDO::FETCH_ASSOC);
assertFixture(is_array($inactive), 'Fixture de usuário inativo ausente.');
assertFixture($inactive['email'] === 'inativo@auth-test.invalid', 'E-mail da fixture inativa incorreto.');
assertFixture((int) $inactive['cadastro_ativo'] === 0, 'Fixture inativa deve permanecer desabilitada.');

echo "PASS auth-fixtures\n";
