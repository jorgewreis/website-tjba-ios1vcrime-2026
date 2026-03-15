<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/Conection.php';
require_once __DIR__ . '/../../../app/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $token = Security::getToken();
    $usuario = new Usuario();
    $user = ($token !== null && $token !== '') ? $usuario->validarToken($token) : null;
    if ($user === null) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
        exit;
    }

    $userId = Security::getUserId();
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
        exit;
    }

    $pdo = Conection::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT DISTINCT UPPER(TRIM(perfil)) as perfil
        FROM usuario_perfil 
        WHERE usuario = ? AND perfil IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $perfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'perfis' => array_values($perfis ?? [])
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar perfis: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
