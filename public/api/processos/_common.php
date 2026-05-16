<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

function processos_api_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function processos_api_require_access(string $page = 'consultaprocessosedit'): string
{
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        processos_api_json(['error' => 'Sessão expirada. Faça login novamente.'], 401);
        exit;
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage($page, $perfis)) {
        processos_api_json(['error' => 'Acesso negado.'], 403);
        exit;
    }

    return $usuarioId;
}

function processos_api_require_trusted_post(): void
{
    if (!bootstrapRequireTrustedPostRequest()) {
        processos_api_json(['error' => 'Requisição inválida.'], 403);
        exit;
    }
}
