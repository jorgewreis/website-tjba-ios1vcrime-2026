<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 4);
require_once $basePath . '/config/config.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';

header('Content-Type: application/json; charset=UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$userId = $_SESSION['id_usuario'] ?? null;
$userPerfis = [];
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeExcluir = count(array_intersect($userPerfis, ['ADMIN', 'CHEFIA'])) > 0;
if (!$podeExcluir) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para excluir. Apenas admin ou chefia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $basePath . '/app/services/FeriadosService.php';

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));

if ($id === '') {
    $payload = file_get_contents('php://input') ?: '';
    $parsed = json_decode($payload, true);
    if (is_array($parsed) && isset($parsed['id'])) {
        $id = trim((string) $parsed['id']);
    }
}

if ($id === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Identificador obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new FeriadosService($connection);
    $result = $service->delete($id);
    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir feriado: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
