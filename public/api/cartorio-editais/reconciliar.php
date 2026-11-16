<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['editaisedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/EditaisService.php';

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload) || !is_array($payload['edital_ids'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Lista de editais inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new EditaisService(Conection::getInstance()->getConnection());
    $result = $service->reconciliarEditais($payload['edital_ids']);
    echo json_encode(['success' => true, 'resultado' => $result], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Lista de editais inválida ou acima do limite permitido.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'lote_revertido' => true,
        'error' => 'Não foi possível atualizar os editais. Nenhuma alteração do lote foi mantida.',
        'resultado' => [
            'solicitados' => count(array_unique(array_filter($payload['edital_ids'], 'is_string'))),
            'editais_com_etapas_criadas' => 0,
            'etapas_criadas' => 0,
            'inalterados' => 0,
            'finalizados' => 0,
            'falhas' => 1,
        ],
    ], JSON_UNESCAPED_UNICODE);
}
