<?php
declare(strict_types=1);

require_once __DIR__ . '/MniCredentials.php';
require_once __DIR__ . '/MniTribunalResolver.php';
require_once __DIR__ . '/MniErrorTranslator.php';
require_once __DIR__ . '/MniRequestBuilder.php';
require_once __DIR__ . '/MniResponseParser.php';
require_once __DIR__ . '/MniProcessoMapper.php';
require_once __DIR__ . '/MniTokenStore.php';

/**
 * Cliente SOAP do MNI usando cURL + envelope XML literal.
 *
 * - Stateless: cada chamada monta envelope/POST/parsea XML/mapeia para shape comum.
 * - Sem dependencia de sessao ou banco — pode ser usado em CLI (tests-mni/).
 * - Cache opcional de reciboToken via MniTokenStore (v3 apenas). Se houver token
 *   valido, monta envelope com <pji:token>; se o servidor recusar (kind=auth),
 *   o token e' invalidado e a chamada e' refeita uma unica vez com
 *   <pji:autenticacaoSimples>. v2.x ignora cache silenciosamente.
 * - Mensagens de erro padronizadas via MniErrorTranslator.
 *
 * Saida de consultarProcesso():
 *   [
 *     'ok'      => bool,
 *     'shape'   => array<string, mixed>|null,   // shape comum (mesmo de DataJud + chave 'mni')
 *     'raw'     => string|null,                  // XML bruto (apenas em testes/debug)
 *     'error'   => array{kind, message, http_status}|null,
 *     'meta'    => array{http_code: int, elapsed_ms: int, endpoint: string, version: string, recibo_token: ?array, used_token: bool},
 *   ]
 *
 * Saida de consultarAlteracao():
 *   [
 *     'ok'         => bool,
 *     'alteracoes' => array<string, mixed>|null,
 *     'raw'        => string|null,
 *     'error'      => array{kind, message, http_status}|null,
 *     'meta'       => array{http_code, elapsed_ms, endpoint, version, recibo_token, used_token},
 *   ]
 */
final class MniClient
{
    private MniCredentials $credentials;
    /** @var array{include_raw?: bool, use_token_cache?: bool} */
    private array $options;
    private ?MniTokenStore $tokenStore;

    /**
     * @param array{include_raw?: bool, use_token_cache?: bool} $options
     */
    public function __construct(MniCredentials $credentials, array $options = [], ?MniTokenStore $tokenStore = null)
    {
        $this->credentials = $credentials;
        $this->options = $options;
        $this->tokenStore = $tokenStore;
    }

    /**
     * @param array{incluir_cabecalho?: bool, incluir_partes?: bool, incluir_enderecos?: bool, incluir_movimentos?: bool, incluir_documentos?: bool} $reqOptions
     * @return array<string, mixed>
     */
    public function consultarProcesso(string $numeroDigits20, array $reqOptions = []): array
    {
        return $this->executeOperation(
            'consultarProcesso',
            $numeroDigits20,
            $reqOptions,
            function (string $digits, string $version, array $opts): string {
                return MniRequestBuilder::buildConsultarProcesso(
                    $digits,
                    $this->credentials->idConsultante(),
                    $this->credentials->senhaConsultante(),
                    $version,
                    $opts
                );
            },
            function (string $xml, string $version): array {
                $parsed = MniResponseParser::parseEnvelope($xml, $version);
                return [
                    'parsed'       => $parsed,
                    'recibo_token' => $parsed['recibo_token'] ?? null,
                    'fault'        => $parsed['fault'],
                    'sucesso'      => $parsed['sucesso'],
                    'mensagens'    => $parsed['mensagens'] ?? [],
                ];
            },
            function (array $parsedBag, string $digits): array {
                $parsed = $parsedBag['parsed'];
                $shape = MniProcessoMapper::mapToCommonShape($parsed['processo'], $digits);
                return ['shape' => $shape];
            }
        );
    }

