<?php
declare(strict_types=1);

require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';

function assertLogoutRace(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
    throw new RuntimeException('Não foi possível iniciar a sessão sintética de REI-133.');
}

$pdo = Conection::getInstance()->getConnection();
$userId = 'USR-11111111111111111111111111';
$oldToken = bin2hex(random_bytes(32));
$newToken = bin2hex(random_bytes(32));
$setToken = $pdo->prepare('UPDATE usuarios SET token_acesso = ?, token_expira = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id_usuario = ?');
$readToken = $pdo->prepare('SELECT token_acesso, token_expira FROM usuarios WHERE id_usuario = ?');
$readToken->execute([$userId]);
$original = $readToken->fetch(PDO::FETCH_ASSOC);
assertLogoutRace(is_array($original), 'Usuário sintético de REI-133 não existe.');

try {
    // Request A validates its old token before request B completes a new login.
    $setToken->execute([Security::hashAccessToken($oldToken), $userId]);
    $_SESSION = [];
    Security::initializeAuthenticatedSession($userId, 'ADMIN', $oldToken, 'Conta Ativa');
    assertLogoutRace(Security::isAuthenticated(), 'A sessão antiga deve estar válida antes do segundo login.');
    $oldSession = $_SESSION;

    // Request B logs in and atomically replaces the persisted token.
    $setToken->execute([Security::hashAccessToken($newToken), $userId]);
    $_SESSION = [];
    Security::initializeAuthenticatedSession($userId, 'ADMIN', $newToken, 'Conta Ativa');
    assertLogoutRace(Security::isAuthenticated(), 'A sessão nova deve estar válida após o segundo login.');
    $newSession = $_SESSION;

    // Request A resumes after B committed. Its conditional UPDATE must be a no-op.
    $usuario = new Usuario();
    $_SESSION = $oldSession;
    $usuario->logoutLocal($userId, $oldToken);
    $readToken->execute([$userId]);
    $afterOldLogout = $readToken->fetch(PDO::FETCH_ASSOC);
    assertLogoutRace(($afterOldLogout['token_acesso'] ?? null) === Security::hashAccessToken($newToken), 'Logout antigo revogou o token do segundo login.');

    $_SESSION = $newSession;
    assertLogoutRace(Security::isAuthenticated(), 'A sessão mais recente foi invalidada pelo logout antigo.');

    // The stale browser session still cleans up locally when it next reaches the app.
    $_SESSION = $oldSession;
    assertLogoutRace(!Security::isAuthenticated(), 'A sessão antiga continuou autenticada.');
    assertLogoutRace(!isset($_SESSION['id_usuario'], $_SESSION['token']), 'A sessão antiga não foi limpa localmente.');

    // Retrying the already-revoked old token is harmless; a normal logout clears the new one.
    $usuario->logoutLocal($userId, $oldToken);
    $readToken->execute([$userId]);
    assertLogoutRace(($readToken->fetchColumn()) === Security::hashAccessToken($newToken), 'Retry do logout antigo alterou o token novo.');
    $usuario->logoutLocal($userId, $newToken);
    $readToken->execute([$userId]);
    $revoked = $readToken->fetch(PDO::FETCH_ASSOC);
    assertLogoutRace(($revoked['token_acesso'] ?? null) === null && ($revoked['token_expira'] ?? null) === null, 'Logout válido não revogou token e validade.');

    $_SESSION = $newSession;
    assertLogoutRace(!Security::isAuthenticated(), 'Sessão correspondente ao token revogado permaneceu válida.');
    echo "PASS REI-133 stale logout race and session cleanup (isolated MySQL)\n";
} finally {
    $restore = $pdo->prepare('UPDATE usuarios SET token_acesso = ?, token_expira = ? WHERE id_usuario = ?');
    $restore->execute([$original['token_acesso'], $original['token_expira'], $userId]);
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
