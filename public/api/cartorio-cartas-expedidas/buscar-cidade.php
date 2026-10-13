<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/CentralMandadosCcm.php';
require_once $basePath . '/app/repositories/ComarcasRepository.php';

$cidade = trim((string) ($_GET['cidade'] ?? $_POST['cidade'] ?? ''));
$uf = strtoupper(trim((string) ($_GET['uf'] ?? $_POST['uf'] ?? '')));

if ($cidade === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Informe o parâmetro cidade.'], JSON_UNESCAPED_UNICODE);
    return;
}

if (CentralMandadosCcm::contains($cidade, $uf)) {
    echo json_encode([
        'found' => true,
        'uf' => 'BA',
        'nome' => $cidade,
        'cidade' => $cidade,
        'status_cidade' => 'CCM',
        'is_ccm' => true,
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$connection = Conection::getInstance()->getConnection();
$repo = new ComarcasRepository($connection);

$row = $repo->findFirstByNomeOuCidade($cidade);
$isCcm = strtoupper(trim((string) ($row['status_cidade'] ?? ''))) === 'CCM';

if ($row === null) {
    echo json_encode([
        'found' => $isCcm,
        'uf' => $isCcm ? $uf : '',
        'nome' => $isCcm ? $cidade : '',
        'cidade' => $isCcm ? $cidade : '',
        'status_cidade' => $isCcm ? 'CCM' : '',
        'is_ccm' => $isCcm,
    ], JSON_UNESCAPED_UNICODE);
    return;
}

echo json_encode([
    'found' => true,
    'uf' => (string) ($row['uf'] ?? ''),
    'nome' => (string) ($row['nome'] ?? ''),
    'cidade' => (string) ($row['cidade'] ?? $row['nome'] ?? ''),
    'status_cidade' => $isCcm ? 'CCM' : (string) ($row['status_cidade'] ?? ''),
    'is_ccm' => $isCcm,
    'id' => (string) ($row['id'] ?? ''),
], JSON_UNESCAPED_UNICODE);
