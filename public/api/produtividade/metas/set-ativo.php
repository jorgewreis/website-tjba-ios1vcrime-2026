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
if (!produtividadeIsGestao($connection, $userId)) {
    produtividadeRespondError(403, 'Acesso permitido apenas para perfis de gestão.');
    exit;
}

$id = trim((string) ($payload['id'] ?? ''));
if ($id === '') {
    produtividadeRespondError(400, 'ID da meta é obrigatório.');
    exit;
}
$ativo = ((int) ($payload['ativo'] ?? 0)) === 1;

try {
    $ok = $service->setMetaAtivo($userId, $id, $ativo);
    echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao atualizar status da meta: ' . $e->getMessage());
}

