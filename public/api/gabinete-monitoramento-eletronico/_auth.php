<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

function gmeJsonError(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
}

function gmeRequirePageAccess(string $page, bool $requireTrustedPost = false): string
{
    if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
        gmeJsonError('Requisição inválida.', 403);
        exit;
    }

    if (!Security::isAuthenticated()) {
        gmeJsonError('Sessão expirada.', 401);
        exit;
    }

    $userId = trim((string) Security::getUserId());
    if ($userId === '' || !AccessEvaluator::canAccessPage($page, UsuarioPerfisAutorizacao::perfisDoUsuario($userId))) {
        gmeJsonError('Acesso negado.', 403);
        exit;
    }

    return $userId;
}
