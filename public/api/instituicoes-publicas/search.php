<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/InstituicoesPublicasRepository.php';

$connection = Conection::getInstance()->getConnection();

$nome = trim((string) ($_GET['nome'] ?? ''));
$limit = isset($_GET['limit']) ? min(50, max(1, (int) $_GET['limit'])) : 20;

if ($nome === '' || strlen($nome) < 2) {
    echo json_encode(['instituicoes' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new InstituicoesPublicasRepository($connection);
    $rows = $repository->fetchList(['nome' => $nome], $limit, 0);
    $instituicoes = [];
    foreach ($rows as $row) {
        $instituicoes[] = [
            'id' => (string) ($row['id'] ?? ''),
            'nome' => (string) ($row['nome'] ?? ''),
            'nome_fantasia' => (string) ($row['nome_fantasia'] ?? ''),
        ];
    }
    echo json_encode(['instituicoes' => $instituicoes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar.', 'instituicoes' => []], JSON_UNESCAPED_UNICODE);
}
