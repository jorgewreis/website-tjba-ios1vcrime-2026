<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/MedidasProtetivasService.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId === null || trim((string) $userId) === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
if (!AccessEvaluator::canAccessPage('medidasprotetivasedit', $userPerfis)) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para cancelar esta tarefa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da medida protetiva é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new MedidasProtetivasService($connection);
    $service->cancel($id);
    $__mir = true;
    if ($__mir) {
        require_once $basePath . '/app/helpers/CartorioProdutividade.php';
        CartorioProdutividade::mirrorEstornoSaldoSession($connection, $id, 'cartorio-medidas-protetivas', 'Medida protetiva cancelada.');
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao cancelar medida protetiva: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
