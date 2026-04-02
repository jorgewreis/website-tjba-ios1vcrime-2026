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

$id = trim((string) ($payload['id'] ?? ''));
$motivo = trim((string) ($payload['motivo'] ?? ''));
if ($id === '') {
    produtividadeRespondError(400, 'ID do lançamento é obrigatório.');
    exit;
}

try {
    $ok = $service->anularLancamentoManual($userId, $id, $motivo);
    echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao anular lançamento: ' . $e->getMessage());
}

