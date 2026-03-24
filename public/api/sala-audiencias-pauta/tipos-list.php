<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

try {
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $rows = $service->listTiposAtivos();
    echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar tipos de audiência: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

