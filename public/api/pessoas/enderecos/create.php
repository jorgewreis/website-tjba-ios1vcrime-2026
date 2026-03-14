<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
if (!is_file($basePath . '/app/helpers/Conection.php')) {
    $basePath = dirname(__DIR__, 4);
}
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

$emptyToNull = static function ($v) {
    $s = trim((string) $v);
    return $s === '' ? null : $s;
};
$row = [
    'cep' => $emptyToNull($parsed['cep'] ?? ''),
    'logradouro' => $emptyToNull($parsed['logradouro'] ?? ''),
    'numero' => $emptyToNull($parsed['numero'] ?? ''),
    'complemento' => $emptyToNull($parsed['complemento'] ?? ''),
    'bairro' => $emptyToNull($parsed['bairro'] ?? ''),
    'cidade' => $emptyToNull($parsed['cidade'] ?? ''),
    'uf' => $emptyToNull($parsed['uf'] ?? ''),
    'principal' => isset($parsed['principal']) ? (int) (bool) $parsed['principal'] : 0,
];
if (array_key_exists('ativo', $parsed)) {
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
