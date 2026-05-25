<?php
declare(strict_types=1);

/**
 * Traduz SOAP Fault, HTTP 4xx/5xx, timeouts e erros de cURL em mensagens curtas e estáveis
 * para a UI/log. Nunca expõe a senha do consultante.
 */
final class MniErrorTranslator
{
    public const KIND_CONFIG     = 'config';
    public const KIND_NETWORK    = 'network';
    public const KIND_TIMEOUT    = 'timeout';
    public const KIND_HTTP       = 'http';
    public const KIND_AUTH       = 'auth';
    public const KIND_FAULT      = 'fault';
    public const KIND_NOT_FOUND  = 'not_found';
    public const KIND_SIGILO     = 'sigilo';
    public const KIND_INVALID    = 'invalid_response';

    /**
     * @param array{
     *   curl_errno?: int,
     *   curl_error?: string,
     *   http_code?: int,
     *   raw?: string,
     *   fault?: array{code?: string, string?: string}
     * } $context
     *
     * @return array{kind: string, message: string, http_status: int}
     */
    public static function translate(array $context): array
    {
        $curlErrno = (int) ($context['curl_errno'] ?? 0);
        $httpCode  = (int) ($context['http_code'] ?? 0);
        $rawError  = trim((string) ($context['curl_error'] ?? ''));
        $fault     = $context['fault'] ?? null;

        if ($curlErrno !== 0) {
            // CURLE_OPERATION_TIMEOUTED = 28 (também conhecido como CURLE_OPERATION_TIMEDOUT em algumas builds).
            if ($curlErrno === 28 || (defined('CURLE_OPERATION_TIMEOUTED') && $curlErrno === CURLE_OPERATION_TIMEOUTED)) {
                return self::result(self::KIND_TIMEOUT, 'Tempo esgotado ao consultar o MNI do tribunal.', 504);
            }
            if (in_array($curlErrno, [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST], true)) {
                return self::result(self::KIND_NETWORK, 'Não foi possível contatar o MNI do tribunal.', 502);
            }
            return self::result(self::KIND_NETWORK, 'Falha de rede ao consultar o MNI: ' . $rawError, 502);
        }

        if ($fault !== null && (!empty($fault['code']) || !empty($fault['string']))) {
            $faultString = trim((string) ($fault['string'] ?? ''));
            $lowered = mb_strtolower($faultString, 'UTF-8');

            if (str_contains($lowered, 'sigilo') || str_contains($lowered, 'permiss')) {
                return self::result(self::KIND_SIGILO, 'Processo sob sigilo: consulente sem permissão.', 403);
            }
            if (str_contains($lowered, 'não encontr') || str_contains($lowered, 'nao encontr') || str_contains($lowered, 'inexisten')) {
                return self::result(self::KIND_NOT_FOUND, 'Processo não encontrado no MNI do tribunal.', 404);
            }
            if (str_contains($lowered, 'senha') || str_contains($lowered, 'autentic') || str_contains($lowered, 'credencia')) {
                return self::result(self::KIND_AUTH, 'Credenciais inválidas para consulta via MNI.', 401);
            }
            return self::result(self::KIND_FAULT, 'Falha negocial do MNI: ' . ($faultString !== '' ? $faultString : 'sem detalhes'), 502);
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return self::result(self::KIND_AUTH, 'Não autorizado a consultar o MNI (verifique idConsultante/senha).', $httpCode);
        }
        if ($httpCode === 404) {
            return self::result(self::KIND_NOT_FOUND, 'Endpoint do MNI não encontrado (verifique URL configurada).', 404);
        }
        if ($httpCode >= 500) {
            return self::result(self::KIND_HTTP, 'Serviço MNI do tribunal indisponível (HTTP ' . $httpCode . ').', 502);
        }
        if ($httpCode >= 400) {
            return self::result(self::KIND_HTTP, 'Resposta inesperada do MNI (HTTP ' . $httpCode . ').', 502);
        }

        return self::result(self::KIND_INVALID, 'Resposta inválida do MNI (XML não reconhecido).', 502);
    }

    public static function configMissing(array $missingVarNames): array
    {
        $list = implode(', ', $missingVarNames);
        return self::result(
            self::KIND_CONFIG,
            'Integração MNI não configurada. Faltando: ' . ($list !== '' ? $list : 'variáveis MNI_* no .env.'),
            500
        );
    }

    /**
     * @return array{kind: string, message: string, http_status: int}
     */
    private static function result(string $kind, string $message, int $httpStatus): array
    {
        return ['kind' => $kind, 'message' => $message, 'http_status' => $httpStatus];
    }
}
