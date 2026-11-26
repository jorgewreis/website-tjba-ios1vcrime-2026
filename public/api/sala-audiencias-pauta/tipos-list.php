<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-pauta/tipos-list.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada ou não autenticada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

try {
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $rows = $service->listTiposAtivos();
    echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar tipos de audiência: ' . ApiErrorReporter::genericMessage($e, 'public/api/sala-audiencias-pauta/tipos-list.php')], JSON_UNESCAPED_UNICODE);
}

