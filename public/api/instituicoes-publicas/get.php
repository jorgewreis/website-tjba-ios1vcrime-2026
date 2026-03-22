<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

$connection = Conection::getInstance()->getConnection();

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da instituição é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $instituicao = $service->find($id);
    if ($instituicao === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Instituição não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($instituicao, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar instituição: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
