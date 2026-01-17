<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';

try {
    if (Security::isAuthenticated()) {
        $idUsuario = Security::getUserId();
        $usuario = new Usuario();
        $usuario->logoutLocal($idUsuario);
    }
} catch (Throwable $e) {
    error_log('Erro no logout: ' . $e->getMessage());
} finally {
    header('Location: /index.php');
    exit;
}
