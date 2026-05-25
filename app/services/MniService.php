<?php
declare(strict_types=1);

require_once __DIR__ . '/../Mni/MniClient.php';
require_once __DIR__ . '/../Mni/MniCredentials.php';
require_once __DIR__ . '/../Mni/MniTokenStore.php';
require_once __DIR__ . '/../helpers/CnjValidator.php';

/**
 * Servico de aplicacao para consulta MNI.
 *
 * - Encapsula MniClient + carregamento de credenciais.
 * - Cache em processo (por requisicao) para evitar duplicacao se a mesma pagina
 *   chamar duas vezes seguidas.
 * - Cache de reciboToken em disco (MniTokenStore) para reaproveitar autenticacao
 *   v3 entre chamadas e entre requisicoes. Opt-out via $opts['use_token_cache']=false.
 * - Retry simples (1x) em timeout — o MNI e' lento, mas costuma estabilizar.
 *
 * Nao persiste nada no BD nesta fase; a persistencia fica na facade
 * ProcessoLookupService (chamada apos sucesso).
 */
final class MniService
{
    private const RETRY_KINDS = ['timeout', 'network'];

    private MniCredentials $credentials;
    private ?MniTokenStore $tokenStore;

    /** @var array<string, array<string, mixed>> cache por digits (escopo do processo PHP) */
    private array $cache = [];

    public function __construct(?MniCredentials $credentials = null, ?MniTokenStore $tokenStore = null)
    {
        $this->credentials = $credentials ?? MniCredentials::loadForTjba();
        $this->tokenStore = $tokenStore ?? self::defaultTokenStore();
    }

    /**
     * @param array{
     *   incluir_cabecalho?: bool,
     *   incluir_partes?: bool,
     *   incluir_enderecos?: bool,
     *   incluir_documentos?: bool,
     *   incluir_movimentos?: bool,
     *   use_token_cache?: bool
     * } $opts
     * @return array<string, mixed> mesmo shape de MniClient::consultarProcesso
     */
    public function consultar(string $numero, array $opts = [], bool $useCache = true): array
    {
        $digits = CnjValidator::digits20OrThrow($numero);

        $cacheOpts = $opts;
        unset($cacheOpts['use_token_cache']);
        $cacheKey = $digits . '|' . md5(json_encode($cacheOpts) ?: '');
        if ($useCache && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $useTokenCache = !array_key_exists('use_token_cache', $opts) || (bool) $opts['use_token_cache'];
        $clientOpts = ['use_token_cache' => $useTokenCache];
        $client = new MniClient($this->credentials, $clientOpts, $useTokenCache ? $this->tokenStore : null);
        $reqOpts = $opts;
        unset($reqOpts['use_token_cache']);

        $result = $client->consultarProcesso($digits, $reqOpts);

        if (!$result['ok'] && isset($result['error']['kind']) && in_array($result['error']['kind'], self::RETRY_KINDS, true)) {
            usleep(400_000);
            $result = $client->consultarProcesso($digits, $reqOpts);
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    public function credentials(): MniCredentials
    {
        return $this->credentials;
    }

    public function tokenStore(): ?MniTokenStore
    {
        return $this->tokenStore;
    }

    private static function defaultTokenStore(): ?MniTokenStore
    {
        $root = dirname(__DIR__, 2);
        $base = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mni-tokens';
        try {
            return new MniTokenStore($base);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
