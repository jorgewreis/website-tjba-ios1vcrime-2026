<?php
declare(strict_types=1);

// O guard deve anteceder configuração, conexão e qualquer DDL/escrita.
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE instituicoes_publicas (
    id VARCHAR(30) PRIMARY KEY, nome VARCHAR(200) NOT NULL, nome_fantasia VARCHAR(200),
    cnpj VARCHAR(20), observacoes TEXT, ativo TINYINT NOT NULL,
    created_at DATETIME, updated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec("INSERT INTO instituicoes_publicas VALUES
 ('INS-SYNTHETIC-ACTIVE', 'Instituicao Sintetica Ativa', 'ISA', NULL, NULL, 1, NOW(), NOW()),
 ('INS-SYNTHETIC-INACTIVE', 'Instituicao Sintetica Inativa', 'ISI', NULL, NULL, 0, NOW(), NOW())");

$user = $pdo->prepare('INSERT INTO usuarios (id_usuario, nome, email, cpf, senha_hash, cadastro_ativo) VALUES (?, ?, ?, ?, ?, 1)');
$profile = $pdo->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
foreach ([
    ['USR-33333333333333333333333333', 'Consultor Sintetico', 'consultor@auth-test.invalid', '258.369.147-37', 'USP-33333333333333333333333333', 'CONSULTOR'],
    ['USR-44444444444444444444444444', 'Estudante Sintetico', 'estudante@auth-test.invalid', '147.258.369-82', 'USP-44444444444444444444444444', 'ESTUDANTE'],
] as [$id, $name, $email, $cpf, $profileId, $role]) {
    $user->execute([$id, $name, $email, $cpf, Security::hashPassword('Sintetica!12345')]);
    $profile->execute([$profileId, $id, $role]);
}
echo "PASS rei73-instituicoes-fixture\n";
