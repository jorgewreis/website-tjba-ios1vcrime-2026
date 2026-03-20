<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CartasRecebidasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

/** Apenas admin, diretora ou magistrada podem excluir tarefa e subtarefas. */
$perfisPermitidosExcluir = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluir = count(array_intersect($userPerfis, $perfisPermitidosExcluir)) > 0;

if (!$podeExcluir) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para excluir. Apenas admin, diretora ou magistrada.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => 'Erro ao excluir carta recebida: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
