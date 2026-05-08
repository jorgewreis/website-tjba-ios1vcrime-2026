<?php
declare(strict_types=1);

$envPath = dirname(__DIR__, 3) . '/.env';
if (is_file($envPath)) {
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

$certPath = trim((string) ($_ENV['MNI_CERT_PATH'] ?? getenv('MNI_CERT_PATH') ?: ''));
if ($certPath !== '' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $certPath)) {
    $certPath = dirname(__DIR__, 3) . '/' . ltrim($certPath, '/\\');
}

return [
    'wsdl' => trim((string) ($_ENV['MNI_WSDL_URL'] ?? getenv('MNI_WSDL_URL') ?: '')),
    'consulta_wsdl' => trim((string) ($_ENV['MNI_CONSULTA_WSDL_URL'] ?? getenv('MNI_CONSULTA_WSDL_URL') ?: '')),
    'location' => trim((string) ($_ENV['MNI_SOAP_LOCATION'] ?? getenv('MNI_SOAP_LOCATION') ?: '')),
    'consulta_location' => trim((string) ($_ENV['MNI_CONSULTA_SOAP_LOCATION'] ?? getenv('MNI_CONSULTA_SOAP_LOCATION') ?: '')),
    'tribunal_code' => trim((string) ($_ENV['MNI_TRIBUNAL_CODE'] ?? getenv('MNI_TRIBUNAL_CODE') ?: '')),
    'include_movimentos' => trim((string) ($_ENV['MNI_INCLUDE_MOVIMENTOS'] ?? getenv('MNI_INCLUDE_MOVIMENTOS') ?: '0')),
    'login' => trim((string) ($_ENV['MNI_LOGIN'] ?? getenv('MNI_LOGIN') ?: '')),
    'password' => trim((string) ($_ENV['MNI_PASSWORD'] ?? getenv('MNI_PASSWORD') ?: '')),
    'consultante_id' => trim((string) ($_ENV['MNI_CONSULTANTE_ID'] ?? getenv('MNI_CONSULTANTE_ID') ?: '')),
    'consultante_senha' => trim((string) ($_ENV['MNI_CONSULTANTE_SENHA'] ?? getenv('MNI_CONSULTANTE_SENHA') ?: '')),
    'probe_numero_processo' => trim((string) ($_ENV['MNI_PROBE_PROCESS_NUMBER'] ?? getenv('MNI_PROBE_PROCESS_NUMBER') ?: '')),
    'cert_path' => $certPath,
    'cert_passphrase' => trim((string) ($_ENV['MNI_CERT_PASSPHRASE'] ?? getenv('MNI_CERT_PASSPHRASE') ?: '')),
    'timeout' => (int) (trim((string) ($_ENV['MNI_TIMEOUT_SECONDS'] ?? getenv('MNI_TIMEOUT_SECONDS') ?: '8')) ?: 8),
    'connect_timeout' => (int) (trim((string) ($_ENV['MNI_CONNECT_TIMEOUT_SECONDS'] ?? getenv('MNI_CONNECT_TIMEOUT_SECONDS') ?: '4')) ?: 4),
];
