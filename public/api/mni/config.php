<?php
declare(strict_types=1);

/**
 * Resolve a configuração de runtime do MNI a partir de .env / config/mni.example.php.
 *
 * Não expõe segredos para o caller; quem precisa de credenciais usa
 * MniCredentials::loadForTjba() diretamente (a partir de app/Mni/).
 *
 * Retorna apenas metadados úteis para o endpoint HTTP (limites, flags default).
 */

require_once dirname(__DIR__, 3) . '/app/Mni/MniCredentials.php';

$creds = MniCredentials::loadForTjba();

$exampleTemplatePath = dirname(__DIR__, 3) . '/config/mni.example.php';
$template = is_file($exampleTemplatePath) ? require $exampleTemplatePath : [];
$options = isset($template['options']) && is_array($template['options']) ? $template['options'] : [];

return [
    'tjba' => [
        // Não expõe valores reais — apenas se está configurado.
        'configured'    => $creds->isComplete(),
        'missing_vars'  => $creds->missingVarNames(),
        'version'       => $creds->version(),
        'endpoint_host' => parse_url($creds->endpoint(), PHP_URL_HOST) ?: '',
    ],
    'http' => [
        'timeout_ms'         => $creds->timeoutMs(),
        'connect_timeout_ms' => $creds->connectTimeoutMs(),
    ],
    'request_defaults' => [
        'incluir_cabecalho'  => (bool) ($options['incluir_cabecalho']  ?? true),
        'incluir_documentos' => (bool) ($options['incluir_documentos'] ?? false),
        'incluir_movimentos' => (bool) ($options['incluir_movimentos'] ?? true),
        'movimentos_limit'   => (int)  ($options['movimentos_limit']   ?? 200),
    ],
];
