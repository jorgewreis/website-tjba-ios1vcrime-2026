<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-pauta/alocar.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

ApiAccessGuard::requireAccess(['salapautaedit'], true);

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$parsed = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
$usuarioAlvo = trim((string) ($parsed['usuario_id'] ?? ''));

if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID da audiência é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$actorId = trim((string) ($_SESSION['id_usuario'] ?? ''));
if ($actorId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new SalaAudienciasPautaService($connection);
    $service->addAlocadoUsuario($id, $usuarioAlvo);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao alocar: ' . ApiErrorReporter::genericMessage($e, 'public/api/sala-audiencias-pauta/alocar.php')], JSON_UNESCAPED_UNICODE);
}
