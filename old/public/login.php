<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

if (Security::isAuthenticated()) {
    header('Location: /home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../old/bd/Usuario.php';

$cpf   = $_POST['cpf'] ?? '';
$senha = $_POST['senha'] ?? '';
$csrf  = $_POST['csrf_token'] ?? null;

if ($cpf === '' || $senha === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Preencha todos os campos.'
    ]);
    exit;
}

if (!Security::verifyCSRFToken($csrf)) {
    echo json_encode([
        'success' => false,
        'message' => 'Sessão inválida. Recarregue a página.'
    ]);
    exit;
}

$usuario = new Usuario();
$resultado = $usuario->autenticar($cpf, $senha);

if (($resultado['success'] ?? false) !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'CPF ou senha inválidos.'
    ]);
    exit;
}

/* =====================
   Sessão (backend manda)
   ===================== */

session_regenerate_id(true);

$_SESSION['id_usuario']   = $resultado['id_usuario'];
$_SESSION['user_profile'] = $resultado['perfil'];
$_SESSION['token']        = Security::generateSecureToken();

/* Persistir token */
$usuario->atualizarToken(
    $resultado['id_usuario'],
    $_SESSION['token'],
    TOKEN_EXPIRATION_HOURS
);

echo json_encode(['success' => true]);
exit;
