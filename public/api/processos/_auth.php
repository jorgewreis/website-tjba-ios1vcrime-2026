<?php
declare(strict_types=1);

$processosApiBasePath = dirname(__DIR__, 3);
require_once $processosApiBasePath . '/app/helpers/BootstrapHelper.php';
require_once $processosApiBasePath . '/config/security.php';
require_once $processosApiBasePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $processosApiBasePath . '/app/access/AccessEvaluator.php';

function processos_api_json_error(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function processos_api_require_access(string $page = 'consultaprocessosedit', bool $requireTrustedPost = false): string
{
    if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
        processos_api_json_error(403, 'Requisição inválida.');
    }

    if (!Security::isAuthenticated()) {
        processos_api_json_error(401, 'Sessão expirada. Faça login novamente.');
    }

    $usuarioId = trim((string) (Security::getUserId() ?? ($_SESSION['id_usuario'] ?? '')));
    if ($usuarioId === '') {
        processos_api_json_error(401, 'Sessão expirada. Faça login novamente.');
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage($page, $perfis)) {
        processos_api_json_error(403, 'Acesso negado.');
    }

    return $usuarioId;
}
