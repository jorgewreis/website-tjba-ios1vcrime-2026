<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

/**
 * Enforces the same auth boundary as frame.php for Sala de Audiencias JSON APIs.
 */
function salaAudienciasPautaRequireJsonAccess(string $pageKey, bool $requireTrustedPost = true): string
{
    if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
        salaAudienciasPautaJsonError(403, 'Requisicao invalida.');
    }

    if (!Security::isAuthenticated()) {
        salaAudienciasPautaJsonError(401, 'Sessao expirada. Faca login novamente.');
    }

    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        salaAudienciasPautaJsonError(401, 'Sessao expirada. Faca login novamente.');
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage($pageKey, $perfis)) {
        salaAudienciasPautaJsonError(403, 'Acesso negado.');
    }

    return $usuarioId;
}

function salaAudienciasPautaJsonError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
