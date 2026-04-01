<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $token = Security::getToken();
    $userId = Security::getUserId();
    
    if (!$token || !$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    
    $usuario = new Usuario();
    $user = $usuario->validarToken($token);
    
    if (!$user) {
        Security::clearAuthenticatedSession();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token expirado ou inválido']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'valid' => true,
        'message' => 'Token válido',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao verificar token: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
