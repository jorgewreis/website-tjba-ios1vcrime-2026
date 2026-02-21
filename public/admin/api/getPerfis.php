<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/Conection.php';
require_once __DIR__ . '/../../../app/helpers/Conexao.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $userId = Security::getUserId();
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
        exit;
    }

    $perfis = [];

    // Prefer PDO (Conection). Fallback to mysqli (Conexao) if PDO not available.
    if (class_exists('Conection')) {
        $pdo = Conection::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT UPPER(TRIM(perfil)) as perfil
            FROM usuario_perfil 
            WHERE usuario = ? AND perfil IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $perfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif (class_exists('Conexao')) {
        $mysqli = Conexao::getConn();
        $sql = "SELECT DISTINCT UPPER(TRIM(perfil)) as perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param('s', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_array(MYSQLI_NUM)) {
                $perfis[] = $row[0];
            }
            $stmt->close();
        }
    } else {
        throw new RuntimeException('Nenhum adaptador de banco de dados disponível (Conection/Conexao).');
    }
    
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
