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

return [
    'endpoint' => 'https://api-publica.datajud.cnj.jus.br/api_publica_tjba/_search',
    'api_key' => $_ENV['DATAJUD_API_KEY'] ?? getenv('DATAJUD_API_KEY') ?: '',
    'timeout' => 30,
];
