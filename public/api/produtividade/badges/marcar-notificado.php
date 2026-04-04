<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_common.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$payload = produtividadeReadPayload();
$userId = produtividadeCurrentUserId();

if ($userId === '') {
    produtividadeRespondError(401, 'Sessão expirada. Faça login novamente.');
    exit;
}

$concedidoId = trim((string) ($payload['concedido_id'] ?? ''));
if ($concedidoId === '') {
    produtividadeRespondError(400, 'ID da concessão é obrigatório.');
    exit;
}

try {
    $ok = $service->marcarBadgeNotificado($userId, $concedidoId);
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao marcar badge como notificado: ' . $e->getMessage());
}
