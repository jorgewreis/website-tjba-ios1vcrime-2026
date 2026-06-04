<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$idParte = trim((string) ($payload['id_parte'] ?? ''));
if ($idParte === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID da parte é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    $perfils = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('salapautaedit', $perfils)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $ok = $service->deleteParte($idParte, $usuarioId);
    echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover parte: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

