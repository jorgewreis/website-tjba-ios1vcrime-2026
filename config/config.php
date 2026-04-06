<?php

/**
 * Carrega um arquivo .env simples para o escopo atual.
 */
function loadProjectEnv(string $envPath): void
{
    if (!is_file($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ($key === '') {
            continue;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

/**
 * Lê variável de ambiente carregada pelo PHP ou pelo .env local.
 */
function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }

    $value = trim((string) $value);
    return $value !== '' ? $value : $default;
}

/**
 * Quando false (PASSWORD_HISTORY_CHECK=0 no .env), não consulta `historico_senha` para bloquear reutilização.
 * A nova senha continua obrigada a ser diferente da senha atual (alteração e reset).
 * Use false se o banco não mantém histórico (trigger `trg_historico_senha_update` não aplicado).
 */
function isPasswordHistoryCheckEnabled(): bool
{
    $raw = envValue('PASSWORD_HISTORY_CHECK', '1');
    if ($raw === null || $raw === '') {
        return true;
    }

    $v = strtolower(trim($raw));

    return !in_array($v, ['0', 'false', 'no', 'off'], true);
}

/**
 * Interrompe a execução com erro controlado de configuração.
 */
function failConfiguration(array $missingKeys): never
{
    $message = 'Configuracao obrigatoria ausente: ' . implode(', ', $missingKeys);
    error_log($message);

    if (PHP_SAPI === 'cli') {
        throw new RuntimeException($message);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    exit('Erro interno de configuracao. Contate o suporte.');
}

$envPath = __DIR__ . '/../.env';
loadProjectEnv($envPath);

// Configurações do banco de dados (obrigatórias via ambiente ou .env local)
$dbHost = envValue('DB_HOST');
$dbName = envValue('DB_NAME');
$dbUser = envValue('DB_USER');
$dbPass = envValue('DB_PASS');
$dbCharset = envValue('DB_CHARSET', 'utf8mb4');
$dbPort = envValue('DB_PORT', '3306');

$missingDbConfig = [];
foreach ([
    'DB_HOST' => $dbHost,
    'DB_NAME' => $dbName,
    'DB_USER' => $dbUser,
    'DB_PASS' => $dbPass,
] as $key => $value) {
    if ($value === null || $value === '') {
        $missingDbConfig[] = $key;
    }
}

if ($missingDbConfig !== []) {
    failConfiguration($missingDbConfig);
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', $dbCharset);
$portNum = (is_string($dbPort) && $dbPort !== '' && ctype_digit($dbPort)) ? (int) $dbPort : 3306;
define('DB_PORT', $portNum > 0 && $portNum <= 65535 ? $portNum : 3306);

// Configurações de segurança
define('TOKEN_EXPIRATION_HOURS', 8);
define('PASSWORD_MIN_LENGTH', 10);
define('PASSWORD_MAX_LENGTH', 128);
define('SESSION_LIFETIME', 28800); // 8 horas
define('SESSION_IDLE_TIMEOUT', 3600); // 60 minutos
define('LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900); // 15 minutos
define('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_BASE_LOCK_SECONDS', 900); // 15 minutos
define('LOGIN_RATE_LIMIT_MAX_LOCK_SECONDS', 28800); // 8 horas
define('PASSWORD_RESET_TOKEN_TTL_SECONDS', 3600); // 1 hora

// Timezone
date_default_timezone_set(envValue('APP_TIMEZONE', 'America/Bahia'));

function appIsHttpsRequest(): bool
{
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains((string) $_SERVER['HTTP_CF_VISITOR'], 'https'))
    );
}

/**
 * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool,samesite:string}
 */
function appSessionCookieParams(): array
{
    return [
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => appIsHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function bootstrapSession(bool $forceStart = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (PHP_SAPI === 'cli' && !$forceStart) {
        return;
    }

    $cookieParams = appSessionCookieParams();

    // Resolve o diretório de sessões da aplicação:
    // 1ª opção: tmp/sessions/ na raiz do projeto (quando o servidor permitir).
    // 2ª opção: subdiretório exclusivo dentro do temp do sistema (sempre gravável pelo processo PHP).
    $sessionPath = dirname(__DIR__) . '/tmp/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0750, true);
    }
    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        $sessionPath = rtrim(sys_get_temp_dir(), '/\\') . '/ios1vcrime_sess';
        if (!is_dir($sessionPath)) {
            @mkdir($sessionPath, 0750, true);
        }
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        ini_set('session.save_path', $sessionPath);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $cookieParams['secure'] ? '1' : '0');
    ini_set('session.cookie_samesite', $cookieParams['samesite']);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    session_set_cookie_params($cookieParams);
    session_start();
}

function destroySessionData(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => !empty($params['secure']),
            'httponly' => !empty($params['httponly']),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

bootstrapSession();
