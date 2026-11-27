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
redis.call('PEXPIRE', KEYS[1], math.max(redis.call('PTTL', KEYS[1]), tonumber(ARGV[3]) + 1000))
return 0
LUA;

    private const FINISH = <<<'LUA'
if redis.call('HGET', KEYS[1], 'owner') ~= ARGV[1] then return -1 end
local seconds = 0
if ARGV[2] == '429' then
    local consecutive = math.min(4, (tonumber(redis.call('HGET', KEYS[1], 'limited')) or 0) + 1)
    redis.call('HSET', KEYS[1], 'limited', consecutive)
    seconds = tonumber(ARGV[3])
    if seconds < 0 then seconds = 60 * 2 ^ (consecutive - 1) + tonumber(ARGV[4]) end
    local t = redis.call('TIME')
    local now = t[1] * 1000 + math.floor(t[2] / 1000)
    local untilTime = math.max(tonumber(redis.call('HGET', KEYS[1], 'cooldown')) or 0, now + seconds * 1000)
    redis.call('HSET', KEYS[1], 'cooldown', untilTime)
    redis.call('PEXPIRE', KEYS[1], math.max(redis.call('PTTL', KEYS[1]), untilTime - now + 600000))
elseif ARGV[2] == 'healthy' then
    redis.call('HDEL', KEYS[1], 'limited', 'cooldown')
end
redis.call('HDEL', KEYS[1], 'owner', 'lease')
return seconds
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

    public function finish(string $token, bool $limited = false, ?int $retryAfter = null, bool $healthy = false): int
    {
        try {
            // A pausa, o contador de respostas 429 e a liberação são atômicos.
            $state = $limited ? '429' : ($healthy ? 'healthy' : 'other');
            $wait = $this->redis->eval(self::FINISH, [
                $this->key, $token, $state, $retryAfter ?? -1, $limited && $retryAfter === null ? random_int(0, 5) : 0,
            ], 1);
            if (!is_int($wait) || $wait < 0) throw new RuntimeException('Reserva não reconhecida.');
            return $wait;
        } catch (Throwable) {
            // Não reabre o envio; a reserva expira e o serviço continua protegido.
            error_log('DataJud: não foi possível finalizar a reserva de frequência.');
            return $limited ? ($retryAfter ?? 60) : 0;
        }
    }

    public static function retryAfter(?string $value, ?int $now = null): ?int
    {
        $v = trim($value ?? '');
        if (preg_match('/^[0-9]{1,12}$/D', $v) === 1) return (int) $v;
        if (preg_match('/^[A-Z][a-z]{2}, [0-9]{2} [A-Z][a-z]{2} [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} GMT$/D', $v) !== 1) return null;
        $date = DateTimeImmutable::createFromFormat('!D, d M Y H:i:s T', $v);
        if (!$date || $date->format('D, d M Y H:i:s \\G\\M\\T') !== $v) return null;
        return max(0, $date->getTimestamp() - ($now ?? time()));
    }
}