    /**
     * Operacao consultarAlteracao — polling barato (hashes de cabecalho/movimentos/documentos).
     *
     * @param array{data_referencia?: ?string} $opts
     * @return array<string, mixed>
     */
    public function consultarAlteracao(string $numeroDigits20, array $opts = []): array
    {
        return $this->executeOperation(
            'consultarAlteracao',
            $numeroDigits20,
            $opts,
            function (string $digits, string $version, array $options): string {
                return MniRequestBuilder::buildConsultarAlteracao(
                    $digits,
                    $this->credentials->idConsultante(),
                    $this->credentials->senhaConsultante(),
                    $version,
                    $options
                );
            },
            function (string $xml, string $version): array {
                $parsed = MniResponseParser::parseConsultarAlteracaoResponse($xml, $version);
                return [
                    'parsed'       => $parsed,
                    'recibo_token' => $parsed['recibo_token'] ?? null,
                    'fault'        => $parsed['fault'],
                    'sucesso'      => $parsed['sucesso'],
                    'mensagens'    => $parsed['mensagens'] ?? [],
                ];
            },
            function (array $parsedBag, string $digits): array {
                return ['alteracoes' => $parsedBag['parsed']['alteracoes'] ?? []];
            }
        );
    }

    /**
     * Loop de execucao comum a todas as operacoes: validacao -> roteamento ->
     * envelope (com tentativa de token, se aplicavel) -> POST -> parse ->
     * normalizacao de erros -> retry com autenticacaoSimples em caso de kind=auth.
     *
     * @param string $operation
     * @param string $numero
     * @param array<string, mixed> $opts
     * @param callable(string, string, array<string, mixed>): string $envelopeFactory
     * @param callable(string, string): array<string, mixed> $parseFactory
     * @param callable(array<string, mixed>, string): array<string, mixed> $successPayload
     * @return array<string, mixed>
     */
    private function executeOperation(
        string $operation,
        string $numero,
        array $opts,
        callable $envelopeFactory,
        callable $parseFactory,
        callable $successPayload
    ): array {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        if (strlen($digits) !== 20) {
            return $this->fail(
                MniErrorTranslator::translate(['curl_errno' => 0, 'http_code' => 0, 'fault' => ['string' => 'numeroProcesso deve ter 20 digitos']]),
                $this->emptyMeta(),
                $operation
            );
        }

        if (!$this->credentials->isComplete()) {
            return $this->fail(
                MniErrorTranslator::configMissing($this->credentials->missingVarNames()),
                $this->emptyMeta(),
                $operation
            );
        }

        $resolved = MniTribunalResolver::resolveFromDigits($digits, $this->credentials);
        if ($resolved === null) {
            $describe = MniTribunalResolver::describeDigits($digits);
            $msg = $describe === null
                ? 'CNJ invalido para roteamento MNI.'
                : 'Tribunal nao suportado pelo MNI configurado (J=' . $describe['justica'] . ', TR=' . $describe['tribunal'] . ').';
            return $this->fail(
                ['kind' => 'config', 'message' => $msg, 'http_status' => 422],
                $this->emptyMeta(),
                $operation
            );
        }

        $useTokenCache = $this->shouldUseTokenCache($resolved['version']);
        $cachedToken = $useTokenCache
            ? $this->loadCachedToken($resolved['version'], $resolved['endpoint'])
            : null;

        $attemptOpts = $opts;
        if ($cachedToken !== null) {
            $attemptOpts['auth_token'] = $cachedToken['token'];
        }

        $execution = $this->doRoundtrip(
            $operation,
            $envelopeFactory,
            $parseFactory,
            $digits,
            $resolved,
            $attemptOpts,
            $cachedToken !== null
        );

        if ($execution['retry_with_simple']) {
            // Token cacheado foi recusado — invalida e refaz uma unica vez com autenticacaoSimples.
            if ($useTokenCache) {
                $this->tokenStore->invalidate($resolved['version'], $resolved['endpoint'], $this->credentials->idConsultante());
            }
            unset($attemptOpts['auth_token']);
            $execution = $this->doRoundtrip(
                $operation,
                $envelopeFactory,
                $parseFactory,
                $digits,
                $resolved,
                $attemptOpts,
                false
            );
        }

        $meta = $execution['meta'];

        if ($execution['fail'] !== null) {
            return $this->fail($execution['fail'], $meta, $operation, $execution['raw']);
        }

        // Persistir novo token (v3) sempre que a resposta trouxer um.
        if ($useTokenCache && is_array($meta['recibo_token']) && isset($meta['recibo_token']['token'])) {
            $this->tokenStore->put(
                $resolved['version'],
                $resolved['endpoint'],
                $this->credentials->idConsultante(),
                (string) $meta['recibo_token']['token'],
                isset($meta['recibo_token']['data_expiracao']) ? (string) $meta['recibo_token']['data_expiracao'] : null
            );
        }

        $payload = $successPayload($execution['parsed'], $digits);

        return array_merge(
            ['ok' => true, 'raw' => $this->includeRaw() ? $execution['raw'] : null, 'error' => null, 'meta' => $meta],
            $payload
        );
    }

