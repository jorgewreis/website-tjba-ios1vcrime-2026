<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se usuário está autenticado
if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    $token = $_SESSION['token'] ?? null;
    $userId = Security::getUserId();
    
    if (!$token || !$userId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    
    // Validar token com usuário
    $usuario = new Usuario();
    $user = $usuario->validarToken($token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token expirado ou inválido']);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Token válido',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao verificar token: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit;
}
