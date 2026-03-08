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
    $parsed = $_POST;
}

$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioId !== '') {
    $parsed['usuario_id'] = $usuarioId;
}

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador obrigatório para atualização.'], JSON_UNESCAPED_UNICODE);
    exit;
}

unset($parsed['id']);
$tinhaDataFatoOuDiasMulta = isset($parsed['data_fato']) || isset($parsed['dias_multa']);

try {
    $service = new GuiasService($connection);
    $updated = $service->update($id, $parsed);
    $body = ['success' => $updated, 'id' => $id];
    if ($tinhaDataFatoOuDiasMulta) {
        $guia = $service->find($id);
        if ($guia !== null && isset($guia['valor_multa']) && $guia['valor_multa'] !== '' && $guia['valor_multa'] !== null) {
            $body['valor_multa_calculado'] = 'R$ ' . number_format((float) $guia['valor_multa'], 2, ',', '.');
        }
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar guia: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