    /**
     * Executa um envelope + parse, devolvendo:
     *   - 'meta'    com http/elapsed/endpoint/version/recibo_token/used_token
     *   - 'parsed'  bag retornado por $parseFactory (contem 'parsed' interno do parser)
     *   - 'raw'     XML bruto
     *   - 'fail'    array de erro (kind/message/http_status) caso a chamada deva ser tratada como falha
     *   - 'retry_with_simple' bool — sinaliza que o token foi rejeitado e o cliente deve refazer
     *
     * @param array{endpoint: string, version: string, tribunal: string} $resolved
     * @param array<string, mixed> $opts
     * @param callable(string, string, array<string, mixed>): string $envelopeFactory
     * @param callable(string, string): array<string, mixed> $parseFactory
     * @return array{meta: array<string, mixed>, parsed: array<string, mixed>, raw: string, fail: ?array<string, mixed>, retry_with_simple: bool}
     */
    private function doRoundtrip(
        string $operation,
        callable $envelopeFactory,
        callable $parseFactory,
        string $digits,
        array $resolved,
        array $opts,
        bool $usedToken
    ): array {
        $envelope = $envelopeFactory($digits, $resolved['version'], $opts);

        $started = microtime(true);
        $exec = $this->postXml($operation, $resolved['endpoint'], $envelope, $resolved['version']);
        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        $meta = [
            'http_code'   => $exec['http_code'],
            'elapsed_ms'  => $elapsedMs,
            'endpoint'    => $resolved['endpoint'],
            'version'     => $resolved['version'],
            'recibo_token' => null,
            'used_token'  => $usedToken,
        ];

        if ($exec['curl_errno'] !== 0) {
            return [
                'meta' => $meta,
                'parsed' => [],
                'raw' => $exec['raw'],
                'fail' => MniErrorTranslator::translate([
                    'curl_errno' => $exec['curl_errno'],
                    'curl_error' => $exec['curl_error'],
                    'http_code'  => $exec['http_code'],
                ]),
                'retry_with_simple' => false,
            ];
        }

        $bag = $parseFactory($exec['raw'], $resolved['version']);
        $parsed = $bag['parsed'] ?? [];
        $reciboToken = $bag['recibo_token'] ?? null;
        if (is_array($reciboToken)) {
            $meta['recibo_token'] = $reciboToken;
        }

        $fault = $bag['fault'] ?? null;
        if ($fault !== null) {
            $err = MniErrorTranslator::translate([
                'http_code' => $exec['http_code'],
                'fault'     => $fault,
            ]);
            return [
                'meta' => $meta,
                'parsed' => $bag,
                'raw' => $exec['raw'],
                'fail' => $err,
                'retry_with_simple' => $usedToken && $err['kind'] === MniErrorTranslator::KIND_AUTH,
            ];
        }

        if (!($parsed['ok'] ?? false) && $exec['http_code'] >= 400) {
            $err = MniErrorTranslator::translate(['http_code' => $exec['http_code']]);
            return [
                'meta' => $meta,
                'parsed' => $bag,
                'raw' => $exec['raw'],
                'fail' => $err,
                'retry_with_simple' => $usedToken && $exec['http_code'] === 401,
            ];
        }

        $sucesso = $bag['sucesso'] ?? null;
        if ($sucesso === false) {
            $msgList = $bag['mensagens'] ?? [];
            $msg = is_array($msgList) && !empty($msgList) ? (string) $msgList[0] : '';
            $lower = mb_strtolower($msg, 'UTF-8');
            $kind = MniErrorTranslator::KIND_FAULT;
            if (str_contains($lower, 'sigilo') || str_contains($lower, 'permiss')) {
                $kind = MniErrorTranslator::KIND_SIGILO;
            } elseif (str_contains($lower, 'nao encontr') || str_contains($lower, 'não encontr') || str_contains($lower, 'inexisten')) {
                $kind = MniErrorTranslator::KIND_NOT_FOUND;
            } elseif (str_contains($lower, 'senha') || str_contains($lower, 'autentic') || str_contains($lower, 'credencia') || str_contains($lower, 'token')) {
                $kind = MniErrorTranslator::KIND_AUTH;
            }
            return [
                'meta' => $meta,
                'parsed' => $bag,
                'raw' => $exec['raw'],
                'fail' => ['kind' => $kind, 'message' => $msg !== '' ? $msg : 'MNI retornou sucesso=false sem mensagem.', 'http_status' => $kind === MniErrorTranslator::KIND_AUTH ? 401 : 502],
                'retry_with_simple' => $usedToken && $kind === MniErrorTranslator::KIND_AUTH,
            ];
        }

        return [
            'meta' => $meta,
            'parsed' => $bag,
            'raw' => $exec['raw'],
            'fail' => null,
            'retry_with_simple' => false,
        ];
    }

