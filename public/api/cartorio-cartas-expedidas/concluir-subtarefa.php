<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['cartasexpedidasedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CartorioSubtarefaUsuarioSessao.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';


$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$subtarefaId = trim((string) ($parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? $_GET['subtarefa_id'] ?? ''));


if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa Ã© obrigatÃ³rio.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $dadosEspecificos = $parsed['dados_especificos'] ?? null;
    if (is_array($dadosEspecificos)) $dadosEspecificos = json_encode($dadosEspecificos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($dadosEspecificos !== null && !is_string($dadosEspecificos)) throw new InvalidArgumentException('Dados da subtarefa inválidos.');
    $service = new CartasExpedidasService($connection);
    $resultado = $service->concluirSubtarefa($subtarefaId, $dadosEspecificos);
    echo json_encode(array_merge(['success' => true], is_array($resultado) ? $resultado : []), JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    error_log('Cartas expedidas: falha ao concluir subtarefa (' . get_class($exception) . ').');
    echo json_encode(['error' => 'NÃ£o foi possÃ­vel confirmar a conclusÃ£o. Atualize a pÃ¡gina antes de tentar novamente.'], JSON_UNESCAPED_UNICODE);
}
