<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/PartesRepository.php';

$connection = Conection::getInstance()->getConnection();

$nome = trim((string) ($_GET['nome'] ?? ''));
$limit = isset($_GET['limit']) ? min(50, max(1, (int) $_GET['limit'])) : 20;

if ($nome === '' || strlen($nome) < 2) {
    echo json_encode(['pessoas' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new PartesRepository($connection);
    $filters = ['nome' => $nome];
    $rows = $repository->fetchList($filters, $limit, 0);
    $pessoas = [];
    foreach ($rows as $row) {
        $pessoas[] = [
            'id' => (string) ($row['id'] ?? ''),
            'nome_completo' => (string) ($row['nome_completo'] ?? ''),
            'cpf' => (string) ($row['cpf'] ?? ''),
        ];
    }
    echo json_encode(['pessoas' => $pessoas], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar.', 'pessoas' => []], JSON_UNESCAPED_UNICODE);
}
