<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/BootstrapHelper.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
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

$pdo = getDatabaseConnection();

$userId = Security::getUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? '';
        if ($id === '') {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND target_user_id = ?");
        $stmt->execute([$id, $userId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // list
    $stmt = $pdo->prepare("SELECT id, type, payload, created_at, read_at FROM notifications WHERE target_user_id = ? ORDER BY created_at DESC LIMIT 100");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('API notifications error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
    exit;
}

