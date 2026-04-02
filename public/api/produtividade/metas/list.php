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

$limit = (int) ($payload['limit'] ?? 25);
$offset = (int) ($payload['offset'] ?? 0);
$filters = [
    'q' => trim((string) ($payload['q'] ?? '')),
    'ativo' => $payload['ativo'] ?? '',
    'tipo_alvo' => trim((string) ($payload['tipo_alvo'] ?? '')),
    'periodo_tipo' => trim((string) ($payload['periodo_tipo'] ?? '')),
];

try {
    $result = $service->listMetas($userId, $filters, $limit, $offset);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao listar metas: ' . $e->getMessage());
}

