<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do vínculo processo-parte é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['polo', 'prioridade_mulher', 'prioridade_crianca_adolescente', 'prioridade_idoso', 'prioridade_pessoa_deficiencia'];
$data = array_intersect_key($parsed, array_flip($allowed));
if ($data === []) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new ProcessosPartesRepository($connection);
    $repository->update($id, $data);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar vínculo: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
