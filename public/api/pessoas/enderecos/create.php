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

$row = [
    'cep' => array_key_exists('cep', $parsed) ? trim((string) $parsed['cep']) : null,
    'logradouro' => array_key_exists('logradouro', $parsed) ? trim((string) $parsed['logradouro']) : null,
    'numero' => array_key_exists('numero', $parsed) ? trim((string) $parsed['numero']) : null,
    'complemento' => array_key_exists('complemento', $parsed) ? trim((string) $parsed['complemento']) : null,
    'bairro' => array_key_exists('bairro', $parsed) ? trim((string) $parsed['bairro']) : null,
    'cidade' => array_key_exists('cidade', $parsed) ? trim((string) $parsed['cidade']) : null,
    'uf' => array_key_exists('uf', $parsed) ? trim((string) $parsed['uf']) : null,
    'principal' => isset($parsed['principal']) ? (int) (bool) $parsed['principal'] : 0,
];
if (isset($parsed['ativo'])) {
    $row['ativo'] = (int) (bool) $parsed['ativo'];
}

try {
    $service = new PessoasService($connection);
    $newId = $service->addEndereco($parteId, $row);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar endereço: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
