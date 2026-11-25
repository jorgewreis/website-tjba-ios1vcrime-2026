<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-pauta/occupied-slots.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

ApiAccessGuard::requireAccess(['salapautaedit'], false);

$data = isset($_GET['data']) ? trim((string) $_GET['data']) : '';
$exceptId = isset($_GET['except_id']) ? trim((string) $_GET['except_id']) : '';

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
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
    echo json_encode(['error' => 'Erro ao listar horários: ' . ApiErrorReporter::genericMessage($e, 'public/api/sala-audiencias-pauta/occupied-slots.php')], JSON_UNESCAPED_UNICODE);
}
