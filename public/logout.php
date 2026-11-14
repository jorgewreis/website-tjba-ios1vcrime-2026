<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';

// Aceitar apenas POST para evitar logout acidental por GET (ex.: link em email)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Requisição inválida.');
}

try {
    if (Security::isAuthenticated()) {
        $idUsuario = Security::getUserId();
        $usuario = new Usuario();
        $usuario->logoutLocal((string) $idUsuario);
    }
} catch (Throwable $e) {
    error_log('Erro no logout: ' . $e->getMessage());
} finally {
    Security::clearAuthenticatedSession();
    header('Location: /index.php');
    exit;
}
