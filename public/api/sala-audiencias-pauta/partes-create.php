<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$idPauta = trim((string) ($payload['id_pauta'] ?? ''));
if ($idPauta === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID da audiência é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $id = $service->addParte($idPauta, $payload, isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : null);
    http_response_code(201);
    echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar parte: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

