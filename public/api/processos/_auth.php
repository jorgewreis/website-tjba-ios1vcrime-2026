<?php
declare(strict_types=1);

$processosApiBasePath = dirname(__DIR__, 3);
require_once $processosApiBasePath . '/app/helpers/BootstrapHelper.php';
require_once $processosApiBasePath . '/config/security.php';
require_once $processosApiBasePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $processosApiBasePath . '/app/access/AccessEvaluator.php';

function processosApiRespondJson(array $payload, int $status): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param list<string> $pages
 */
function processosApiRequireAnyAccess(array $pages): string
{
    if (!Security::isAuthenticated()) {
        processosApiRespondJson(['error' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        processosApiRespondJson(['error' => 'Sessão expirada. Faça login novamente.'], 401);
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    foreach ($pages as $page) {
        if (AccessEvaluator::canAccessPage($page, $perfis)) {
            return $usuarioId;
        }
    }

    processosApiRespondJson(['error' => 'Acesso negado.'], 403);
}

function processosApiRequireAccess(string $page = 'consultaprocessosedit'): string
{
    return processosApiRequireAnyAccess([$page]);
}

function processosApiRequireTrustedPost(): void
{
    if (!bootstrapRequireTrustedPostRequest()) {
        processosApiRespondJson(['error' => 'Requisição inválida.'], 403);
    }
}
