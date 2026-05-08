<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
@ini_set('default_socket_timeout', '8');
@set_time_limit(20);

require_once dirname(__DIR__, 3) . '/config/config.php';

// #region agent log
$__mniDiagId = 'mni_' . bin2hex(random_bytes(4));
error_log('[MNI_DIAG][' . $__mniDiagId . '] entry search-processo.php method=' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
register_shutdown_function(static function () use ($__mniDiagId): void {
    $err = error_get_last();
    if ($err !== null) {
        error_log('[MNI_DIAG][' . $__mniDiagId . '] shutdown_error type=' . ($err['type'] ?? '') . ' msg=' . ($err['message'] ?? '') . ' file=' . ($err['file'] ?? '') . ' line=' . ($err['line'] ?? ''));
    } else {
        error_log('[MNI_DIAG][' . $__mniDiagId . '] shutdown_ok');
    }
});
// #endregion

$enabled = strtolower((string) envValue('MNI_TEST_PAGE_PUBLIC_ENABLED', '0'));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    respond(['error' => 'Consulta MNI de teste desabilitada neste ambiente.'], 404);
}

$config = require __DIR__ . '/config.php';
if (($config['wsdl'] ?? '') === '') {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] wsdl_missing');
    respond(['error' => 'Integração MNI não configurada (MNI_WSDL_URL ausente).'], 500);
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$ping = !empty($payload['ping']) || (isset($_GET['ping']) && $_GET['ping'] === '1');
$ifWsdlConfigured = (($config['wsdl'] ?? '') !== '');
if ($ping) {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] ping_ok');
    respond([
        'data' => [
            'ok' => true,
            'endpoint' => 'mni/search-processo.php',
            'wsdl_configurado' => $ifWsdlConfigured,
            'diag_id' => $__mniDiagId,
            'timestamp' => date(DATE_ATOM),
        ],
    ], 200);
}

$healthcheck = !empty($payload['healthcheck']) || (isset($_GET['healthcheck']) && $_GET['healthcheck'] === '1');

require_once dirname(__DIR__, 3) . '/app/services/MniProcessoService.php';

if ($healthcheck) {
    $quick = !isset($_GET['mode']) || $_GET['mode'] !== 'full';
    error_log('[MNI_DIAG][' . $__mniDiagId . '] healthcheck_start quick=' . ($quick ? '1' : '0'));
    try {
        $service = new MniProcessoService($config);
        $diag = $service->diagnosticarConectividade($quick);
        error_log('[MNI_DIAG][' . $__mniDiagId . '] healthcheck_ok');
        respond(['data' => $diag], 200);
    } catch (RuntimeException $e) {
        error_log('[MNI_DIAG][' . $__mniDiagId . '] healthcheck_runtime_error=' . $e->getMessage());
        respond(['error' => 'Falha no healthcheck MNI: ' . $e->getMessage()], 502);
    } catch (Throwable $e) {
        error_log('[MNI_DIAG][' . $__mniDiagId . '] healthcheck_throwable=' . $e->getMessage());
        respond(['error' => 'Falha inesperada no healthcheck MNI.'], 500);
    }
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$numero = trim(preg_replace('/\D+/', '', $numero));
if ($numero === '' || strlen($numero) !== 20) {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] invalid_numero len=' . strlen($numero));
    respond(['error' => 'Número do processo inválido. Forneça 20 dígitos sem pontuação.'], 400);
}

try {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] consulta_start numero=' . $numero);
    $service = new MniProcessoService($config);
    $data = $service->consultarPorNumeroCnj($numero);
    error_log('[MNI_DIAG][' . $__mniDiagId . '] consulta_ok');
    respond(['data' => $data], 200);
} catch (InvalidArgumentException $e) {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] consulta_invalid_arg=' . $e->getMessage());
    respond(['error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] consulta_runtime=' . $e->getMessage());
    respond(['error' => $e->getMessage()], 502);
} catch (Throwable $e) {
    error_log('[MNI_DIAG][' . $__mniDiagId . '] consulta_throwable=' . $e->getMessage());
    respond(['error' => 'Falha inesperada na consulta MNI.'], 500);
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
