<?php
declare(strict_types=1);

/**
 * Respostas JSON uniformes para endpoints em public/api.
 * Carregar {@see BootstrapHelper} antes quando o endpoint usar {@see bootstrapRequireTrustedPostRequest}.
 */
final class ApiJsonResponse
{
    /**
     * Corpo JSON (php://input) com fallback para $_POST.
     *
     * @return array<string, mixed>
     */
    public static function readJsonPayload(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        return is_array($_POST) ? $_POST : [];
    }

    /**
     * Envia JSON com Content-Type e status HTTP.
     *
     * @param array<string, mixed> $payload
     */
    public static function send(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
