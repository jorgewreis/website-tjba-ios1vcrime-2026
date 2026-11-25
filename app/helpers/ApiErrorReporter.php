<?php
declare(strict_types=1);

/**
 * Keeps unexpected API failures out of client responses and logs only bounded metadata.
 */
final class ApiErrorReporter
{
    private const GENERIC_PUBLIC_MESSAGE = 'Não foi possível concluir a operação.';

    /** @var WeakMap<Throwable, true>|null */
    private static ?WeakMap $reported = null;

    public static function register(string $endpoint): void
    {
        ini_set('display_errors', '0');

        set_exception_handler(static function (Throwable $exception) use ($endpoint): void {
            self::logUnexpected($exception, $endpoint);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => self::GENERIC_PUBLIC_MESSAGE], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
            }
        });

        register_shutdown_function(static function () use ($endpoint): void {
            $lastError = error_get_last();
            if (!is_array($lastError) || !in_array($lastError['type'] ?? null, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::logMetadata($endpoint, 'PhpFatalError');
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => self::GENERIC_PUBLIC_MESSAGE], JSON_UNESCAPED_UNICODE);
            }
        });
    }

    public static function genericMessage(Throwable $exception, string $endpoint): string
    {
        self::logUnexpected($exception, $endpoint);

        return self::GENERIC_PUBLIC_MESSAGE;
    }

    public static function logUnexpected(Throwable $exception, string $endpoint): void
    {
        self::$reported ??= new WeakMap();
        if (isset(self::$reported[$exception])) {
            return;
        }
        self::$reported[$exception] = true;

        $exceptionClass = get_class($exception);
        if (preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/D', $exceptionClass) !== 1) {
            $exceptionClass = 'Throwable';
        }

        self::logMetadata($endpoint, $exceptionClass);
    }

    private static function logMetadata(string $endpoint, string $exceptionClass): void
    {
        $safeEndpoint = preg_replace('/[^A-Za-z0-9._\\/-]/', '', $endpoint) ?: 'api';
        $safeClass = preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/D', $exceptionClass) === 1
            ? $exceptionClass
            : 'Throwable';
        error_log('api_internal_error endpoint=' . $safeEndpoint . ' exception=' . $safeClass);
    }
}
