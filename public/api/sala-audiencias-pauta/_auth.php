<?php
declare(strict_types=1);

$salaAudienciasPautaAuthBasePath = dirname(__DIR__, 3);
require_once $salaAudienciasPautaAuthBasePath . '/config/security.php';
require_once $salaAudienciasPautaAuthBasePath . '/app/helpers/BootstrapHelper.php';
require_once $salaAudienciasPautaAuthBasePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $salaAudienciasPautaAuthBasePath . '/app/access/AccessEvaluator.php';

if (!function_exists('sala_audiencias_pauta_api_error')) {
    function sala_audiencias_pauta_api_error(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('sala_audiencias_pauta_require_access')) {
    function sala_audiencias_pauta_require_access(string $page, bool $requireTrustedPost = false): string
    {
        if ($requireTrustedPost && !bootstrapRequireTrustedPostRequest()) {
            sala_audiencias_pauta_api_error(403, 'Requisição inválida.');
        }

        if (!Security::isAuthenticated()) {
            sala_audiencias_pauta_api_error(401, 'Sessão expirada. Faça login novamente.');
        }

        $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
        if ($usuarioId === '') {
            sala_audiencias_pauta_api_error(401, 'Sessão expirada. Faça login novamente.');
        }

        $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
        if (!AccessEvaluator::canAccessPage($page, $perfis)) {
            sala_audiencias_pauta_api_error(403, 'Acesso negado.');
        }

        return $usuarioId;
    }
}
