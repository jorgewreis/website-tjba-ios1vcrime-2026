<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../DataJud/DataJudResponseMapper.php';
require_once __DIR__ . '/../DataJud/DataJudTribunalResolver.php';

/**
 * Serviço para consulta ao DataJud (CNJ).
 * Mesma estrutura lógica de public/api/datajud/search-processo.php.
 */
final class DataJudService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Consulta processo por número (20 dígitos).
     *
     * @return array<string, mixed> payload com campos processuais; inclui sigilo_restrito e sigilo_mensagem quando aplicável
     */
    public function searchProcesso(string $numero): array
    {
        $numero = CnjValidator::digits20OrThrow($numero);

        $baseUrl = $this->getBaseUrl();
        $defaultAlias = $this->getDefaultAlias();
        $resolvedAlias = DataJudTribunalResolver::resolveAliasFromDigits($numero) ?? $defaultAlias;

        $body = $this->buildElasticsearchBody($numero);

        $attempts = [[$resolvedAlias, DataJudTribunalResolver::buildSearchUrl($baseUrl, $resolvedAlias)]];
        if ($resolvedAlias !== $defaultAlias) {
            $attempts[] = [$defaultAlias, DataJudTribunalResolver::buildSearchUrl($baseUrl, $defaultAlias)];
        }

        foreach ($attempts as [$aliasUsed, $url]) {
            $exec = $this->executeElasticsearchSearch($url, $body);
            $decoded = $exec['decoded'];
            $httpCode = $exec['http_code'];

            if ($exec['curl_error'] !== '') {
                throw new RuntimeException('Falha ao contatar o DataJud: ' . $exec['curl_error']);
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new RuntimeException('Não autorizado a consultar o DataJud. Verifique a chave da API.');
            }

            if ($httpCode >= 500) {
                throw new RuntimeException('Serviço DataJud indisponível no momento. Tente novamente em instantes.');
            }

            if (!is_array($decoded)) {
                // Resposta não JSON ou corpo vazio: tenta índice alternativo (ex.: fallback tjba).
                continue;
            }

            if (!isset($decoded['hits']['hits'][0]['_source'])) {
                continue;
            }

            $source = $decoded['hits']['hits'][0]['_source'];
            $nivelSigilo = $source['nivelSigilo'] ?? null;
            $redact = DataJudResponseMapper::isNivelSigiloRestrito($nivelSigilo);

            return DataJudResponseMapper::mapSourceToPayload($source, $numero, $redact);
        }

        // 0 hits: na API pública isso ocorre com frequência em processos sigilosos (fora do índice público).
        return DataJudResponseMapper::mapEmptyPublicSearch($numero);
    }

    private function getBaseUrl(): string
    {
        if (!empty($this->config['base_url'])) {
            return rtrim((string) $this->config['base_url'], '/');
        }
        if (!empty($this->config['endpoint'])) {
            $ep = (string) $this->config['endpoint'];
            if (preg_match('#^(https?://[^/]+)#', $ep, $m)) {
                return rtrim($m[1], '/');
            }
        }

        return 'https://api-publica.datajud.cnj.jus.br';
    }

    private function getDefaultAlias(): string
    {
        $a = $this->config['default_alias'] ?? getenv('DATAJUD_DEFAULT_ALIAS') ?: '';
        $a = trim((string) $a);

        return $a !== '' ? $a : 'tjba';
    }

    /**
     * @return array{query: array<string, mixed>, size: int, _source: bool}
     */
    private function buildElasticsearchBody(string $numero): array
    {
        return [
            'query' => [
                'bool' => [
                    'should' => [
                        ['match' => ['numeroProcesso' => $numero]],
                        ['term' => ['numeroProcesso' => $numero]],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
            'size' => 1,
            '_source' => true,
        ];
    }

    /**
     * @return array{curl_error: string, http_code: int, decoded: mixed, raw: string}
     */
    private function executeElasticsearchSearch(string $url, array $body): array
    {
        $timeout = (int) ($this->config['timeout'] ?? 30);
        if ($timeout < 5) {
            $timeout = 30;
        }
        $connectTimeout = (int) ($this->config['connect_timeout'] ?? 10);
        if ($connectTimeout < 2) {
            $connectTimeout = 10;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: APIKey ' . $this->config['api_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_FAILONERROR => false,
        ]);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }

        return [
            'curl_error' => is_string($curlErr) ? $curlErr : '',
            'http_code' => $httpCode,
            'decoded' => $decoded,
            'raw' => is_string($raw) ? $raw : '',
        ];
    }
}
