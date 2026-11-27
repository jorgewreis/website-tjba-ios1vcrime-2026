<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/CnjValidator.php';
require_once __DIR__ . '/../DataJud/DataJudResponseMapper.php';
require_once __DIR__ . '/../DataJud/DataJudTribunalResolver.php';
require_once __DIR__ . '/../DataJud/DataJudUpstreamException.php';
require_once __DIR__ . '/../DataJud/DataJudRequestPacer.php';
require_once __DIR__ . '/../DataJud/DataJudLocalSigiloException.php';

final class DataJudAmbiguousResponseException extends RuntimeException {}

/**
 * Serviço para consulta ao DataJud (CNJ).
 * Mesma estrutura lógica de public/api/datajud/search-processo.php.
 */
final class DataJudService
{
    private array $config;
    private ?Closure $transport;

    public function __construct(array $config, ?Closure $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport;
    }

    /**
     * Consulta processo por número (20 dígitos).
     *
     * @return array<string, mixed> payload com campos processuais; inclui sigilo_restrito e sigilo_mensagem quando aplicável
     */
    public function consultarFichaPublica(string $numero): array
    {
        return $this->buscar($numero, false, false, true);
    }

    public function searchProcesso(string $numero, bool $forSync = false, bool $includeSyncOrgao = false): array
    {
        return $this->buscar($numero, $forSync, $includeSyncOrgao, false);
    }

