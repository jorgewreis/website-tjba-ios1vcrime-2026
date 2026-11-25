<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/instituicoes-publicas/search.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['instituicoespublicas']);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/InstituicoesPublicasRepository.php';
require_once $basePath . '/app/helpers/InstituicoesConsultaPolicy.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('instituicoes-publicas/search.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$nome = trim((string) ($_GET['nome'] ?? ''));
$limit = isset($_GET['limit']) ? min(50, max(1, (int) $_GET['limit'])) : 20;

if ($nome === '' || strlen($nome) < 2) {
    echo json_encode(['instituicoes' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $repository = new InstituicoesPublicasRepository($connection);
    $filters = ['nome' => $nome];
    if (InstituicoesConsultaPolicy::onlyActiveForUser((string) ($_SESSION['id_usuario'] ?? ''))) $filters['ativo'] = 1;
    $rows = $repository->fetchList($filters, $limit, 0);
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
    ApiErrorReporter::logUnexpected($e, 'public/api/instituicoes-publicas/search.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar.', 'instituicoes' => []], JSON_UNESCAPED_UNICODE);
}
