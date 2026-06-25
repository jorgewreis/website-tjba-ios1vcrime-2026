<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/security.php';
require_once dirname(__DIR__, 3) . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once dirname(__DIR__, 3) . '/app/access/AccessEvaluator.php';

/**
 * Helper JSON para endpoints da pauta de audiências.
 *
 * @param array<string, mixed> $extra
 */
function salaPautaJsonError(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge($extra, ['error' => $message]), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Exige sessão autenticada, autorização em pelo menos uma página interna e,
 * quando solicitado, POST de mesma origem.
 *
 * @param string|list<string> $pages
 * @param array<string, mixed> $errorExtra
 */
function salaPautaRequireAccess($pages, bool $requireTrustedPost = false, array $errorExtra = []): string
{
    if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
        salaPautaJsonError(403, 'Requisição inválida.', $errorExtra);
    }

    if (!Security::isAuthenticated()) {
        salaPautaJsonError(401, 'Sessão expirada. Faça login novamente.', $errorExtra);
    }

    $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($userId === '') {
        salaPautaJsonError(401, 'Sessão expirada. Faça login novamente.', $errorExtra);
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    $pages = is_array($pages) ? $pages : [$pages];
    foreach ($pages as $page) {
        if (AccessEvaluator::canAccessPage((string) $page, $perfis)) {
            return $userId;
        }
    }

    salaPautaJsonError(403, 'Acesso negado.', $errorExtra);
}
