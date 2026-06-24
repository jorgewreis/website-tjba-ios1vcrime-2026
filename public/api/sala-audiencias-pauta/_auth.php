<?php
declare(strict_types=1);

$salaPautaApiBasePath = dirname(__DIR__, 3);
require_once $salaPautaApiBasePath . '/app/helpers/BootstrapHelper.php';
require_once $salaPautaApiBasePath . '/config/security.php';
require_once $salaPautaApiBasePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $salaPautaApiBasePath . '/app/access/AccessEvaluator.php';

function salaPautaApiRespondJson(array $payload, int $status): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param list<string> $pages
 */
function salaPautaApiRequireAnyAccess(array $pages): string
{
    if (!Security::isAuthenticated()) {
        salaPautaApiRespondJson(['error' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        salaPautaApiRespondJson(['error' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    foreach ($pages as $page) {
        if (AccessEvaluator::canAccessPage($page, $perfis)) {
            return $usuarioId;
        }
    }

    salaPautaApiRespondJson(['error' => 'Acesso negado.'], 403);
}

function salaPautaApiRequireAccess(string $page): string
{
    return salaPautaApiRequireAnyAccess([$page]);
}

function salaPautaApiRequireTrustedPost(): void
{
    if (!bootstrapRequireTrustedPostRequest()) {
        salaPautaApiRespondJson(['error' => 'Requisição inválida.'], 403);
    }
}
