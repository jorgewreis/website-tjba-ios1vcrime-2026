<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

function cartasExpedidasApiFail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @return array{user_id: string, perfis: list<string>}
 */
function cartasExpedidasApiRequireAccess(string $page = 'cartasexpedidas', bool $requireTrustedPost = false): array
{
    if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
        cartasExpedidasApiFail(403, 'Requisição inválida.');
    }

    if (!Security::isAuthenticated()) {
        cartasExpedidasApiFail(401, 'Usuário não autenticado.');
    }

    $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($userId === '') {
        cartasExpedidasApiFail(401, 'Usuário não autenticado.');
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    if (!AccessEvaluator::canAccessPage($page, $perfis)) {
        cartasExpedidasApiFail(403, 'Seu perfil não tem permissão para acessar Cartas Expedidas.');
    }

    return [
        'user_id' => $userId,
        'perfis' => array_values($perfis),
    ];
}
