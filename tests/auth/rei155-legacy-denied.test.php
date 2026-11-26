<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
if (DB_NAME !== 'auth_test_e2e' || DB_HOST !== 'mysql') { throw new RuntimeException('Banco descartável não confirmado.'); }
$root = Conection::getInstance()->getConnection();
$suffix = bin2hex(random_bytes(5));
$db = 'rei155_legacy_' . $suffix;
$user = 'r155_' . $suffix;
$pass = bin2hex(random_bytes(16));
$limited = null;
try {
    $root->exec("CREATE DATABASE `$db`");
    $root->exec("CREATE TABLE `$db`.usuarios (id_usuario CHAR(30) PRIMARY KEY) ENGINE=InnoDB");
    $root->exec("CREATE TABLE `$db`.recuperacao_senha (id_recuperacao CHAR(30) PRIMARY KEY, usuario CHAR(30) NOT NULL, token VARCHAR(32) NOT NULL, expira_em DATETIME NOT NULL) ENGINE=InnoDB");
    $root->exec("CREATE USER '$user'@'%' IDENTIFIED BY " . $root->quote($pass));
    $root->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE ON `$db`.* TO '$user'@'%'");
    $limited = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $db . ';charset=utf8mb4', $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $service = new PasswordResetService();
    (new ReflectionProperty(PasswordResetService::class, 'pdo'))->setValue($service, $limited);
    if ($service->prepareResetStorage()) { throw new RuntimeException('Schema legado sem ALTER deve falhar fechado.'); }
    $columns = $root->query("SHOW COLUMNS FROM `$db`.recuperacao_senha")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('usado', $columns, true)) { throw new RuntimeException('Upgrade inesperadamente alterou schema sem permissão.'); }
    echo "PASS REI-155 schema legado sem ALTER falha fechado\n";
    $root->exec("ALTER TABLE `$db`.recuperacao_senha MODIFY token VARCHAR(100) NOT NULL, ADD usado TINYINT(1) NOT NULL DEFAULT 0, ADD ip VARCHAR(45) NOT NULL DEFAULT '', ADD data_solicitacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $root->exec("REVOKE CREATE ON `$db`.* FROM '$user'@'%'");
    $service = new PasswordResetService();
    (new ReflectionProperty(PasswordResetService::class, 'pdo'))->setValue($service, $limited);
    if (!$service->prepareResetStorage()) throw new RuntimeException('Schema pronto foi recusado sem CREATE.');
    echo "PASS schema pronto não exige CREATE\n";
    // Execute a troca real com o mesmo PDO restrito, inclusive invalidação do token.
    $root->exec("ALTER TABLE `$db`.usuarios ADD senha_hash VARCHAR(255) NOT NULL, ADD updated_at DATETIME NULL");
    $root->exec("CREATE TABLE `$db`.historico_senha (id_historico CHAR(30) PRIMARY KEY, usuario CHAR(30), senha_hash VARCHAR(255), data_alteracao DATETIME) ENGINE=InnoDB");
    $fixtureUser = 'USR-11111111111111111111111111';
    $root->prepare("INSERT INTO `$db`.usuarios (id_usuario,senha_hash) VALUES (?,?)")->execute([$fixtureUser, Security::hashPassword('Ativo!12345')]);
    $root->prepare("INSERT INTO `$db`.recuperacao_senha (id_recuperacao,usuario,token,expira_em,usado,ip) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE),0,?)")->execute(['REC-11111111111111111111111111',$fixtureUser,hash('sha256','token-sintetico'),'127.0.0.1']);
    $connection = Conection::getInstance();
    $pdoProperty = new ReflectionProperty(Conection::class, 'pdo');
    try {
        $pdoProperty->setValue($connection, $limited);
        $changed = (new Usuario())->alterarSenha($fixtureUser, 'Ativo!12345', 'Nova!SemCreate1552026');
    } finally { $pdoProperty->setValue($connection, $root); }
    $row = $root->query("SELECT senha_hash FROM `$db`.usuarios WHERE id_usuario='$fixtureUser'")->fetchColumn();
    $used = $root->query("SELECT usado FROM `$db`.recuperacao_senha WHERE id_recuperacao='REC-11111111111111111111111111'")->fetchColumn();
    if (empty($changed['success']) || !Security::verifyPassword('Nova!SemCreate1552026', (string)$row) || (int)$used !== 1) throw new RuntimeException('Troca completa falhou sem CREATE.');
    echo "PASS troca completa sem CREATE\n";
} finally {
    $limited = null;
    $root->exec("DROP USER IF EXISTS '$user'@'%'");
    $root->exec("DROP DATABASE IF EXISTS `$db`");
}
