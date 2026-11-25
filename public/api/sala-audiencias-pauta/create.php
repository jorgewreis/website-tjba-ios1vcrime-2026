<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-pauta/create.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

ApiAccessGuard::requireAccess(['salapautacreate'], true);

$connection = Conection::getInstance()->getConnection();
$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $service = new SalaAudienciasPautaService($connection);
    $id = $service->create($payload, $usuarioId);
    http_response_code(201);
    echo json_encode(['id' => $id], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (SalaAudienciasPautaSequenceLockException $e) {
    http_response_code(503);
    header('Retry-After: 5');
    echo json_encode(['error' => 'O sequencial anual da pauta está ocupado. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar audiência: ' . ApiErrorReporter::genericMessage($e, 'public/api/sala-audiencias-pauta/create.php')], JSON_UNESCAPED_UNICODE);
}

