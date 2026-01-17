<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Conection.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se usuário está autenticado
if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Obter ID do usuário
$userId = Security::getUserId();
if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
    exit;
}

try {
    $pdo = Conection::getInstance()->getConnection();
    
    // Query para buscar perfis do usuário
    $stmt = $pdo->prepare("
        SELECT perfil 
        FROM usuario_perfil 
        WHERE usuario = ?
    ");
    
    $stmt->execute([$userId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extrair valores de perfil
    $perfis = [];
    foreach ($results as $row) {
        if (!empty($row['perfil'])) {
            $perfis[] = $row['perfil'];
        }
    }
    
    // Se nenhum perfil encontrado, verificar se usuário existe
    if (empty($perfis)) {
        // Usuário autenticado mas sem perfil atribuído
        // Pode ser admin ou erro de configuração
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'perfis' => [],
            'message' => 'Usuário autenticado mas sem perfil atribuído'
        ]);
        exit;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'perfis' => array_values(array_unique($perfis))
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar perfis: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
    exit;
}
