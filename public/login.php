<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/BootstrapHelper.php';

if (Security::isAuthenticated()) {
    header('Location: /home.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../app/models/Usuario.php';
require_once __DIR__ . '/../app/helpers/AvatarInitialsPalette.php';

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

// === Rate limiting: evita brute force baseado em sessão ===
$maxAttempts = 5;
$lockWindowSeconds = 15 * 60; // 15 minutos
$attempts = $_SESSION['login_attempts'] ?? [];
$now = time();

// Remove tentativas antigas
$attempts = array_filter($attempts, function ($ts) use ($now, $lockWindowSeconds) {
    return ($now - $ts) <= $lockWindowSeconds;
});

if (count($attempts) >= $maxAttempts) {
    echo json_encode([
        'success' => false,
        'message' => 'Muitas tentativas de login. Tente novamente mais tarde.'
    ]);
    exit;
}

$usuario = new Usuario();
$resultado = $usuario->autenticar($cpf, $senha);

if (($resultado['success'] ?? false) !== true) {
    // registra tentativa falha
    $attempts[] = $now;
    $_SESSION['login_attempts'] = $attempts;

    $message = !empty($resultado['inactive']) && !empty($resultado['message'])
        ? $resultado['message']
        : 'CPF ou senha inválidos.';
    echo json_encode([
        'success' => false,
        'message' => $message
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
$usuario->registrarUltimoAcesso($resultado['id_usuario']);

AvatarInitialsPalette::regenerateOnLogin();

// login bem-sucedido: limpa registros de tentativa
unset($_SESSION['login_attempts']);

echo json_encode(['success' => true]);
exit;
