<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/InstituicoesSetoresRepository.php';

$connection = Conection::getInstance()->getConnection();

$instituicaoId = trim((string) ($_GET['instituicao_id'] ?? ''));

if ($instituicaoId === '') {
    echo json_encode(['setores' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new InstituicoesSetoresRepository($connection);
    $rows = $repository->findByInstituicaoId($instituicaoId);
    $setores = [];
    foreach ($rows as $row) {
        if (empty($row['ativo'])) {
            continue;
        }
        $setores[] = [
            'id' => (string) ($row['id'] ?? ''),
            'nome_setor' => (string) ($row['nome_setor'] ?? ''),
            'sigla' => (string) ($row['sigla'] ?? ''),
        ];
    }
    echo json_encode(['setores' => $setores], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar setores.', 'setores' => []], JSON_UNESCAPED_UNICODE);
}
