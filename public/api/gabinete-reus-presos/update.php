<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/gabinete-reus-presos/update.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['reuspresosedit'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
$nome = trim((string) ($parsed['nome'] ?? ''));

try {
    $service = new GabineteReusPresosService($connection, (($_GET['contexto'] ?? '') === 'substituto'));
    $service->updateNome($id, $nome);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar.'], JSON_UNESCAPED_UNICODE);
}
