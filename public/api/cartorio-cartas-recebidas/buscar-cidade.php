<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-cartas-recebidas/buscar-cidade.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['cartasrecebidas'], false);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/ComarcasRepository.php';

$connection = Conection::getInstance()->getConnection();
$repo = new ComarcasRepository($connection);

$cidade = trim((string) ($_GET['cidade'] ?? $_POST['cidade'] ?? ''));

if ($cidade === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Informe o parâmetro cidade.'], JSON_UNESCAPED_UNICODE);
    return;
}

$uf = strtoupper(trim((string) ($_GET['uf'] ?? '')));
if ($uf !== '' && !preg_match('/^[A-Z]{2}$/', $uf)) {
    http_response_code(400);
    echo json_encode(['error' => 'UF inválida.'], JSON_UNESCAPED_UNICODE);
    return;
}
$row = $repo->findFirstByNomeOuCidade($cidade, $uf);

if ($row === null) {
    echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
    return;
}

echo json_encode([
    'found' => true,
    'uf' => (string) ($row['uf'] ?? ''),
    'nome' => (string) ($row['nome'] ?? ''),
    'cidade' => (string) ($row['cidade'] ?? $row['nome'] ?? ''),
    'status_cidade' => (string) ($row['status_cidade'] ?? ''),
    'id' => (string) ($row['id'] ?? ''),
], JSON_UNESCAPED_UNICODE);
