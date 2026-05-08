<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__, 3) . '/config/config.php';

$enabled = strtolower((string) envValue('MNI_TEST_PAGE_PUBLIC_ENABLED', '0'));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    respond(['error' => 'Consulta MNI de teste desabilitada neste ambiente.'], 404);
}

$config = require __DIR__ . '/config.php';
if (($config['wsdl'] ?? '') === '') {
    respond(['error' => 'Integração MNI não configurada (MNI_WSDL_URL ausente).'], 500);
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$numero = trim(preg_replace('/\D+/', '', $numero));
if ($numero === '' || strlen($numero) !== 20) {
    respond(['error' => 'Número do processo inválido. Forneça 20 dígitos sem pontuação.'], 400);
}

require_once dirname(__DIR__, 3) . '/app/services/MniProcessoService.php';

try {
    $service = new MniProcessoService($config);
    $data = $service->consultarPorNumeroCnj($numero);
    respond(['data' => $data], 200);
} catch (InvalidArgumentException $e) {
    respond(['error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    respond(['error' => $e->getMessage()], 502);
} catch (Throwable $e) {
    respond(['error' => 'Falha inesperada na consulta MNI.'], 500);
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
