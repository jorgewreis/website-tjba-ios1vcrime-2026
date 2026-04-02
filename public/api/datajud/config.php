<?php
/**
 * Configuração para a consulta pública do DataJud (CNJ).
 * A chave da API deve vir de variável de ambiente ou do .env local do projeto.
 */

$envPath = dirname(__DIR__, 3) . '/.env';
if (is_file($envPath) && (getenv('DATAJUD_API_KEY') === false || getenv('DATAJUD_API_KEY') === '')) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1), " \t\n\r\0\x0B\"'");
        if ($key === '') {
            continue;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

$defaultAlias = $_ENV['DATAJUD_DEFAULT_ALIAS'] ?? getenv('DATAJUD_DEFAULT_ALIAS') ?: '';
$defaultAlias = trim((string) $defaultAlias);
if ($defaultAlias === '') {
    $defaultAlias = 'tjba';
}

$baseUrl = $_ENV['DATAJUD_BASE_URL'] ?? getenv('DATAJUD_BASE_URL') ?: '';
$baseUrl = trim((string) $baseUrl);
if ($baseUrl === '') {
    $baseUrl = 'https://api-publica.datajud.cnj.jus.br';
}

return [
    'base_url' => $baseUrl,
    'default_alias' => $defaultAlias,
    /** @deprecated Mantido para compat.: URL completa antiga; o serviço deriva base_url se necessário */
    'endpoint' => $baseUrl . '/api_publica_' . $defaultAlias . '/_search',
    'api_key' => $_ENV['DATAJUD_API_KEY'] ?? getenv('DATAJUD_API_KEY') ?: '',
    'timeout' => 30,
    'connect_timeout' => 10,
];
