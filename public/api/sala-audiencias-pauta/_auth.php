<?php
declare(strict_types=1);

$salaAudienciasPautaAuthBasePath = dirname(__DIR__, 3);
require_once $salaAudienciasPautaAuthBasePath . '/config/security.php';
require_once $salaAudienciasPautaAuthBasePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $salaAudienciasPautaAuthBasePath . '/app/access/AccessEvaluator.php';

/**
 * Exige sessão válida e permissão de edição da pauta antes de qualquer mutação.
 */
function salaAudienciasPautaRequireEditAccess(): string
{
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('salapautaedit', $perfis)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $usuarioId;
}
