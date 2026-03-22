<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';

$connection = Conection::getInstance()->getConnection();

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$parteId = trim((string) ($_GET['parte_id'] ?? ''));

try {
    $repository = new ProcessosPartesRepository($connection);
    if ($processoId !== '') {
        $rows = $repository->findByProcessoId($processoId);
    } elseif ($parteId !== '') {
        $rows = $repository->findByParteId($parteId);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Informe processo_id ou parte_id.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar vínculos.'], JSON_UNESCAPED_UNICODE);
}
