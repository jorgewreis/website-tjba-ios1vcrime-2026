<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';
require_once __DIR__ . '/_auth.php';

$usuarioCadastroId = cartorio_cartas_expedidas_require_json_access('cartasexpedidascreate');
$connection = Conection::getInstance()->getConnection();
$dataJudConfig = require $basePath . '/public/api/datajud/config.php';
$dataJudService = new DataJudService($dataJudConfig);

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

if ($usuarioCadastroId !== '') {
    $parsed['usuario_cadastro_id'] = $usuarioCadastroId;
}

function assertCnjFormattedValue(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $value)) {
        throw new \InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
}

try {
    assertCnjFormattedValue($parsed, 'numero_processo', 'Número do processo');

    $service = new CartasExpedidasService($connection, $dataJudService);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar carta expedida: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
