<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

salaPautaApiRequireAnyAccess(['salapautacreate', 'salapautaedit']);

$data = isset($_GET['data']) ? trim((string) $_GET['data']) : '';
$exceptId = isset($_GET['except_id']) ? trim((string) $_GET['except_id']) : '';

try {
    if ($data === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Informe a data (parâmetro data).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $except = $exceptId !== '' ? $exceptId : null;
    $available = $service->listHorariosDisponiveisCadastro($data, $except);
    echo json_encode(['available' => $available], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar horários: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
