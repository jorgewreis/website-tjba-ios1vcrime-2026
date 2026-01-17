<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    require_once __DIR__ . '/../bd/Conection.php';

    $pdo = Conection::getInstance()->getConnection();

    /* Invalida TODOS os tokens do sistema */
    $pdo->exec("
        UPDATE usuarios
        SET token_acesso = NULL,
            token_expira = NULL
    ");

    /* Limpa sessão local */
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

} catch (Throwable $e) {
    error_log('Erro no logout: ' . $e->getMessage());
} finally {
    header('Location: /index.php');
    exit;
}
