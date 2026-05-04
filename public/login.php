<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/BootstrapHelper.php';
require_once __DIR__ . '/../app/helpers/Conection.php';

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
require_once __DIR__ . '/../app/helpers/EffectiveAccessProfiles.php';
require_once __DIR__ . '/../app/helpers/AvatarInitialsPalette.php';
require_once __DIR__ . '/../app/services/LoginRateLimiter.php';

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

$normalizedCpf = Security::normalizeCPF($cpf);
$clientIp = Security::getClientIp();

$pdo = Conection::getInstance()->getConnection();
$rateLimiter = new LoginRateLimiter($pdo);
$limitStatus = $rateLimiter->canAttempt($normalizedCpf, $clientIp);

if (($limitStatus['allowed'] ?? false) !== true) {
    echo json_encode([
        'success' => false,
        'message' => $limitStatus['message'] ?? 'Muitas tentativas de login. Tente novamente mais tarde.'
    ]);
    exit;
}

$usuario = new Usuario();
$resultado = $usuario->autenticar($cpf, $senha);

if (($resultado['success'] ?? false) !== true) {
    $limitStatus = $rateLimiter->registerFailure($normalizedCpf, $clientIp);

    $message = !empty($resultado['inactive']) && !empty($resultado['message'])
        ? $resultado['message']
        : 'CPF ou senha inválidos.';

    if (($limitStatus['allowed'] ?? true) !== true && !empty($limitStatus['message'])) {
        $message = (string) $limitStatus['message'];
    }

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
$sessionToken = Security::generateSecureToken();
Security::initializeAuthenticatedSession(
    $resultado['id_usuario'],
    $resultado['perfil'],
    $sessionToken,
    (string) ($resultado['nome'] ?? '')
);

$perfisLogin = $resultado['perfis'] ?? [];
if (is_array($perfisLogin)) {
    EffectiveAccessProfiles::applyDefaultOperacionalIfCumulative($perfisLogin);
}

/* Persistir token */
$usuario->atualizarToken(
    $resultado['id_usuario'],
    $sessionToken,
    TOKEN_EXPIRATION_HOURS
);
$usuario->registrarUltimoAcesso($resultado['id_usuario']);

AvatarInitialsPalette::regenerateOnLogin();

$rateLimiter->clear($normalizedCpf, $clientIp);

echo json_encode(['success' => true]);
exit;