    private function shouldUseTokenCache(string $version): bool
    {
        if ($this->tokenStore === null) {
            return false;
        }
        if (array_key_exists('use_token_cache', $this->options) && $this->options['use_token_cache'] === false) {
            return false;
        }
        return $version === MniRequestBuilder::VERSION_3_0_0;
    }

    /**
     * @return ?array{token: string, data_expiracao: ?string, criado_em: string}
     */
    private function loadCachedToken(string $version, string $endpoint): ?array
    {
        if ($this->tokenStore === null) {
            return null;
        }
        return $this->tokenStore->get($version, $endpoint, $this->credentials->idConsultante());
    }

    private function includeRaw(): bool
    {
        return !empty($this->options['include_raw']);
    }

    /**
     * @return array{curl_errno: int, curl_error: string, http_code: int, raw: string}
     */
    private function postXml(string $operation, string $url, string $envelope, string $version): array
    {
        $ch = curl_init($url);
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . MniRequestBuilder::soapActionFor($operation, $version) . '"',
            'Accept: text/xml',
            'Expect:',
        ];
        curl_setopt_array($ch, [
            CURLOPT_POST            => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HTTPHEADER      => $headers,
            CURLOPT_POSTFIELDS      => $envelope,
            CURLOPT_TIMEOUT_MS      => max(2000, $this->credentials->timeoutMs()),
            CURLOPT_CONNECTTIMEOUT_MS => max(1000, $this->credentials->connectTimeoutMs()),
            CURLOPT_FAILONERROR     => false,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_USERAGENT       => 'TJBA-1VCrime-Ilheus/MNI-Client (PHP cURL)',
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'curl_errno' => $errno,
            'curl_error' => is_string($err) ? $err : '',
            'http_code'  => $code,
            'raw'        => is_string($raw) ? $raw : '',
        ];
    }

    /**
     * @return array{http_code: int, elapsed_ms: int, endpoint: string, version: string, recibo_token: null, used_token: false}
     */
    private function emptyMeta(): array
    {
        return [
            'http_code'    => 0,
            'elapsed_ms'   => 0,
            'endpoint'     => '',
            'version'      => $this->credentials->version(),
            'recibo_token' => null,
            'used_token'   => false,
        ];
    }

    /**
     * @param array{kind: string, message: string, http_status: int} $error
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function fail(array $error, array $meta, string $operation, string $raw = ''): array
    {
        $base = [
            'ok'    => false,
            'raw'   => $this->includeRaw() ? $raw : null,
            'error' => $error,
            'meta'  => $meta,
        ];
        if ($operation === 'consultarAlteracao') {
            $base['alteracoes'] = null;
        } else {
            $base['shape'] = null;
        }
        return $base;
    }
}
