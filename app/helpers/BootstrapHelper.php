<?php
declare(strict_types=1);

require_once __DIR__ . '/AutoloadHelper.php';
require_once __DIR__ . '/Conection.php';

/**
 * Retorna a conexão compartilhada com o banco (PDO).
 * @deprecated Use Conection::getInstance()->getConnection() diretamente
 */
function getDatabaseConnection(): PDO
{
    return Conection::getInstance()->getConnection();
}

/**
 * Inicia variáveis e sessões compartilhadas entre publicações.
 */
if (function_exists('bootstrapSession')) {
    bootstrapSession();
}

/**
 * Proteção leve contra CSRF para chamadas AJAX internas.
 * Requer método POST e mesma origem (Origin ou Referer).
 */
function bootstrapRequireTrustedPostRequest(): bool
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        return false;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $scheme = appIsHttpsRequest() ? 'https' : 'http';
    $request = $host === '' ? false : parse_url($scheme . '://' . $host);
    if (!is_array($request) || empty($request['host'])
        || isset($request['user']) || isset($request['pass'])
        || isset($request['path']) || isset($request['query']) || isset($request['fragment'])) {
        return false;
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $source = $origin !== '' ? $origin : trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $parts = $source === '' ? false : parse_url($source);
    if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])
        || isset($parts['user']) || isset($parts['pass'])) {
        return false;
    }

    // Origin contém somente a origem; Referer pode incluir caminho e consulta.
    if ($origin !== '' && (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment']))) {
        return false;
    }
    $sourceScheme = strtolower((string) $parts['scheme']);
    if (!in_array($sourceScheme, ['http', 'https'], true) || $sourceScheme !== $scheme) {
        return false;
    }
    $defaultPort = $scheme === 'https' ? 443 : 80;
    return hash_equals(strtolower((string) $request['host']), strtolower((string) $parts['host']))
        && (int) ($request['port'] ?? $defaultPort) === (int) ($parts['port'] ?? $defaultPort);
}
