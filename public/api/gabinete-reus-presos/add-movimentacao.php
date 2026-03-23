<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$parsed['user_cadastro'] = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

try {
    $service = new GabineteReusPresosService($connection);
    $inserted = $service->addMovimentacao($parsed);
    echo json_encode(['success' => true, 'inserted' => $inserted], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao registrar movimentação: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
