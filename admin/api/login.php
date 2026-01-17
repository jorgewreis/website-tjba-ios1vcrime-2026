<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$cpf   = $_POST['cpf'] ?? '';
$senha = $_POST['senha'] ?? '';
$csrf  = $_POST['csrf_token'] ?? null;

if ($cpf === '' || $senha === '') {
    echo json_encode(['success' => false, 'message' => 'CPF e senha são obrigatórios.']);
    exit;
}

if (!Security::verifyCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

session_regenerate_id(true);

$usuario = new Usuario();
$resultado = $usuario->autenticar($cpf, $senha);

if (($resultado['success'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'CPF ou senha inválidos.']);
    exit;
}

$_SESSION['id_usuario']   = $resultado['id_usuario'];
$_SESSION['user_profile'] = $resultado['perfil'];
$_SESSION['token']        = Security::generateSecureToken();

$usuario->atualizarToken(
    $resultado['id_usuario'],
    $_SESSION['token'],
    TOKEN_EXPIRATION_HOURS
);

echo json_encode(['success' => true]);
