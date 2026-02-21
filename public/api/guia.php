<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/BootstrapHelper.php';
require_once __DIR__ . '/../../app/services/Guias.php';

header('Content-Type: application/json; charset=utf-8');

// Verifica autenticação
if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

$pdo = getDatabaseConnection();
$guiasService = new Guias($pdo);

try {
    if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $dados = $_POST;
        // sanitize inputs (apenas trim básico; validações adicionais necessárias)
        array_walk_recursive($dados, function (&$v) { $v = trim((string)$v); });

        $result = $guiasService->cadastrarGuia($dados);
        echo json_encode($result);
        exit;
    }

    // listagem padrão (GET)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 25;
    $filtros = [];
    if (!empty($_GET['status'])) $filtros['status'] = trim((string)$_GET['status']);
    if (!empty($_GET['processo'])) $filtros['processo'] = trim((string)$_GET['processo']);

    $list = $guiasService->listarGuias($filtros, $page, $perPage);
    echo json_encode(['success' => true, 'data' => $list]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    error_log('API guia error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
    exit;
}

