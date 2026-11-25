<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/gabinete-reus-presos/get.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['reuspresos', 'reuspresossubstituto'], false);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$id = trim((string) ($_GET['id'] ?? ''));

try {
    if ($id === '') {
        throw new InvalidArgumentException('Identificador obrigatório.');
    }
    $service = new GabineteReusPresosService($connection, (($_GET['contexto'] ?? '') === 'substituto'));
    $data = $service->findById($id);
    if ($data === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Registro não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $data['tipos_movimentacao'] = GabineteReusPresosService::tiposMovimentacao();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/gabinete-reus-presos/get.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar.'], JSON_UNESCAPED_UNICODE);
}
