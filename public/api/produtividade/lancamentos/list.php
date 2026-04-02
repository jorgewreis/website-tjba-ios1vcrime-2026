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

$limit = (int) ($payload['limit'] ?? 25);
$offset = (int) ($payload['offset'] ?? 0);
$filters = [
    'usuario_id' => trim((string) ($payload['usuario_id'] ?? '')),
    'status' => trim((string) ($payload['status'] ?? '')),
    'tipo_lancamento' => trim((string) ($payload['tipo_lancamento'] ?? '')),
    'data_inicio' => trim((string) ($payload['data_inicio'] ?? '')),
    'data_fim' => trim((string) ($payload['data_fim'] ?? '')),
];

try {
    $result = $service->listLancamentos($userId, $filters, $limit, $offset);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao listar lançamentos: ' . $e->getMessage());
}

