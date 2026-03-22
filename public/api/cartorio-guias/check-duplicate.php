<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GuiasService.php';

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_GET;
}

$numeroProcesso = trim((string) ($parsed['numero_processo'] ?? $parsed['processo'] ?? ''));
$denunciado = trim((string) ($parsed['denunciado'] ?? ''));
$tipo = trim((string) ($parsed['tipo'] ?? 'Guia - Tipo não informado'));

if ($numeroProcesso === '' || $denunciado === '') {
    echo json_encode(['exists' => false, 'error' => 'Número do processo e denunciado são obrigatórios para a verificação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new GuiasService($connection);
    $exists = $service->existsGuiaComMesmoProcessoDenunciadoTipo($numeroProcesso, $denunciado, $tipo);
    echo json_encode(['exists' => $exists], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['exists' => false, 'error' => 'Erro ao verificar duplicidade.'], JSON_UNESCAPED_UNICODE);
}
