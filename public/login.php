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
$usuario = new Usuario();
try {
    $attempt = $rateLimiter->withLoginAttemptLock(
        $normalizedCpf,
        $clientIp,
        static function () use ($rateLimiter, $usuario, $cpf, $senha, $normalizedCpf, $clientIp): array {
            $limitStatus = $rateLimiter->canAttempt($normalizedCpf, $clientIp);
            if (($limitStatus['allowed'] ?? false) !== true) {
                return ['limit_status' => $limitStatus, 'result' => null];
            }

            $result = $usuario->autenticar($cpf, $senha);
            if (($result['success'] ?? false) !== true) {
                $limitStatus = $rateLimiter->registerFailure($normalizedCpf, $clientIp);
            } else {
                $rateLimiter->clear($normalizedCpf, $clientIp);
            }

            return ['limit_status' => $limitStatus, 'result' => $result];
        }
    );
} catch (Throwable $exception) {
    error_log('LoginRateLimiter: falha ao serializar tentativa ou registrar resultado: ' . get_class($exception));
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Login temporariamente indisponível. Tente novamente mais tarde.']);
    exit;
}

if (!$rateLimiter->isStorageAvailable()) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Login temporariamente indisponível. Tente novamente mais tarde.']);
    exit;
}

/** @var array{allowed:bool,message:?string,retryAfter:int} $limitStatus */
$limitStatus = $attempt['limit_status'];
/** @var array<string,mixed>|null $resultado */
$resultado = $attempt['result'];
if ($resultado === null) {
    echo json_encode([
        'success' => false,
        'message' => $limitStatus['message'] ?? 'Muitas tentativas de login. Tente novamente mais tarde.'
    ]);
    exit;
}

if (($resultado['success'] ?? false) !== true) {
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

$sessionToken = Security::generateSecureToken();
$perfisLogin = $resultado['perfis'] ?? [];
if (is_array($perfisLogin)) {
    EffectiveAccessProfiles::applyDefaultOperacionalIfCumulative($perfisLogin);
}

/* Persistir token somente se a senha ainda for a mesma validada no login. */
if (!$usuario->atualizarToken(
    $resultado['id_usuario'],
    $sessionToken,
    TOKEN_EXPIRATION_HOURS
)) {
    echo json_encode([
        'success' => false,
        'message' => 'CPF ou senha inválidos.'
    ]);
    exit;
}

session_regenerate_id(true);
Security::initializeAuthenticatedSession(
    $resultado['id_usuario'],
    $resultado['perfil'],
    $sessionToken,
    (string) ($resultado['nome'] ?? '')
);

$usuario->registrarUltimoAcesso($resultado['id_usuario']);


echo json_encode(['success' => true]);
exit;
