<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
if (!is_file($basePath . '/app/helpers/Conection.php')) {
    $basePath = dirname(__DIR__, 4);
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}
$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'id do telefone é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $removed = $service->deleteTelefone($id);
    echo json_encode(['deleted' => $removed], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir telefone.'], JSON_UNESCAPED_UNICODE);
}
