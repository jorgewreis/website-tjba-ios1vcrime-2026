<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$perfisPermitidosCancelar = ['ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'];
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && trim((string) $userId) !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}
$podeCancelar = count(array_intersect($userPerfis, $perfisPermitidosCancelar)) > 0;

if (!$podeCancelar) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para cancelar.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => 'Identificador obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);
    $updated = $service->cancel($id);
    echo json_encode(['success' => $updated], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao cancelar: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