    private function buscar(string $numero, bool $forSync, bool $includeSyncOrgao, bool $apenasFichaPublica): array
    {
        $numero = CnjValidator::digits20OrThrow($numero);

        $baseUrl = $this->getBaseUrl();
        $resolvedAlias = DataJudTribunalResolver::resolveAliasFromDigits($numero);
        if ($resolvedAlias === null) {
            throw new InvalidArgumentException('Tribunal do processo não suportado para consulta no DataJud.');
        }

        $body = $this->buildElasticsearchBody($numero, $forSync, $includeSyncOrgao, $apenasFichaPublica);

        $attempts = [[$resolvedAlias, DataJudTribunalResolver::buildSearchUrl($baseUrl, $resolvedAlias)]];

        foreach ($attempts as [$aliasUsed, $url]) {
            $exec = $this->executeElasticsearchSearch($url, $body);
            $decoded = $exec['decoded'];
            $httpCode = $exec['http_code'];

            if ($exec['curl_error'] !== '') {
                throw new DataJudUpstreamException('transport');
            }

            if ($httpCode === 401 || $httpCode === 403) {
                throw new DataJudUpstreamException('authorization');
            }

            if ($httpCode === 429) {
                throw new DataJudUpstreamException('throttled', $exec['retry_after'] ?? 60);
            }

            if ($httpCode >= 500) {
                throw new DataJudUpstreamException('unavailable');
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('Não foi possível consultar o DataJud.');
            }

            // Mesmo com HTTP 200, o Elasticsearch pode reportar execução incompleta.
            if (is_array($decoded) && array_key_exists('timed_out', $decoded)
                && (!is_bool($decoded['timed_out']) || $decoded['timed_out'])) {
                throw new DataJudUpstreamException('incomplete');
            }
            if (is_array($decoded) && array_key_exists('_shards', $decoded)) {
                $shards = $decoded['_shards'];
                if (!is_array($shards) || !isset($shards['failed']) || !is_int($shards['failed']) || $shards['failed'] !== 0) {
                    throw new DataJudUpstreamException('incomplete');
                }
            }
            if (!is_array($decoded) || !isset($decoded['hits']['hits']) || !is_array($decoded['hits']['hits']) || !array_is_list($decoded['hits']['hits'])) {
                throw new RuntimeException('Resposta inválida do DataJud. Tente novamente.');
            }

            // Sem total exato não é possível comprovar que o primeiro documento
            // representa o único resultado para esse CNJ.
            $total = $decoded['hits']['total'] ?? null;
            if (!is_array($total) || !isset($total['value'], $total['relation'])
                || !is_int($total['value']) || $total['value'] < 0 || $total['relation'] !== 'eq') {
                throw new DataJudUpstreamException('incomplete');
            }
            if ($total['value'] > 1 || count($decoded['hits']['hits']) > 1) {
                throw new DataJudAmbiguousResponseException('Consulta DataJud ambígua; a sincronização automática foi recusada.');
            }
            if ($total['value'] !== count($decoded['hits']['hits'])) {
                throw new RuntimeException('Resposta inválida do DataJud. Tente novamente.');
            }

            $shardInfo = $decoded['_shards'] ?? null;
            $complete = array_key_exists('timed_out', $decoded)
                && is_array($shardInfo)
                && isset($shardInfo['total'], $shardInfo['successful'], $shardInfo['failed'], $shardInfo['skipped'])
                && is_int($shardInfo['total']) && $shardInfo['total'] > 0
                && is_int($shardInfo['successful']) && $shardInfo['successful'] >= 0
                && is_int($shardInfo['failed']) && $shardInfo['failed'] === 0
                && is_int($shardInfo['skipped']) && $shardInfo['skipped'] >= 0
                && $shardInfo['successful'] + $shardInfo['failed'] === $shardInfo['total']
                && $shardInfo['skipped'] <= $shardInfo['successful'];

            if ($decoded['hits']['hits'] === []) {
                if (!$complete) throw new DataJudUpstreamException('incomplete');
                continue;
            }

            if ($forSync && !$complete) throw new DataJudUpstreamException('incomplete');

            $source = $decoded['hits']['hits'][0]['_source'] ?? null;
            if (!is_array($source) || !isset($source['numeroProcesso']) || !is_string($source['numeroProcesso'])
                || CnjValidator::onlyDigits($source['numeroProcesso']) !== $numero) {
                throw new RuntimeException('Resposta inválida do DataJud. Tente novamente.');
            }
            $nivelSigilo = $source['nivelSigilo'] ?? null;
            $redact = DataJudResponseMapper::isNivelSigiloRestrito($nivelSigilo);

            $payload = DataJudResponseMapper::mapSourceToPayload($source, $numero, $redact);
            $payload['resultado_unico_verificado'] = $complete;
            return $payload;
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

    /**
     * @return array{query: array<string, mixed>, size: int, track_total_hits: bool, _source: list<string>}
     */
    private function buildElasticsearchBody(string $numero, bool $forSync, bool $includeSyncOrgao, bool $apenasFichaPublica): array
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
            'size' => 2,
            'track_total_hits' => true,
            // A ficha e a sincronização só requisitam os campos que utilizam.
            '_source' => $forSync ? array_merge(['numeroProcesso', 'nivelSigilo', 'classe', 'classeProcessual', 'assuntos'],
                $includeSyncOrgao ? ['orgaoJulgador.nome', 'orgaoJulgador.codigoMunicipioIBGE'] : []) : ($apenasFichaPublica ? [
                'numeroProcesso', 'nivelSigilo', 'classe', 'classeProcessual', 'assuntos',
                'orgaoJulgador.nome', 'movimentos.nome', 'movimentos.dataHora', 'movimentos.data_hora',
            ] : [
                'numeroProcesso', 'tribunal', 'classe', 'classeProcessual', 'assuntos',
                'prioridade', 'polo', 'poloAtivo', 'poloPassivo', 'movimentos',
                'orgaoJulgador', 'nivelSigilo', 'grau', 'formato', 'sistema',
                'dataAjuizamento', 'dataHoraUltimaAtualizacao', 'id',
            ]),
        ];
    }

    /**
     * @return array{curl_error: string, http_code: int, decoded: mixed, raw: string, retry_after?: int}
     */
    private function executeElasticsearchSearch(string $url, array $body): array
    {
        if ($this->transport !== null) {
            return ($this->transport)($url, $body);
        }
        $pacer = DataJudRequestPacer::connect();
        $token = $pacer->acquire();
        $result = null;
        try {
            return $result = $this->sendToElastic($url, $body);
        } finally {
            $pacer->finish($token, $result['retry_after'] ?? 0);
        }
    }

    private function sendToElastic(string $url, array $body): array
    {
        $timeout = max(5, min(60, (int) ($this->config['timeout'] ?? 60)));
        $connectTimeout = max(2, min(15, (int) ($this->config['connect_timeout'] ?? 10)));

        $ch = curl_init($url);
        $retryAfterHeader = null;
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
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$retryAfterHeader): int {
                if (preg_match('/^HTTP\//i', $line)) $retryAfterHeader = null;
                if (stripos($line, 'Retry-After:') === 0) $retryAfterHeader = trim(substr($line, 12));
                return strlen($line);
            },
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
            'retry_after' => $httpCode === 429 ? DataJudRequestPacer::retryAfter($retryAfterHeader) : 0,
            'decoded' => $decoded,
            'raw' => is_string($raw) ? $raw : '',
        ];
    }
}
