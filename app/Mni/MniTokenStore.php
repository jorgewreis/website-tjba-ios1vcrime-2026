<?php
declare(strict_types=1);

/**
 * Persiste tokens de autenticacao MNI (reciboToken da v3) em arquivos JSON locais.
 *
 * Politica:
 *  - 1 arquivo por chave (version + endpoint + idConsultante), nome = sha256 hex.
 *  - flock exclusivo em escrita; tempfile + rename para atomicidade.
 *  - leitura respeita dataExpiracao (ISO 8601) + margem de seguranca SAFETY_SECONDS.
 *  - Diretorio criado com 0700 quando possivel. Em Windows o chmod e' best-effort.
 *  - Sem dependencias externas. Sem estado global.
 *  - O id do consultante NAO aparece em log; entra apenas no hash da chave.
 *
 * Fluxo tipico:
 *  $store = new MniTokenStore('/abs/path/storage/mni-tokens');
 *  $tok = $store->get($version, $endpoint, $idConsultante);
 *  if ($tok) usar $tok['token'];
 *  $store->put($version, $endpoint, $idConsultante, $newToken, $dataExpiracao);
 *  $store->invalidate(...);
 */
final class MniTokenStore
{
    /** Margem (em segundos) antes da expiracao para descartar o token. */
    public const SAFETY_SECONDS = 30;

    private string $basePath;

    /** Construtor isolavel (instanciar do servico). */
    public function __construct(string $basePath)
    {
        $resolved = rtrim($basePath, "/\\");
        if ($resolved === '' || !$this->ensureDir($resolved)) {
            $resolved = rtrim(sys_get_temp_dir(), "/\\") . DIRECTORY_SEPARATOR . 'mni-tokens';
            $this->ensureDir($resolved);
        }
        $this->basePath = $resolved;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Retorna o token cacheado se valido, considerando a margem de seguranca.
     *
     * @return ?array{token: string, data_expiracao: ?string, criado_em: string}
     */
    public function get(string $version, string $endpoint, string $idConsultante): ?array
    {
        $file = $this->fileFor($version, $endpoint, $idConsultante);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['token']) || !is_string($decoded['token']) || $decoded['token'] === '') {
            return null;
        }
        $expira = isset($decoded['data_expiracao']) && is_string($decoded['data_expiracao']) && $decoded['data_expiracao'] !== ''
            ? (string) $decoded['data_expiracao']
            : null;
        if (!$this->isStillValid($expira)) {
            return null;
        }
        return [
            'token'          => (string) $decoded['token'],
            'data_expiracao' => $expira,
            'criado_em'      => isset($decoded['criado_em']) && is_string($decoded['criado_em'])
                ? (string) $decoded['criado_em']
                : '',
        ];
    }

    /**
     * Persiste o token retornado pelo MNI (v3 reciboToken).
     * Devolve true se gravou, false em qualquer falha (sem lancar excecao).
     */
    public function put(string $version, string $endpoint, string $idConsultante, string $token, ?string $dataExpiracao): bool
    {
        if ($token === '') {
            return false;
        }
        $file = $this->fileFor($version, $endpoint, $idConsultante);
        $payload = json_encode([
            'token'          => $token,
            'data_expiracao' => $dataExpiracao,
            'criado_em'      => gmdate('Y-m-d\TH:i:s\Z'),
            'version'        => $version,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            return false;
        }

        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $fp = @fopen($tmp, 'wb');
        if ($fp === false) {
            return false;
        }
        $ok = false;
        try {
            if (@flock($fp, LOCK_EX)) {
                $written = @fwrite($fp, $payload);
                @fflush($fp);
                @flock($fp, LOCK_UN);
                $ok = is_int($written) && $written === strlen($payload);
            }
        } finally {
            @fclose($fp);
        }
        if (!$ok) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, 0600);
        return true;
    }

    /**
     * Remove o token cacheado (apos falha de autenticacao). Idempotente.
     */
    public function invalidate(string $version, string $endpoint, string $idConsultante): void
    {
        $file = $this->fileFor($version, $endpoint, $idConsultante);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function fileFor(string $version, string $endpoint, string $idConsultante): string
    {
        $key = $version . '|' . $endpoint . '|' . $idConsultante;
        $name = hash('sha256', $key);
        return $this->basePath . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) {
            @chmod($dir, 0700);
            return is_writable($dir);
        }
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }
        @chmod($dir, 0700);
        return is_writable($dir);
    }

    private function isStillValid(?string $isoExpiracao): bool
    {
        if ($isoExpiracao === null || $isoExpiracao === '') {
            return false;
        }
        try {
            $exp = new DateTimeImmutable($isoExpiracao);
        } catch (Exception $e) {
            return false;
        }
        $now = new DateTimeImmutable('now', $exp->getTimezone());
        $threshold = $exp->getTimestamp() - self::SAFETY_SECONDS;
        return $now->getTimestamp() < $threshold;
    }
}
