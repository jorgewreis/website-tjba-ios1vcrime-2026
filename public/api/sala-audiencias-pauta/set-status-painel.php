<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuarioId = salaAudienciasPautaRequireAccess('salapauta');
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = trim((string) ($payload['id'] ?? ''));
$acao = trim((string) ($payload['acao'] ?? ''));
if ($id === '' || $acao === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Identificador da audiência e ação são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $service->setStatusPainel($id, $acao, $usuarioId);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao atualizar o status: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
