<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

salaPautaApiRequireTrustedPost();
salaPautaApiRequireAccess('salapautaedit');

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

try {
    $service = new SalaAudienciasPautaService($connection);
    $service->addAlocadoUsuario($id, $usuarioAlvo);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao alocar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
