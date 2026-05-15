<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/AssuntosRepository.php';

$codigoRaw = isset($_GET['codigo']) ? (string) $_GET['codigo'] : '';
if ($codigoRaw === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (is_array($payload)) {
        $codigoRaw = (string) ($payload['codigo'] ?? '');
    }
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $codigo = AssuntosRepository::normalizeCodigo($codigoRaw);
    if ($codigo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Código do assunto é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $repository = new AssuntosRepository(Conection::getInstance()->getConnection());
    $row = $repository->findByCodigo($codigo);

    if ($row === null) {
        echo json_encode(['assunto' => null, 'codigo_normalizado' => $codigo], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'assunto' => [
            'id' => (string) $row['id'],
            'codigo' => (string) ($row['codigo'] ?? $codigo),
            'descricao' => (string) ($row['descricao'] ?? ''),
        ],
        'codigo_normalizado' => $codigo,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar assunto: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
