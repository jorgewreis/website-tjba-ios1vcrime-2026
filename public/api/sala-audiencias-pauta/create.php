<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

$connection = Conection::getInstance()->getConnection();
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

try {
    $usuarioId = salaAudienciasPautaRequireEditAccess();
    $service = new SalaAudienciasPautaService($connection);
    $id = $service->create($payload, $usuarioId);
    http_response_code(201);
    echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar audiência: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

