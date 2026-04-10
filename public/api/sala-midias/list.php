<?php
declare(strict_types=1);

require __DIR__ . '/_common.php';
require_once dirname(__DIR__, 3) . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once dirname(__DIR__, 3) . '/app/access/AccessEvaluator.php';

try {
    $usuarioId = salaMidiasCurrentUserId();
    if ($usuarioId === '') {
        salaMidiasJson(['error' => 'Sessão expirada.'], 401);
        exit;
    }
    if (!AccessEvaluator::canAccessPage('salamidias', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
        salaMidiasJson(['error' => 'Acesso negado.'], 403);
        exit;
    }

    $service = new SalaMidiasService(Conection::getInstance()->getConnection());
    $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 25;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $filters = [
        'search' => trim((string) ($_GET['search'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'data_audiencia' => trim((string) ($_GET['data_audiencia'] ?? '')),
    ];

    salaMidiasJson($service->list($filters, $limit, $offset));
} catch (InvalidArgumentException $e) {
    salaMidiasJson(['error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    salaMidiasJson(['error' => 'Erro ao listar mídias: ' . $e->getMessage()], 500);
}
