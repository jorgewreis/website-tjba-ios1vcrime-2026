<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CeapaService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
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

$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CeapaService($connection);
    $registro = $service->find($id);
    if ($registro === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Registro CEAPA não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $perfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $isEstagiario = in_array('ESTAGIARIO', $perfis, true);
    $isCriador = trim((string) ($registro['usuario_cadastro_id'] ?? '')) === $userId;

    if ($isEstagiario && !$isCriador) {
        http_response_code(403);
        echo json_encode(['error' => 'Estagiário só pode excluir quando for o criador da tarefa.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $deleted = $service->delete($id);
    echo json_encode(['deleted' => $deleted], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir registro CEAPA: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
