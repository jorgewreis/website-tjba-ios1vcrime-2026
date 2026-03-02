<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';

$connection = Conection::getInstance()->getConnection();
$dataJudConfig = require $basePath . '/api/datajud/config.php';
$dataJudService = new DataJudService($dataJudConfig);

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$usuarioCadastroId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioCadastroId !== '') {
    $parsed['usuario_cadastro_id'] = $usuarioCadastroId;
}

try {
    $service = new CartasExpedidasService($connection, $dataJudService);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar carta expedida: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
