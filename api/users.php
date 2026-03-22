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
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id_usuario, nome, cpf, ativo FROM usuarios ORDER BY nome LIMIT 200");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // Placeholders para create/update/delete - exigir privilégios de admin
    $userProfile = Security::getUserProfile();
    if ($userProfile !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        exit;
    }

    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nome = trim($_POST['nome'] ?? '');
        $cpf = trim($_POST['cpf'] ?? '');
        $senha = trim($_POST['senha'] ?? '');
        if ($nome === '' || $cpf === '' || $senha === '') {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
            exit;
        }
        $senhaHash = Security::hashPassword($senha);
        $id = 'USR-' . strtoupper(bin2hex(random_bytes(13)));
        // Servidor: Tipo Interno, Status Pendente. Setor e perfis devem ser definidos/validados por admin ou diretora para ativar.
        $stmt = $pdo->prepare("INSERT INTO usuarios (id_usuario, nome, cpf, senha_hash, ativo, cadastro_ativo, created_at, updated_at) VALUES (?, ?, ?, ?, 0, 0, NOW(), NOW())");
        $stmt->execute([$id, $nome, $cpf, $senhaHash]);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação não suportada']);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('API users error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
    exit;
}

