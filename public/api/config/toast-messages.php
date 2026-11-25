<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/config/toast-messages.php');
header('Content-Type: application/json; charset=UTF-8');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

/**
 * Normaliza o payload da mensagem para manter contrato estavel no endpoint.
 */
$normalizeMessage = static function (array $raw): array {
    $type = isset($raw['type']) ? (string) $raw['type'] : 'info';
    if (!in_array($type, ['success', 'error', 'info'], true)) {
        $type = 'info';
    }

    return [
        'type' => $type,
        'title' => isset($raw['title']) ? (string) $raw['title'] : 'Informação',
        'message' => isset($raw['message']) ? (string) $raw['message'] : '',
        'vars' => isset($raw['vars']) && is_array($raw['vars']) ? array_values($raw['vars']) : [],
    ];
};

$messages = [];
foreach ($catalog['messages'] as $key => $rawMessage) {
    if (!is_string($key) || $key === '' || !is_array($rawMessage)) {
        continue;
    }
    $messages[$key] = $normalizeMessage($rawMessage);
}

$meta = array_merge(
    [
        'version' => 'unknown',
        'locale' => 'pt-BR',
    ],
    (array) ($catalog['meta'] ?? [])
);

$fileMTime = filemtime($catalogPath);
$lastModifiedTs = ($fileMTime !== false) ? (int) $fileMTime : time();
$lastModified = gmdate('D, d M Y H:i:s', $lastModifiedTs) . ' GMT';
$meta['generatedAt'] = gmdate('c', $lastModifiedTs);

$body = [
    'meta' => $meta,
    'messages' => $messages,
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

$hasIfNoneMatch = array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER);
$ifNoneMatch = $hasIfNoneMatch ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string) $_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';

$notModifiedByEtag = false;
if ($hasIfNoneMatch) {
    if ($ifNoneMatch === '*') {
        $notModifiedByEtag = true;
    } elseif (preg_match('/\A\s*(?:W\/)?"[\x21\x23-\x7E\x80-\xFF]*"(?:\s*,\s*(?:W\/)?"[\x21\x23-\x7E\x80-\xFF]*")*\s*\z/', $ifNoneMatch)) {
        preg_match_all('/(?:W\/)?"[\x21\x23-\x7E\x80-\xFF]*"/', $ifNoneMatch, $candidates);
        foreach ($candidates[0] as $candidate) {
            if ($candidate === $etag || $candidate === 'W/' . $etag) {
                $notModifiedByEtag = true;
                break;
            }
        }
    }
}
$notModifiedByDate = false;
// If-None-Match takes precedence even when it does not match.
if (!$hasIfNoneMatch && $ifModifiedSince !== '') {
    $ifModifiedSinceTs = strtotime($ifModifiedSince);
    if ($ifModifiedSinceTs !== false && $ifModifiedSinceTs >= $lastModifiedTs) {
        $notModifiedByDate = true;
    }
}

if ($notModifiedByEtag || $notModifiedByDate) {
    http_response_code(304);
    exit;
}

echo $payload;
