<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/mni/consultar-processo.php');
/**
 * Endpoint HTTP fino que delega para MniService (que, por sua vez, encapsula MniClient).
 *
 * Entrada (POST JSON ou GET):
 *   { "numero": "0000000-00.0000.8.05.0103" }
 *
 * Saída:
 *   200 → { data: <shape>, meta: { elapsed_ms, version, endpoint_host } }
 *   4xx/5xx → { error: <msg>, kind: <kind> }
 *
 * Política:
 *   - Sessão obrigatória ($_SESSION['id_usuario']).
 *   - Não invalida fluxo do DataJud — é endpoint independente, opt-in.
 *   - Trunca movimentos para evitar payload gigante.
 */

header('Content-Type: application/json; charset=UTF-8');

// REI-81: a consulta está suspensa até existir autorização por processo e sigilo.
// Esta barreira precede a leitura da configuração e qualquer chamada ao tribunal.
http_response_code(503);
header('Cache-Control: no-store');
echo json_encode([
    'error' => 'Consulta MNI temporariamente indisponível.',
    'kind' => 'unavailable',
], JSON_UNESCAPED_UNICODE);
exit;

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/services/MniService.php';

$config = require __DIR__ . '/config.php';

$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.', 'kind' => 'auth'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawNumero = '';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (is_array($payload) && isset($payload['numero'])) {
        $rawNumero = (string) $payload['numero'];
    }
}
if ($rawNumero === '' && isset($_GET['numero'])) {
    $rawNumero = (string) $_GET['numero'];
}

try {
    $digits = CnjValidator::digits20OrThrow($rawNumero);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage(), 'kind' => 'config'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new MniService();
    $result = $service->consultar($digits, $config['request_defaults'] ?? []);

    if (!$result['ok']) {
        $error = $result['error'] ?? ['kind' => 'unknown', 'message' => 'Erro desconhecido no MNI.', 'http_status' => 502];
        $status = (int) ($error['http_status'] ?? 502);
        http_response_code($status >= 400 && $status < 600 ? $status : 502);
        echo json_encode([
            'error' => $error['message'] ?? 'Erro ao consultar MNI.',
            'kind'  => $error['kind'] ?? 'unknown',
            'meta'  => [
                'elapsed_ms'    => $result['meta']['elapsed_ms']    ?? 0,
                'http_code'     => $result['meta']['http_code']     ?? 0,
                'version'       => $result['meta']['version']       ?? null,
                'endpoint_host' => $config['tjba']['endpoint_host'] ?? '',
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $shape = $result['shape'];

    $maxMov = (int) ($config['request_defaults']['movimentos_limit'] ?? 200);
    if ($maxMov > 0 && isset($shape['movimentos']) && is_array($shape['movimentos']) && count($shape['movimentos']) > $maxMov) {
        $shape['movimentos_truncado'] = true;
        $shape['movimentos_total']    = count($shape['movimentos']);
        $shape['movimentos']          = array_slice($shape['movimentos'], 0, $maxMov);
        if (isset($shape['mni']) && is_array($shape['mni'])) {
            $shape['mni']['totalMovimentos'] = $shape['movimentos_total'];
        }
    }

    echo json_encode([
        'data' => $shape,
        'meta' => [
            'elapsed_ms'    => $result['meta']['elapsed_ms']    ?? 0,
            'http_code'     => $result['meta']['http_code']     ?? 0,
            'version'       => $result['meta']['version']       ?? null,
            'endpoint_host' => $config['tjba']['endpoint_host'] ?? '',
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Falha interna ao consultar MNI: ' . ApiErrorReporter::genericMessage($e, 'public/api/mni/consultar-processo.php'),
        'kind'  => 'unknown',
    ], JSON_UNESCAPED_UNICODE);
}
