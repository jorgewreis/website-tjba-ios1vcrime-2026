<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/PessoasService.php';

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if (!AccessEvaluator::canAccessPage('consultapartes', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da pessoa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new PessoasService($connection);
    $pessoa = $service->find($id);
    if ($pessoa === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Pessoa não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($pessoa, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar pessoa: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
