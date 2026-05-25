<?php
declare(strict_types=1);

/**
 * Lê variáveis MNI_* do .env (mesma estratégia do public/api/datajud/config.php).
 *
 * - Sem fallback inseguro: se endpoint, idConsultante ou senhaConsultante faltarem,
 *   retorna isComplete()=false e o serviço deve abortar a chamada.
 * - Nunca expõe a senha em logs/__debugInfo (sanitize() devolve uma cópia mascarada).
 */
final class MniCredentials
{
    private const ENV_PREFIX = 'MNI_TJBA_';

    /** @var array<string, string> */
    private array $values;

    /**
     * @param array<string, string> $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function loadForTjba(?string $envPath = null): self
    {
        self::ensureEnvLoaded($envPath);

        $values = [
            'endpoint'           => self::env('ENDPOINT'),
            'version'            => self::env('VERSION', '2.2.2'),
            'id_consultante'     => self::env('ID_CONSULTANTE'),
            'senha_consultante'  => self::env('SENHA_CONSULTANTE'),
            'timeout_ms'         => (string) self::envInt('TIMEOUT_MS', 30000, 'MNI_HTTP_'),
            'connect_timeout_ms' => (string) self::envInt('CONNECT_TIMEOUT_MS', 10000, 'MNI_HTTP_'),
        ];

        return new self($values);
    }

    public function endpoint(): string         { return $this->values['endpoint']; }
    public function version(): string          { return $this->values['version']; }
    public function idConsultante(): string    { return $this->values['id_consultante']; }
    public function senhaConsultante(): string { return $this->values['senha_consultante']; }
    public function timeoutMs(): int           { return (int) $this->values['timeout_ms']; }
    public function connectTimeoutMs(): int    { return (int) $this->values['connect_timeout_ms']; }

    public function isComplete(): bool
    {
        return $this->endpoint() !== ''
            && $this->idConsultante() !== ''
            && $this->senhaConsultante() !== '';
    }

    /**
     * Lista os nomes das variáveis obrigatórias que estão faltando, para mensagem de erro útil.
     *
     * @return list<string>
     */
    public function missingVarNames(): array
    {
        $missing = [];
        if ($this->endpoint() === '')         $missing[] = self::ENV_PREFIX . 'ENDPOINT';
        if ($this->idConsultante() === '')    $missing[] = self::ENV_PREFIX . 'ID_CONSULTANTE';
        if ($this->senhaConsultante() === '') $missing[] = self::ENV_PREFIX . 'SENHA_CONSULTANTE';

        return $missing;
    }

    /**
     * Cópia segura para logs: máscara a senha.
     *
     * @return array<string, string>
     */
    public function sanitize(): array
    {
        $copy = $this->values;
        $copy['senha_consultante'] = $copy['senha_consultante'] === '' ? '' : '***';
        return $copy;
    }

    /** Não vaza valores brutos para var_dump/print_r. */
    public function __debugInfo(): array
    {
        return $this->sanitize();
    }

    private static function env(string $suffix, string $default = ''): string
    {
        $key = self::ENV_PREFIX . $suffix;
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return trim((string) $value);
    }

    private static function envInt(string $suffix, int $default, string $prefix = self::ENV_PREFIX): int
    {
        $key = $prefix . $suffix;
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null || trim((string) $raw) === '') {
            return $default;
        }
        $int = (int) $raw;
        return $int > 0 ? $int : $default;
    }

    /**
     * Carrega o .env do projeto (mesmo formato simples KEY=VALUE usado em public/api/datajud/config.php).
     */
    private static function ensureEnvLoaded(?string $envPath): void
    {
        $path = $envPath ?? dirname(__DIR__, 2) . '/.env';
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            if ($key === '' || strncmp($key, 'MNI_', 4) !== 0) {
                continue;
            }
            if (($_ENV[$key] ?? getenv($key)) !== '' && ($_ENV[$key] ?? getenv($key)) !== false) {
                continue;
            }
            $value = trim(substr($line, $eq + 1), " \t\n\r\0\x0B\"'");
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
