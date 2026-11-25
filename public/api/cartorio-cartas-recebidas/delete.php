<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-cartas-recebidas/delete.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['cartasrecebidasedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CartasRecebidasService.php';
require_once $basePath . '/app/helpers/DeleteAuthorization.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$userId = $_SESSION['id_usuario'] ?? null;
$podeExcluir = DeleteAuthorization::usuarioPodeExcluir(
    $userId !== null ? trim((string) $userId) : null
);

if (!$podeExcluir) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para excluir. Apenas admin, chefia, diretora ou magistrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador obrigatório para exclusão.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CartasRecebidasService($connection);
    $resultado = $service->delete($id);
    echo json_encode(is_array($resultado) ? $resultado : ['deleted' => (bool) $resultado], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir carta recebida: ' . ApiErrorReporter::genericMessage($exception, 'public/api/cartorio-cartas-recebidas/delete.php')], JSON_UNESCAPED_UNICODE);
}
