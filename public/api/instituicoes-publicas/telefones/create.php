<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$instituicaoId = trim((string) ($parsed['instituicao_id'] ?? ''));
if ($instituicaoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'instituicao_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $newId = $service->addTelefone($instituicaoId, $parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar telefone.'], JSON_UNESCAPED_UNICODE);
}
