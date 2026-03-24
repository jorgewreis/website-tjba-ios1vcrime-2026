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

$instituicaoId = trim((string) ($parsed['instituicao_id'] ?? ''));
if ($instituicaoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'instituicao_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hoje = date('Y-m-d');
$dataInclusao = trim((string) ($parsed['data_inclusao'] ?? ''));
if ($dataInclusao === '') {
    $dataInclusao = $hoje;
}
$dataInicio = trim((string) ($parsed['data_inicio'] ?? ''));
if ($dataInicio === '') {
    $dataInicio = $dataInclusao;
}

$row = [
    'instituicao_id' => $instituicaoId,
    'data_inclusao' => $dataInclusao,
    'data_inicio' => $dataInicio,
    'setor_id' => array_key_exists('setor_id', $parsed) ? (trim((string) $parsed['setor_id']) ?: null) : null,
    'profissao' => array_key_exists('profissao', $parsed) ? trim((string) $parsed['profissao']) : null,
    'cargo_funcao' => array_key_exists('cargo_funcao', $parsed) ? trim((string) $parsed['cargo_funcao']) : null,
    'data_fim' => array_key_exists('data_fim', $parsed) ? (trim((string) $parsed['data_fim']) ?: null) : null,
    'ativo' => isset($parsed['ativo']) ? (int) (bool) $parsed['ativo'] : 1,
    'observacoes' => array_key_exists('observacoes', $parsed) ? (trim((string) $parsed['observacoes']) ?: null) : null,
];

try {
    $service = new PessoasService($connection);
    $newId = $service->addVinculoInstituicao($parteId, $row);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao vincular instituição: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
