<?php
declare(strict_types=1);

require_once __DIR__ . '/DataJudUpstreamException.php';

/** Orçamento compartilhado entre PHP-FPM e a fila, sem armazenar dados de processos. */
final class DataJudRequestPacer
{
    private const GAP_MS = 25000; // no máximo 3 inícios em qualquer janela de 60 segundos
    private const LEASE_MS = 150000; // cobre uma chamada de até 120 s, mesmo se o processo morrer
    private const KEY = 'ios1vcrime:datajud:pace:v1';

    private const ACQUIRE = <<<'LUA'
local t = redis.call('TIME')
local now = t[1] * 1000 + math.floor(t[2] / 1000)
local wait = math.max(
    (tonumber(redis.call('HGET', KEYS[1], 'next')) or 0) - now,
    (tonumber(redis.call('HGET', KEYS[1], 'cooldown')) or 0) - now,
    math.min(5000, (tonumber(redis.call('HGET', KEYS[1], 'lease')) or 0) - now)
)
if wait > 0 then return math.ceil(wait / 1000) end
redis.call('HSET', KEYS[1], 'owner', ARGV[1], 'next', now + tonumber(ARGV[2]), 'lease', now + tonumber(ARGV[3]))
redis.call('PEXPIRE', KEYS[1], tonumber(ARGV[3]) + 1000)
return 0
LUA;

    private const FINISH = <<<'LUA'
if redis.call('HGET', KEYS[1], 'owner') ~= ARGV[1] then return 0 end
local delay = tonumber(ARGV[2])
if delay > 0 then
    local t = redis.call('TIME')
    local now = t[1] * 1000 + math.floor(t[2] / 1000)
    local untilTime = math.max(tonumber(redis.call('HGET', KEYS[1], 'cooldown')) or 0, now + delay)
    redis.call('HSET', KEYS[1], 'cooldown', untilTime)
    redis.call('PEXPIRE', KEYS[1], math.max(redis.call('PTTL', KEYS[1]), untilTime - now + 1000))
end
redis.call('HDEL', KEYS[1], 'owner', 'lease')
return 1
LUA;

    private function __construct(private readonly Redis $redis, private readonly string $key) {}

    public static function connect(): self
    {
        try {
            $r = new Redis();
            if (!$r->connect('127.0.0.1', 6379, 0.3)) throw new RuntimeException('Redis indisponível.');
            $r->setOption(Redis::OPT_READ_TIMEOUT, 0.5);
            return new self($r, self::KEY);
        } catch (Throwable) {
            // Sem orçamento compartilhado o app não arrisca uma rajada de chamadas.
            throw new DataJudUpstreamException('pace_unavailable');
        }
    }

    public function acquire(): string
    {
        try {
            $token = bin2hex(random_bytes(16));
            $wait = $this->redis->eval(self::ACQUIRE, [$this->key, $token, self::GAP_MS, self::LEASE_MS], 1);
            if (!is_int($wait) || $wait < 0) throw new RuntimeException('Controle sem resposta válida.');
        } catch (Throwable) {
            throw new DataJudUpstreamException('pace_unavailable');
        }
        if ($wait > 0) throw new DataJudUpstreamException('local_throttled', $wait);
        return $token;
    }

    public function finish(string $token, int $pauseSeconds = 0): void
    {
        try {
            // O cooldown e a liberação acontecem na mesma operação atômica.
            $this->redis->eval(self::FINISH, [$this->key, $token, $pauseSeconds * 1000], 1);
        } catch (Throwable) {
            // A reserva expira sozinha; não modifica a resposta já obtida.
            error_log('DataJud: não foi possível finalizar a reserva de frequência.');
        }
    }

    public static function retryAfter(?string $value, ?int $now = null): int
    {
        $v = trim($value ?? '');
        if (preg_match('/^[0-9]{1,12}$/D', $v) === 1) return max(60, (int) $v);
        if (preg_match('/^[A-Z][a-z]{2}, [0-9]{2} [A-Z][a-z]{2} [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} GMT$/D', $v) !== 1) return 60;
        $date = DateTimeImmutable::createFromFormat('!D, d M Y H:i:s T', $v);
        if (!$date || $date->format('D, d M Y H:i:s \\G\\M\\T') !== $v) return 60;
        return max(60, $date->getTimestamp() - ($now ?? time()));
    }
}
