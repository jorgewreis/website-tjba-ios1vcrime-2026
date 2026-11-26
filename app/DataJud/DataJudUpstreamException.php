<?php
declare(strict_types=1);

/** Mapeia apenas falhas conhecidas da fonte, sem propagar URL, chave ou resposta do CNJ. */
final class DataJudUpstreamException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'throttled' => 'O DataJud limitou ou rejeitou temporariamente a consulta. Aguarde e tente novamente.',
            'incomplete' => 'O DataJud retornou uma consulta incompleta. Aguarde e tente novamente.',
            'authorization' => 'O DataJud recusou a autenticação da integração.',
            'transport' => 'Não foi possível conectar ao DataJud. Aguarde e tente novamente.',
            default => 'O DataJud está temporariamente indisponível. Aguarde e tente novamente.',
        });
    }

    public function respondForSync(): void
    {
        $status = $this->reason === 'authorization' ? 502 : 503;
        http_response_code($status);
        if ($this->reason === 'throttled') header('Retry-After: 60');
        echo json_encode([
            'error' => $this->getMessage(),
            'code' => 'datajud_' . $this->reason,
        ], JSON_UNESCAPED_UNICODE);
    }
}
