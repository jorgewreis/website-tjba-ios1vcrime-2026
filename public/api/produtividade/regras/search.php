<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_common.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = produtividadeCurrentUserId();

if ($userId === '') {
    produtividadeRespondError(401, 'Sessão expirada. Faça login novamente.');
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 10);

try {
    $rows = $service->searchRulesAtivas($userId, $query, $limit);
    echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    produtividadeRespondError(400, $e->getMessage());
} catch (Throwable $e) {
    produtividadeRespondError(500, 'Erro ao buscar regras: ' . $e->getMessage());
}

