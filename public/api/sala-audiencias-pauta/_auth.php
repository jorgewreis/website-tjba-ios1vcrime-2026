<?php
declare(strict_types=1);

$basePath = $basePath ?? dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

function salaAudienciasPautaJsonError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function salaAudienciasPautaRequireTrustedPost(): void
{
    if (!bootstrapRequireTrustedPostRequest()) {
        salaAudienciasPautaJsonError(403, 'Requisição inválida.');
    }
}

function salaAudienciasPautaRequireAccess(string $page): string
{
    if (!Security::isAuthenticated()) {
        salaAudienciasPautaJsonError(401, 'Sessão expirada. Faça login novamente.');
    }

    $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($userId === '') {
        salaAudienciasPautaJsonError(401, 'Sessão expirada. Faça login novamente.');
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    if (!AccessEvaluator::canAccessPage($page, $perfis)) {
        salaAudienciasPautaJsonError(403, 'Acesso negado.');
    }

    return $userId;
}
