<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$idPauta = trim((string) ($payload['id_pauta'] ?? ''));
$prioridade = trim((string) ($payload['prioridade'] ?? ''));
if ($idPauta === '' || $prioridade === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Audiência e prioridade são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $service->addPrioridadeCatalogoAudiencia($idPauta, $prioridade, $usuarioId);
    $state = $service->listPrioridadesEdicaoAudiencia($idPauta);
    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar prioridade: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
