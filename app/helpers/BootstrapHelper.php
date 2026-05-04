<?php
declare(strict_types=1);

require_once __DIR__ . '/AutoloadHelper.php';
require_once __DIR__ . '/Conection.php';
require_once __DIR__ . '/../../config/security.php';

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

    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        $originHost = strtolower(trim((string) parse_url($origin, PHP_URL_HOST)));
        $originPort = (string) parse_url($origin, PHP_URL_PORT);
        $requestPort = (string) parse_url('http://' . $host, PHP_URL_PORT);
        if ($originPort !== '' || $requestPort !== '') {
            $originHost .= ':' . ($originPort !== '' ? $originPort : '80');
            $host .= ':' . ($requestPort !== '' ? $requestPort : '80');
        }
        return hash_equals($host, $originHost);
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer !== '') {
        $refererHost = strtolower(trim((string) parse_url($referer, PHP_URL_HOST)));
        return $refererHost !== '' && hash_equals(strtolower(trim((string) parse_url('http://' . $host, PHP_URL_HOST))), $refererHost);
    }

    return false;
}

/**
 * Requer sessão autenticada além da proteção de POST de mesma origem.
 * Use em APIs JSON que alteram estado para impedir mutações por URL ou clientes sem login.
 */
function bootstrapRequireAuthenticatedTrustedPostRequest(): bool
{
    return bootstrapRequireTrustedPostRequest() && Security::isAuthenticated();
}
