<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

$usuarioId = salaAudienciasPautaRequireAccess('salapautaedit');
salaAudienciasPautaRequireTrustedPost();
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
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $ok = $service->deleteParte($idParte, $usuarioId);
    echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover parte: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

