<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/PessoasService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$parteId = trim((string) ($parsed['parte_id'] ?? ''));
if ($parteId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'parte_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$numero = preg_replace('/\D/', '', trim((string) ($parsed['numero'] ?? '')));
if ($numero === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Número do telefone é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = array_key_exists('tipo', $parsed) ? trim((string) $parsed['tipo']) : '';
$tipo = ($tipo === '' || $tipo === null) ? null : $tipo;

try {
    $service = new PessoasService($connection);
    $newId = $service->addTelefone($parteId, [
        'tipo' => $tipo,
        'numero' => $numero,
    ]);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar telefone: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
