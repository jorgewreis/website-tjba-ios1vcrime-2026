<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP TABLE IF EXISTS login_rate_limits_unavailable');
$schema = file_get_contents(__DIR__ . '/fixtures/auth-schema.sql');
if ($schema === false) {
    throw new RuntimeException('Fixture do schema de autenticação não encontrada.');
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $pdo->exec($statement);
}

$usuariosColumns = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('ultimo_acesso', $usuariosColumns, true)) {
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN ultimo_acesso DATETIME NULL AFTER token_expira');
}

foreach (['login_rate_limits', 'historico_senha', 'recuperacao_senha', 'usuario_perfil', 'usuarios'] as $table) {
    $pdo->exec('DELETE FROM ' . $table);
}

$users = [
    [
        'id' => 'USR-11111111111111111111111111',
        'name' => 'Conta Ativa',
        'email' => 'ativo@auth-test.invalid',
        'cpf' => '529.982.247-25',
        'password' => 'Ativo!12345',
        'active' => 1,
        'profileId' => 'USP-11111111111111111111111111',
        'profile' => 'ADMIN',
    ],
    [
        'id' => 'USR-22222222222222222222222222',
        'name' => 'Conta Inativa',
        'email' => 'inativo@auth-test.invalid',
        'cpf' => '111.444.777-35',
        'password' => 'Inativo!12345',
        'active' => 0,
        'profileId' => 'USP-22222222222222222222222222',
        'profile' => 'CONSULTOR',
    ],
];

$insertUser = $pdo->prepare('INSERT INTO usuarios (id_usuario, nome, email, cpf, senha_hash, cadastro_ativo) VALUES (?, ?, ?, ?, ?, ?)');
$insertProfile = $pdo->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
foreach ($users as $user) {
    $insertUser->execute([
        $user['id'],
        $user['name'],
        $user['email'],
        $user['cpf'],
        Security::hashPassword($user['password']),
        $user['active'],
    ]);
    $insertProfile->execute([$user['profileId'], $user['id'], $user['profile']]);
}

echo "PASS fixture-setup\n";
