<?php
declare(strict_types=1);

/** Mapeia apenas falhas conhecidas da fonte, sem propagar URL, chave ou resposta do CNJ. */
final class DataJudUpstreamException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly ?int $retryAfter = null)
    {
        parent::__construct(match ($reason) {
            'local_throttled' => 'Aguarde ' . ($retryAfter ?? 5) . ' s antes de outra consulta ao DataJud.',
            'pace_unavailable' => 'A consulta foi pausada porque o controle de frequência está indisponível.',
            'throttled' => 'O DataJud limitou ou rejeitou temporariamente a consulta. Aguarde e tente novamente.',
            'incomplete' => 'O DataJud retornou uma consulta incompleta. Aguarde e tente novamente.',
            'authorization' => 'O DataJud recusou a autenticação da integração.',
            'transport' => 'Não foi possível conectar ao DataJud. Aguarde e tente novamente.',
            default => 'O DataJud está temporariamente indisponível. Aguarde e tente novamente.',
        });
    }

    public function httpStatus(): int
    {
        return match ($this->reason) {
            'authorization' => 502,
            'local_throttled' => 429,
            default => 503,
        };
    }

    public function emitRetryHeader(): void
    {
        if (in_array($this->reason, ['throttled', 'local_throttled', 'pace_unavailable'], true)) {
            header('Retry-After: ' . ($this->retryAfter ?? 60));
        }
    }

    public function respondForSync(): void
    {
        http_response_code($this->httpStatus());
        $this->emitRetryHeader();
        echo json_encode([
            'error' => $this->getMessage(),
            'code' => 'datajud_' . $this->reason,
        ], JSON_UNESCAPED_UNICODE);
    }
}
