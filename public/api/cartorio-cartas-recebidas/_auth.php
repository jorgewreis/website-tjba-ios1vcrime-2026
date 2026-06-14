<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

if (!function_exists('cartorioCartasRecebidasRequireJsonAccess')) {
    function cartorioCartasRecebidasRequireJsonAccess(string $page): string
    {
        if (!bootstrapRequireTrustedPostRequest()) {
            http_response_code(403);
            echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!Security::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
        if ($userId === '') {
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
        if (!AccessEvaluator::canAccessPage($page, $perfis)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        return $userId;
    }
}
