<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
if (!is_file($basePath . '/app/helpers/Conection.php')) {
    $basePath = dirname(__DIR__, 4);
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/PessoasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if (!AccessEvaluator::canAccessPage('consultapartesedit', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$parteId = trim((string) ($parsed['parte_id'] ?? ''));
if ($parteId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'parte_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipo = trim((string) ($parsed['tipo'] ?? ''));
$numero = trim((string) ($parsed['numero'] ?? ''));
if ($tipo === '' || $numero === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo e número do documento são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new PessoasService($connection);
    $newId = $service->addDocumento($parteId, [
        'tipo' => $tipo,
        'numero' => $numero,
    ]);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar documento: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
