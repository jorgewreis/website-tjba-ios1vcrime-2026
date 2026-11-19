<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/services/PasswordResetService.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
}

$resetService = new PasswordResetService();
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($requestMethod === 'POST' && ($_POST['reset_action'] ?? '') === 'stage_token') {
    header('Content-Type: application/json; charset=UTF-8');
    $csrf = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
        http_response_code(419);
        echo json_encode(['success' => false]);
        exit;
    }

    $candidateToken = isset($_POST['token']) && is_string($_POST['token']) ? trim($_POST['token']) : '';
    if ($candidateToken === '' || $resetService->validateResetToken($candidateToken) === null) {
        http_response_code(422);
        echo json_encode(['success' => false]);
        exit;
    }

    $_SESSION['password_reset_form_token'] = $candidateToken;
    http_response_code(204);
    exit;
}

$iframeSrc = '/views/public/redefinir-senha-form.php';
$pageTitle  = 'Redefinir senha - Primeira Vara Crime de Ilhéus';
$needsResetFragmentBootstrap = true;
$resetBootstrapCsrf = Security::generateCSRFToken();

include __DIR__ . '/_layout_login.php';
