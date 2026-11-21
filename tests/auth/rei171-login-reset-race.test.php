<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';

function assertRei171(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei171Cpf(): string
{
    do {
        $digits = [];
        for ($i = 0; $i < 9; $i++) {
            $digits[] = random_int(0, 9);
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $digits[$i] * (10 - $i);
        }
        $first = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);
        $digits[] = $first;
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $digits[$i] * (11 - $i);
        }
        $second = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);
        $digits[] = $second;
        $cpf = implode('', $digits);
    } while (preg_match('/^(\d)\1{10}$/', $cpf) === 1);

    return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
}

function rei171Id(string $prefix): string
{
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(13)));
}

$pdo = Conection::getInstance()->getConnection();
$userId = rei171Id('USR');
$profileId = rei171Id('USP');
$initialPassword = 'Antiga!Senha171';
$newPassword = 'Nova!Senha1712026';
$failedPassword = 'Outra!Senha1712026';
$resetToken = bin2hex(random_bytes(32));
$rollbackToken = bin2hex(random_bytes(32));
$existingToken = bin2hex(random_bytes(32));
$triggerName = 'rei171_fail_password_' . strtolower(bin2hex(random_bytes(6)));
$profileInserted = false;
$userInserted = false;

try {
    $insertUser = $pdo->prepare('INSERT INTO usuarios (id_usuario, nome, email, cpf, senha_hash, token_acesso, token_expira, cadastro_ativo) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 1)');
    $insertUser->execute([
        $userId,
        'REI-171 User',
        strtolower($userId) . '@rei171.auth-test.invalid',
        rei171Cpf(),
        Security::hashPassword($initialPassword),
        Security::hashAccessToken($existingToken),
    ]);
    $userInserted = true;

    $insertProfile = $pdo->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
    $insertProfile->execute([$profileId, $userId, 'ADMIN']);
    $profileInserted = true;

    $insertReset = $pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)');
    $insertReset->execute([rei171Id('REC'), $userId, Security::hashAccessToken($resetToken), '127.0.0.1']);

    // Deterministic interleaving: authentication verifies the old hash and
    // retains its snapshot; password reset then commits; delayed token issuance
    // resumes only after the committed credential change.
    $staleLogin = new Usuario();
    $staleResult = $staleLogin->autenticar((string) $pdo->query('SELECT cpf FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetchColumn(), $initialPassword);
    assertRei171(($staleResult['success'] ?? false) === true, 'A senha antiga deve autenticar antes da redefinição concorrente.');

    $resetResult = (new PasswordResetService())->resetPassword($resetToken, $newPassword);
    assertRei171(($resetResult['success'] ?? false) === true, 'A redefinição sintética deve concluir.');
    assertRei171(!$pdo->inTransaction(), 'A redefinição precisa estar commitada antes de retomar a emissão de token.');
    assertRei171($pdo->query('SELECT token_acesso FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetchColumn() === null, 'O reset precisa invalidar o token anterior.');

    $staleToken = bin2hex(random_bytes(32));
    assertRei171($staleLogin->atualizarToken($userId, $staleToken, 1) === false, 'O login validado com hash antigo não pode emitir token após o commit do reset.');
    $afterStaleIssue = $pdo->query('SELECT token_acesso, token_expira FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetch(PDO::FETCH_ASSOC);
    assertRei171(is_array($afterStaleIssue) && $afterStaleIssue['token_acesso'] === null && $afterStaleIssue['token_expira'] === null, 'A tentativa obsoleta não pode deixar token ou expiração persistidos.');

    $freshLogin = new Usuario();
    $freshResult = $freshLogin->autenticar((string) $pdo->query('SELECT cpf FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetchColumn(), $newPassword);
    assertRei171(($freshResult['success'] ?? false) === true, 'A senha nova precisa autenticar normalmente.');
    $freshToken = bin2hex(random_bytes(32));
    assertRei171($freshLogin->atualizarToken($userId, $freshToken, 1) === true, 'A senha nova precisa conseguir emitir token.');
    $storedToken = (string) $pdo->query('SELECT token_acesso FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetchColumn();
    assertRei171(hash_equals(Security::hashAccessToken($freshToken), $storedToken), 'A sessão da senha nova precisa persistir somente seu token hash.');

    $insertReset->execute([rei171Id('REC'), $userId, Security::hashAccessToken($rollbackToken), '127.0.0.1']);
    $hashBeforeFailure = (string) $pdo->query('SELECT senha_hash FROM usuarios WHERE id_usuario = ' . $pdo->quote($userId))->fetchColumn();
    $pdo->exec("CREATE TRIGGER `{$triggerName}` BEFORE UPDATE ON usuarios FOR EACH ROW BEGIN IF OLD.id_usuario = '{$userId}' AND NEW.senha_hash <> OLD.senha_hash THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic REI-171 reset failure'; END IF; END");
    $previousErrorLog = (string) ini_get('error_log');
    ini_set('error_log', '/dev/null');
    try {
        $failedReset = (new PasswordResetService())->resetPassword($rollbackToken, $failedPassword);
    } finally {
        ini_set('error_log', $previousErrorLog);
    }
    $pdo->exec("DROP TRIGGER `{$triggerName}`");
    assertRei171(($failedReset['success'] ?? true) === false, 'Falha induzida ao atualizar senha deve rejeitar o reset.');
    $rollbackState = $pdo->prepare('SELECT u.senha_hash, u.token_acesso, r.usado FROM usuarios u JOIN recuperacao_senha r ON r.usuario = u.id_usuario WHERE u.id_usuario = ? AND r.token = ?');
    $rollbackState->execute([$userId, Security::hashAccessToken($rollbackToken)]);
    $rolledBack = $rollbackState->fetch(PDO::FETCH_ASSOC);
    assertRei171(is_array($rolledBack) && $rolledBack['senha_hash'] === $hashBeforeFailure, 'A falha não pode alterar parcialmente a senha.');
    assertRei171($rolledBack['token_acesso'] === Security::hashAccessToken($freshToken), 'A falha não pode invalidar nem substituir a sessão existente.');
    assertRei171((int) $rolledBack['usado'] === 0, 'A falha precisa restaurar o token de redefinição não consumido.');

    echo "PASS REI-171: stale login rejected after committed reset, new password token accepted, reset failure rolled back.\n";
} finally {
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS `{$triggerName}`");
    } catch (Throwable $e) {
        // Preserve the original test error while attempting normal fixture cleanup.
    }
    if ($userInserted) {
        $pdo->prepare('DELETE FROM recuperacao_senha WHERE usuario = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM historico_senha WHERE usuario = ?')->execute([$userId]);
        if ($profileInserted) {
            $pdo->prepare('DELETE FROM usuario_perfil WHERE usuario = ?')->execute([$userId]);
        }
        $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?')->execute([$userId]);
    }
}
