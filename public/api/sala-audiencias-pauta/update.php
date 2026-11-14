<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

ApiAccessGuard::requireAccess(['salapautaedit'], true);

$connection = Conection::getInstance()->getConnection();
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = trim((string) ($payload['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID da audiência é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $service = new SalaAudienciasPautaService($connection);
    $ok = $service->update($id, $payload, $usuarioId);
    echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar audiência: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

