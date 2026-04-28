<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$catalogPath = dirname(__DIR__, 3) . '/config/toast_messages.php';
if (!is_file($catalogPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Catalogo de toast nao encontrado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$catalog = require $catalogPath;
if (!is_array($catalog) || !isset($catalog['messages']) || !is_array($catalog['messages'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Catalogo de toast invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$fileMTime = filemtime($catalogPath);
$lastModifiedTs = ($fileMTime !== false) ? (int) $fileMTime : time();
$lastModified = gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT';

$body = [
    'meta' => array_merge(
        [
            'version' => 'unknown',
            'locale' => 'pt-BR',
            'generatedAt' => gmdate('c', $lastModifiedTs),
        ],
        (array) ($catalog['meta'] ?? [])
    ),
    'messages' => $catalog['messages'],
];

$payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($payload === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Falha ao serializar catalogo de toast.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$etag = '"' . sha1($payload) . '"';
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);
header('Cache-Control: private, max-age=60, must-revalidate');

$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';

$notModifiedByEtag = ($ifNoneMatch !== '' && $ifNoneMatch === $etag);
$notModifiedByDate = false;
if ($ifModifiedSince !== '') {
    $ifModifiedSinceTs = strtotime($ifModifiedSince);
    if ($ifModifiedSinceTs !== false && $ifModifiedSinceTs >= $lastModifiedTs) {
        $notModifiedByDate = true;
    }
}

// #region agent log
@file_put_contents(
    dirname(__DIR__, 3) . '/debug-16faad.log',
    json_encode([
        'sessionId' => '16faad',
        'runId' => 'toast-config-endpoint-initial',
        'hypothesisId' => 'H1',
        'location' => 'public/api/config/toast-messages.php:etag-check',
        'message' => 'Avalia precondicao de cache condicional',
        'data' => [
            'ifNoneMatch' => $ifNoneMatch !== '' ? 'present' : 'absent',
            'ifModifiedSince' => $ifModifiedSince !== '' ? 'present' : 'absent',
            'computedEtag' => $etag,
            'lastModifiedTs' => $lastModifiedTs,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND
);
// #endregion

if ($notModifiedByEtag || $notModifiedByDate) {
    // #region agent log
    @file_put_contents(
        dirname(__DIR__, 3) . '/debug-16faad.log',
        json_encode([
            'sessionId' => '16faad',
            'runId' => 'toast-config-endpoint-initial',
            'hypothesisId' => 'H2',
            'location' => 'public/api/config/toast-messages.php:304',
            'message' => 'Resposta 304 emitida por cache condicional',
            'data' => [
                'notModifiedByEtag' => $notModifiedByEtag,
                'notModifiedByDate' => $notModifiedByDate,
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    http_response_code(304);
    exit;
}

// #region agent log
@file_put_contents(
    dirname(__DIR__, 3) . '/debug-16faad.log',
    json_encode([
        'sessionId' => '16faad',
        'runId' => 'toast-config-endpoint-initial',
        'hypothesisId' => 'H3',
        'location' => 'public/api/config/toast-messages.php:200',
        'message' => 'Resposta 200 com catalogo de toast',
        'data' => [
            'messageCount' => count($body['messages']),
            'version' => $body['meta']['version'] ?? 'unknown',
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND
);
// #endregion

echo $payload;
