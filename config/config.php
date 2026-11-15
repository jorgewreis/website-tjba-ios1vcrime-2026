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

/**
 * Retorna o ambiente solicitado pelo processo antes de qualquer arquivo local
 * ser carregado. Isso impede que o .env de desenvolvimento reconfigure um
 * processo de teste já iniciado com APP_ENV=testing.
 */
function requestedAppEnvironment(): string
{
    $value = getenv('APP_ENV');
    if ($value === false || trim((string) $value) === '') {
        $value = $_ENV['APP_ENV'] ?? 'production';
    }

    return strtolower(trim((string) $value));
}

/**
 * Resolve o arquivo de ambiente de testes sem permitir caminhos arbitrários.
 */
function authTestEnvironmentPath(): string
{
    $configuredPath = trim((string) (getenv('AUTH_TEST_ENV_FILE') ?: ($_ENV['AUTH_TEST_ENV_FILE'] ?? '')));
    if ($configuredPath === '') {
        failConfiguration(['AUTH_TEST_ENV_FILE']);
    }

    $projectRoot = realpath(__DIR__ . '/..');
    $allowedDirectory = $projectRoot === false ? false : realpath($projectRoot . '/tests/auth');
    $resolvedPath = realpath($configuredPath);

    if ($projectRoot === false || $allowedDirectory === false || $resolvedPath === false) {
        failConfiguration(['AUTH_TEST_ENV_FILE']);
    }

    $allowedPrefix = rtrim($allowedDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($resolvedPath, $allowedPrefix) || !is_file($resolvedPath)) {
        failConfiguration(['AUTH_TEST_ENV_FILE']);
    }

    return $resolvedPath;
}

$requestedEnvironment = requestedAppEnvironment();
if ($requestedEnvironment === 'testing') {
    loadProjectEnv(authTestEnvironmentPath());
} else {
    loadProjectEnv(__DIR__ . '/../.env');
}

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

if ($requestedEnvironment === 'testing') {
    $requiredTestConfig = [
        'AUTH_TEST_ISOLATION' => envValue('AUTH_TEST_ISOLATION'),
        'MAIL_DRIVER' => envValue('MAIL_DRIVER'),
        'MAIL_HOST' => envValue('MAIL_HOST'),
        'MAIL_USER' => envValue('MAIL_USER') ?? envValue('MAIL_USERNAME'),
    ];
    $missingTestConfig = [];
    foreach ($requiredTestConfig as $key => $value) {
        if ($value === null || $value === '') {
            $missingTestConfig[] = $key;
        }
    }
    if ($missingTestConfig !== []) {
        failConfiguration($missingTestConfig);
    }

    $allowedTestDatabaseHosts = ['mysql', '127.0.0.1', 'localhost'];
    $allowedTestMailHosts = ['mailpit', '127.0.0.1', 'localhost'];
    $testDatabaseName = (string) envValue('DB_NAME', '');
    $testMailHost = strtolower((string) envValue('MAIL_HOST', ''));
    $testFromAddress = strtolower((string) envValue('MAIL_FROM_ADDRESS', ''));
    $testAppUrl = (string) envValue('APP_URL', '');
    if (
        envValue('AUTH_TEST_ISOLATION') !== 'isolated-auth-test'
        || !in_array(strtolower($dbHost), $allowedTestDatabaseHosts, true)
        || !in_array($testMailHost, $allowedTestMailHosts, true)
        || !str_starts_with($testDatabaseName, 'auth_test_')
        || strtolower((string) envValue('MAIL_DRIVER')) !== 'smtp'
        || strtolower((string) envValue('MAIL_ENCRYPTION')) !== 'none'
        || !str_ends_with($testFromAddress, '@auth-test.invalid')
        || !str_ends_with(strtolower($testAppUrl), '.invalid')
    ) {
        failConfiguration(['AUTH_TEST_ISOLATION']);
    }
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
define('SESSION_IDLE_TIMEOUT', 14400); // 4 horas
define('LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900); // 15 minutos
define('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_BASE_LOCK_SECONDS', 900); // 15 minutos
define('LOGIN_RATE_LIMIT_MAX_LOCK_SECONDS', 28800); // 8 horas
define('PASSWORD_RESET_TOKEN_TTL_SECONDS', 3600); // 1 hora

/** Provisório: false = não consulta historico_senha na alteração de senha (tela / API alterar_senha). Reativar: true. */
define('PASSWORD_HISTORY_ON_CHANGE_ENABLED', false);

// Timezone
date_default_timezone_set(envValue('APP_TIMEZONE', 'America/Bahia'));

function appIsHttpsRequest(): bool
{
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    ) {
        return true;
    }

    if (!appIsTrustedProxyRequest()) {
        return false;
    }

    return (
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains((string) $_SERVER['HTTP_CF_VISITOR'], 'https'))
    );
}

function appIsTrustedProxyRequest(): bool
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        return false;
    }

    $trusted = array_filter(array_map('trim', explode(',', (string) envValue('TRUSTED_PROXIES', ''))));
    return in_array($remote, $trusted, true);
}

/**
 * Retorna a origem pública canônica usada em links sensíveis enviados por e-mail.
 * Nunca derive esta origem de headers recebidos na requisição.
 */
function appCanonicalPublicUrl(): ?string
{
    $url = trim((string) envValue('APP_URL', ''));
    if ($url === '') {
        return null;
    }

    $parts = parse_url($url);
    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
    if (
        $parts === false
        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || $host === ''
        || (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['query'], $parts['fragment'])
    ) {
        return null;
    }

    return rtrim($url, '/');
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
        // Em produção, o cookie nunca pode ser reenviado por HTTP, mesmo atrás
        // de um proxy cuja cadeia ainda não tenha sido declarada como confiável.
        'secure' => strtolower((string) envValue('APP_ENV', 'production')) === 'production'
            ? true
            : appIsHttpsRequest(),
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
