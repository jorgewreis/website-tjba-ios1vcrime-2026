<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pje-publico/search-processo.php');
header('Content-Type: application/json; charset=UTF-8');
@ini_set('default_socket_timeout', '25');
@set_time_limit(30);

require_once dirname(__DIR__, 3) . '/config/config.php';

$enabled = strtolower((string) envValue('PJE_PUBLIC_TEST_PAGE_ENABLED', '0'));
if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
    respond(['error' => 'Consulta pública PJe de teste desabilitada neste ambiente.'], 404);
}

$apiBaseUrl = rtrim(trim((string) envValue('PJE_PUBLIC_API_BASE_URL', 'http://127.0.0.1:8000')), '/');
if ($apiBaseUrl === '') {
    respond(['error' => 'API pública PJe de teste não configurada.'], 500);
}

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$healthcheck = !empty($payload['healthcheck']) || (isset($_GET['healthcheck']) && $_GET['healthcheck'] === '1');
if ($healthcheck) {
    proxyRequest($apiBaseUrl . '/health');
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
if ($numero === '' && isset($_GET['numero'])) {
    $numero = (string) $_GET['numero'];
}

$digits = trim(preg_replace('/\D+/', '', $numero) ?? '');
if ($digits === '' || strlen($digits) !== 20) {
    respond(['error' => 'Número do processo inválido. Forneça 20 dígitos sem pontuação.'], 400);
}

$raw = !empty($payload['raw']) || (isset($_GET['raw']) && $_GET['raw'] === '1');
$path = '/pje/' . rawurlencode($digits) . ($raw ? '/raw' : '');
proxyRequest($apiBaseUrl . $path);

function proxyRequest(string $url): void
{
    $timeout = (int) envValue('PJE_PUBLIC_PROXY_TIMEOUT_SECONDS', '30');
    if ($timeout <= 0) {
        $timeout = 30;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '' || !is_string($body) || $body === '') {
        respond(['error' => 'Falha ao acionar API pública PJe de teste: ' . ($curlError ?: 'resposta vazia')], 502);
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        respond(['error' => 'Resposta inválida da API pública PJe de teste.'], 502);
    }

    if ($status < 200 || $status >= 300) {
        $detail = $decoded['detail'] ?? $decoded['error'] ?? 'Falha na API pública PJe de teste.';
        respond(['error' => is_scalar($detail) ? (string) $detail : 'Falha na API pública PJe de teste.'], $status >= 400 && $status < 600 ? $status : 502);
    }

    respond(['data' => $decoded], 200);
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
