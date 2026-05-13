<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteReusPresosRevisaoEmailService.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

try {
    $service = new GabineteReusPresosRevisaoEmailService($connection);
    $result = $service->sendResumo();
    if (!$result['success']) {
        $status = str_contains(strtolower((string) $result['message']), 'destinatário') ? 422 : 500;
        http_response_code($status);
    }

    echo json_encode([
        'success' => (bool) $result['success'],
        'sent' => (bool) $result['sent'],
        'count' => (int) $result['count'],
        'message' => (string) $result['message'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao enviar resumo: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
