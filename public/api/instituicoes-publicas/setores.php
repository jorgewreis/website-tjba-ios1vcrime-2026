<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/instituicoes-publicas/setores.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['instituicoespublicas', 'partesinstituicoescreate']);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/InstituicoesSetoresRepository.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('instituicoes-publicas/setores.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    ApiErrorReporter::logUnexpected($e, 'public/api/instituicoes-publicas/setores.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar setores.', 'setores' => []], JSON_UNESCAPED_UNICODE);
}
