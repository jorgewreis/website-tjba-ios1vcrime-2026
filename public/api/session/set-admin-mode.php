<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/helpers/EffectiveAccessProfiles.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = Security::getUserId();
if ($userId === null || trim((string) $userId) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sessão inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$parsed = json_decode($raw, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$modoAdmin = $parsed['modo_admin'] ?? null;
if (!is_bool($modoAdmin)) {
    $ma = strtolower(trim((string) ($parsed['modo_admin'] ?? '')));
    if (in_array($ma, ['1', 'true', 'sim', 'yes'], true)) {
        $modoAdmin = true;
    } elseif (in_array($ma, ['0', 'false', 'nao', 'não', 'no'], true)) {
        $modoAdmin = false;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Informe modo_admin (boolean).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$usuario = new Usuario();
$perfisCadastro = $usuario->listarPerfisUsuario(trim((string) $userId));
if (!is_array($perfisCadastro)) {
    $perfisCadastro = [];
}

if (!EffectiveAccessProfiles::canOfferAdminModeToggle($perfisCadastro)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Alternância de modo administrador não disponível para este usuário.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// modo_admin true = usar ADMIN nas autorizações; false = modo operacional (suprimir ADMIN)
EffectiveAccessProfiles::setAdminSuppressed(!$modoAdmin);
$_SESSION[EffectiveAccessProfiles::SESSION_ADMIN_MODE_EXPLICIT] = true;

$userIdTrim = trim((string) $userId);
echo json_encode([
    'success' => true,
    'modo_admin' => $modoAdmin,
    'admin_suprimido_sessao' => EffectiveAccessProfiles::isAdminSuppressed(),
    'perfis' => array_values(UsuarioPerfisAutorizacao::perfisDoUsuario($userIdTrim)),
], JSON_UNESCAPED_UNICODE);
