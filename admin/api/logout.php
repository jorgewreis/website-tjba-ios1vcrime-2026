<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Security::requireAuthentication();

    $idUsuario = Security::getUserId();

    $usuario = new Usuario();
    $resultado = $usuario->logoutLocal($idUsuario);

    echo json_encode($resultado);
    exit;

} catch (Throwable $e) {
    error_log('Erro no logout: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao realizar logout.'
    ]);
    exit;
}