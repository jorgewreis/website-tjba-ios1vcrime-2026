<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
set_exception_handler(static function (Throwable $e): void { $allowed = ['Timeout não restaurado após sucesso.', 'Guarda deveria expirar.', 'Timeout não foi limitado/restaurado.', 'Preparação ou troca afetou transação externa.', 'Escrita externa foi confirmada indevidamente.', 'Preparação não limitou/restaurou timeout.', 'Falha dupla não ocorreu.', 'Conexão insegura aceita.', 'Falha esperada não ocorreu.', 'Rollback não preservou senha e histórico.']; $idx = array_search($e->getMessage(), $allowed, true); $msg = $idx === false ? get_class($e) . ' code=' . (string)$e->getCode() . ' native=' . (string)($e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0) : 'case-' . $idx; echo 'REI155_DIAG: ' . ($GLOBALS['stage'] ?? 'initial') . ' ' . $msg . PHP_EOL; exit(1); });
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';
require dirname(__DIR__, 2) . '/app/helpers/PasswordResetTransactionalGuard.php';
if (DB_HOST !== 'mysql' || DB_NAME !== 'auth_test_e2e') { throw new RuntimeException('Sandbox inválido.'); }
if (PasswordResetTransactionalGuard::normalizeMetadataTableName('Usuarios', '2') !== 'usuarios' || PasswordResetTransactionalGuard::normalizeMetadataTableName('Usuarios', '0') !== 'Usuarios') throw new RuntimeException('Normalização de caixa inválida.');
echo "PASS normalização modo 2 simulada (não é servidor modo 2)\n";
$pdo = Conection::getInstance()->getConnection();
$other = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['historico_senha','usuarios','recuperacao_senha'] as $table) {
    $pdo->exec('SET SESSION lock_wait_timeout = 17');
    $pdo->beginTransaction();
    try {
        PasswordResetTransactionalGuard::requireTransactionalTables($pdo);
        if ((int)$pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== 17) { throw new RuntimeException('Timeout não restaurado após sucesso.'); }
        $other->exec('SET SESSION lock_wait_timeout = 1');
        try { $other->exec("ALTER TABLE `$table` ENGINE=InnoDB"); throw new RuntimeException("ALTER concorrente em $table não aguardou."); }
        catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1205) { throw $e; } }
    } finally { if ($pdo->inTransaction()) { $pdo->rollBack(); } }
    $other->exec("ALTER TABLE `$table` ENGINE=InnoDB");
    echo "PASS MDL protegeu $table e liberou após rollback\n";
}
// Bloqueio exclusivo no segundo PDO faz a guarda expirar sem escrita e restaura timeout.
$other->exec('LOCK TABLES `usuarios` WRITE');
try {
    $pdo->exec('SET SESSION lock_wait_timeout = 17');
    $pdo->beginTransaction();
    $start = microtime(true);
    try { PasswordResetTransactionalGuard::requireTransactionalTables($pdo); throw new RuntimeException('Guarda deveria expirar.'); }
    catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1205) { throw $e; } }
    $elapsed = microtime(true) - $start;
    if ($elapsed > 8 || (int)$pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== 17) { throw new RuntimeException('Timeout não foi limitado/restaurado.'); }
    $pdo->rollBack();
} finally { if ($pdo->inTransaction()) { $pdo->rollBack(); } $other->exec('UNLOCK TABLES'); }
echo "PASS timeout de metadados limitado e restaurado\n";
$GLOBALS['stage'] = 'external';
// Preparação de storage usa DDL; uma transação externa jamais pode sofrer commit implícito.
require_once dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
require_once dirname(__DIR__, 2) . '/app/models/Usuario.php';
$pdo->beginTransaction();
try {
    $pdo->exec("UPDATE usuarios SET nome='REI155-MARCADOR' WHERE id_usuario='USR-11111111111111111111111111'");
    $storage = (new PasswordResetService())->prepareResetStorage();
    $change = (new Usuario())->alterarSenha('USR-11111111111111111111111111', 'Ativo!12345', 'Outra!Senha1552026');
    if ($storage || !empty($change['success']) || !$pdo->inTransaction()) { throw new RuntimeException('Preparação ou troca afetou transação externa.'); }
    $pdo->rollBack();
    if ($pdo->query("SELECT nome FROM usuarios WHERE id_usuario='USR-11111111111111111111111111'")->fetchColumn() === 'REI155-MARCADOR') { throw new RuntimeException('Escrita externa foi confirmada indevidamente.'); }
} finally { if ($pdo->inTransaction()) { $pdo->rollBack(); } }
echo "PASS preparação e troca preservaram transação externa\n";
$GLOBALS['stage'] = 'prepare';
// Preparação deve limitar também a espera do ALTER de schema legado.
$pdo->exec('ALTER TABLE recuperacao_senha DROP COLUMN ip');
$other->exec('LOCK TABLES `recuperacao_senha` WRITE');
try {
    $pdo->exec('SET SESSION lock_wait_timeout = 17');
    $start = microtime(true);
    $ready = (new PasswordResetService())->prepareResetStorage();
    if ($ready || microtime(true)-$start > 8 || (int)$pdo->query('SELECT @@SESSION.lock_wait_timeout')->fetchColumn() !== 17) { throw new RuntimeException('Preparação não limitou/restaurou timeout.'); }
} finally { $other->exec('UNLOCK TABLES'); $pdo->exec("ALTER TABLE recuperacao_senha ADD COLUMN ip VARCHAR(45) NOT NULL DEFAULT '' AFTER usado"); }
echo "PASS preparação limitada sob lock exclusivo\n";
$GLOBALS['stage'] = 'double';
// Simula erro primário e falha adicional ao restaurar o timeout sem perder a primeira causa.
class Rei155RestoreFailurePDO extends PDO {
    private int $sets = 0;
    public function exec(string $statement): int|false {
        if (str_starts_with($statement, 'SET SESSION lock_wait_timeout') && ++$this->sets === 2) { throw new RuntimeException('restore-failed'); }
        return parent::exec($statement);
    }
}
$fake = new Rei155RestoreFailurePDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try { PasswordResetTransactionalGuard::withBoundedMetadataWait($fake, static function (): void { throw new RuntimeException('primary-failed'); }); throw new RuntimeException('Falha dupla não ocorreu.'); }
catch (RuntimeException $e) { if ($e->getMessage() !== 'primary-failed') { throw $e; } }
try { PasswordResetTransactionalGuard::withBoundedMetadataWait($fake, static fn() => null); throw new RuntimeException('Conexão insegura aceita.'); }
catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'Conexão indisponível')) { throw $e; } }
$fake = null;
echo "PASS falha dupla preservou erro primário e invalidou conexão\n";
$GLOBALS['stage'] = 'trigger';
// Trigger sintético equivalente ao canônico deve reverter histórico com a senha.
$GLOBALS['stage'] = 'history_create';
$pdo->exec("CREATE TRIGGER rei155_history AFTER UPDATE ON usuarios FOR EACH ROW BEGIN IF OLD.senha_hash <> NEW.senha_hash THEN INSERT INTO historico_senha (id_historico,usuario,senha_hash,data_alteracao) VALUES (CONCAT('HIS-',UPPER(SUBSTRING(REPLACE(UUID(),'-',''),1,26))),NEW.id_usuario,OLD.senha_hash,NOW()); END IF; END");
$GLOBALS['stage'] = 'token_create';
$pdo->exec("CREATE TRIGGER rei155_fail_token BEFORE UPDATE ON recuperacao_senha FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'REI155_FAIL_TOKEN'");
$user = 'USR-11111111111111111111111111';
$tokenId = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
$pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao,usuario,token,expira_em,usado,ip) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),0,?)')->execute([$tokenId,$user,hash('sha256', random_bytes(32)),'127.0.0.1']);
$GLOBALS['stage'] = 'trigger_run';
$hashBefore = $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario='$user'")->fetchColumn();
$countBefore = (int)$pdo->query("SELECT COUNT(*) FROM historico_senha WHERE usuario='$user'")->fetchColumn();
try {
    try { (new Usuario())->alterarSenha($user, 'Ativo!12345', 'Nova!Trigger1552026'); throw new RuntimeException('Falha esperada não ocorreu.'); }
    catch (PDOException $e) { if (!str_contains($e->getMessage(), 'REI155_FAIL_TOKEN')) throw $e; }
    if ($pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario='$user'")->fetchColumn() !== $hashBefore || (int)$pdo->query("SELECT COUNT(*) FROM historico_senha WHERE usuario='$user'")->fetchColumn() !== $countBefore) { throw new RuntimeException('Rollback não preservou senha e histórico.'); }
} finally { $pdo->exec('DROP TRIGGER IF EXISTS rei155_history'); $pdo->exec('DROP TRIGGER IF EXISTS rei155_fail_token'); $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao=?')->execute([$tokenId]); }
echo "PASS histórico por trigger revertido com falha da troca\n";
// Em modo 0, metadados da tabela homônima não podem mascarar upgrade da exata.
if ((int)$pdo->query('SELECT @@lower_case_table_names')->fetchColumn() === 0) {
    $pdo->exec('CREATE TABLE RECUPERACAO_SENHA LIKE recuperacao_senha');
    try {
        $pdo->exec('ALTER TABLE RECUPERACAO_SENHA ENGINE=MyISAM');
        $pdo->exec('ALTER TABLE recuperacao_senha DROP COLUMN ip');
        if (!(new PasswordResetService())->prepareResetStorage()) throw new RuntimeException('Preparação rejeitou homônima distinta.');
        $column = $pdo->query("SHOW COLUMNS FROM recuperacao_senha LIKE 'ip'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) throw new RuntimeException('Upgrade confundiu metadados da homônima.');
    } finally { $pdo->exec('DROP TABLE RECUPERACAO_SENHA'); }
    echo "PASS preparação distingue tabela homônima por caixa\n";
}
// Nomes de coluna são insensíveis à caixa no MySQL: não duplicar colunas existentes.
$pdo->exec("ALTER TABLE recuperacao_senha CHANGE ip IP VARCHAR(45) NOT NULL, CHANGE usado USADO TINYINT(1) NOT NULL DEFAULT 0, CHANGE token TOKEN VARCHAR(100) NOT NULL");
try {
    if (!(new PasswordResetService())->prepareResetStorage()) throw new RuntimeException('Colunas maiúsculas foram rejeitadas.');
} finally {
    $pdo->exec("ALTER TABLE recuperacao_senha CHANGE IP ip VARCHAR(45) NOT NULL, CHANGE USADO usado TINYINT(1) NOT NULL DEFAULT 0, CHANGE TOKEN token VARCHAR(100) NOT NULL");
}
echo "PASS colunas maiúsculas no schema MySQL\n";
// Emissão não altera a senha e continua disponível sem tabela opcional de histórico.
$pdo->exec('RENAME TABLE historico_senha TO rei155_historico_ausente');
try {
    $limiter = new LoginRateLimiter($pdo, 'reset');
    $limiter->clear('529.982.247-25','127.0.0.1');
    $before = (int)$pdo->query("SELECT COUNT(*) FROM recuperacao_senha WHERE usuario='USR-11111111111111111111111111'")->fetchColumn();
    (new PasswordResetService())->requestReset('529.982.247-25','ativo@auth-test.invalid');
    $after = (int)$pdo->query("SELECT COUNT(*) FROM recuperacao_senha WHERE usuario='USR-11111111111111111111111111'")->fetchColumn();
    if ($after !== $before + 1) throw new RuntimeException('Emissão recusou histórico ausente.');
} finally {
    $pdo->exec('RENAME TABLE rei155_historico_ausente TO historico_senha');
    (new LoginRateLimiter($pdo, 'reset'))->clear('529.982.247-25','127.0.0.1');
}
echo "PASS emissão com histórico ausente\n";
