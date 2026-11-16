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
    private ?Closure $transport;

    public function __construct(array $config, ?callable $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport === null ? null : Closure::fromCallable($transport);
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

        $failedAttempt = false;
        foreach ($attempts as [$aliasUsed, $url]) {
            $exec = $this->transport === null
                ? $this->executeElasticsearchSearch($url, $body)
                : ($this->transport)($url, $body);
            $decoded = $exec['decoded'];
            $httpCode = $exec['http_code'];

            if ($exec['curl_error'] !== '') {
                $failedAttempt = true;
                continue;
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new RuntimeException('Não autorizado a consultar o DataJud. Verifique a chave da API.');
            }

            if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)
                || !isset($decoded['hits']) || !is_array($decoded['hits'])
                || !isset($decoded['hits']['hits']) || !is_array($decoded['hits']['hits'])
                || !array_is_list($decoded['hits']['hits'])) {
                $failedAttempt = true;
                continue;
            }

            if ($decoded['hits']['hits'] === []) {
                continue;
            }

            if (!isset($decoded['hits']['hits'][0]['_source']) || !is_array($decoded['hits']['hits'][0]['_source'])) {
                $failedAttempt = true;
                continue;
            }
            $source = $decoded['hits']['hits'][0]['_source'];
            // O mapper aceita registros parciais, mas um hit vazio não é dado útil.
            if ($source === []) {
                $failedAttempt = true;
                continue;
            }
            // Estes campos são repassados diretamente ao consumidor; objetos e
            // listas não pertencem ao contrato escalar do mapper.
            $invalidScalar = false;
            foreach (['numeroProcesso', 'tribunal', 'nivelSigilo', 'grau', 'dataAjuizamento', 'dataHoraUltimaAtualizacao', 'id'] as $field) {
                if (isset($source[$field]) && !is_scalar($source[$field])) {
                    $invalidScalar = true;
                    break;
                }
            }
            if ($invalidScalar) {
                $failedAttempt = true;
                continue;
            }
            if (array_key_exists('numeroProcesso', $source) && $source['numeroProcesso'] !== null) {
                $sourceNumero = $source['numeroProcesso'];
                if (!is_string($sourceNumero)
                    || !(ctype_digit($sourceNumero) || CnjValidator::isFormatted($sourceNumero))
                    || CnjValidator::onlyDigits($sourceNumero) !== $numero) {
                    $failedAttempt = true;
                    continue;
                }
            }
            $nivelSigilo = $source['nivelSigilo'] ?? null;
            $redact = DataJudResponseMapper::isNivelSigiloRestrito($nivelSigilo);
            $mapped = DataJudResponseMapper::mapSourceToPayload($source, $numero, $redact);
            // O número padrão e os campos de sigilo derivados não provam que o
            // documento de origem tenha fornecido qualquer dado aproveitável.
            $evidence = $mapped;
            if (!isset($source['numeroProcesso'])) {
                unset($evidence['numero']);
            }
            unset($evidence['sigilo_restrito'], $evidence['sigilo_mensagem'], $evidence['assuntos_completos'], $evidence['fonte_confirmada_para_sincronizacao']);
            if (!self::hasMeaningfulValue($evidence)) {
                $failedAttempt = true;
                continue;
            }

            return $mapped;
        }

        if ($failedAttempt) {
            throw new RuntimeException('Não foi possível consultar o DataJud.');
        }

        // Somente respostas válidas e vazias permitem concluir que não houve hits.
        return DataJudResponseMapper::mapEmptyPublicSearch($numero);
    }

    /** Um campo parcial é útil se contém algum escalar, inclusive zero ou false. */
    private static function hasMeaningfulValue(mixed $value, int $depth = 0): bool
    {
        if ($depth > 32 || $value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $nested) {
                if (self::hasMeaningfulValue($nested, $depth + 1)) {
                    return true;
                }
            }
        }
        return false;
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
