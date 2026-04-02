<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = produtividadeCurrentUserId();

if ($userId === '') {
    produtividadeRespondError(401, 'Sessão expirada. Faça login novamente.');
    exit;
}

$payload = $_GET + produtividadeReadPayload();
$limit = (int) ($payload['limit'] ?? 200);
$filters = [
    'usuario_id' => trim((string) ($payload['usuario_id'] ?? '')),
    'status' => trim((string) ($payload['status'] ?? 'ativo')),
    'tipo_lancamento' => trim((string) ($payload['tipo_lancamento'] ?? '')),
    'data_inicio' => trim((string) ($payload['data_inicio'] ?? '')),
    'data_fim' => trim((string) ($payload['data_fim'] ?? '')),
    'setor_codigo_area' => trim((string) ($payload['setor_codigo_area'] ?? '')),
];

try {
    $result = $service->listRanking($userId, $filters, $limit);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao carregar ranking: ' . $e->getMessage());
}

