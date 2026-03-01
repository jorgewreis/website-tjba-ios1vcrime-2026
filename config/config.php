<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

// Configurações do banco de dados
define('DB_HOST', '72.60.250.46');
define('DB_NAME', 'common');
define('DB_USER', 'admin');
define('DB_PASS', 'vluyIOTXf2fKx8RLZxck');
define('DB_CHARSET', 'utf8mb4');

// Configurações de segurança
define('TOKEN_EXPIRATION_HOURS', 8);
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 28800); // 8 horas

// Timezone
date_default_timezone_set('America/Bahia');

// Configurações de sessão (ANTES de iniciar a sessão)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);

    // Só marca secure se estiver em HTTPS
    $isHttps =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], 'https'));

    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);


    // Define parâmetros do cookie de sessão
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    // Inicia a sessão apenas se ainda não foi iniciada
    session_start();
}
