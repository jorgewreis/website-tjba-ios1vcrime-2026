<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ClassesRepository.php';

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

    $codigo = ClassesRepository::normalizeCodigo($codigoRaw);
    if ($codigo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Código da classe é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $repository = new ClassesRepository(Conection::getInstance()->getConnection());
    $row = $repository->findByCodigo($codigo);

    if ($row === null) {
        echo json_encode(['classe' => null, 'codigo_normalizado' => $codigo], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'classe' => [
            'id' => (string) $row['id'],
            'codigo' => (string) ($row['codigo'] ?? $codigo),
            'nome' => (string) ($row['nome'] ?? ''),
        ],
        'codigo_normalizado' => $codigo,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar classe: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
