<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pessoas/get.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultapartes'], false);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/PessoasService.php';
require_once $basePath . '/app/helpers/InstituicoesConsultaPolicy.php';

$connection = Conection::getInstance()->getConnection();

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da pessoa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new PessoasService($connection);
    $onlyActive = InstituicoesConsultaPolicy::onlyActiveForUser((string) ($_SESSION['id_usuario'] ?? ''));
    $pessoa = $service->find($id, $onlyActive);
    if ($pessoa === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Pessoa não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($pessoa, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('API error (public/api/pessoas/get.php): ' . ApiErrorReporter::genericMessage($exception, 'public/api/pessoas/get.php'));
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar pessoa.'], JSON_UNESCAPED_UNICODE);
}
