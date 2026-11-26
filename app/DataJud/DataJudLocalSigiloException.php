<?php
declare(strict_types=1);

final class DataJudLocalSigiloException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O processo está marcado como sigiloso no cadastro local. A sincronização usa apenas a base pública do DataJud.');
    }

    public function respondForSync(): void
    {
        http_response_code(422);
        echo json_encode(['error' => $this->getMessage(), 'code' => 'datajud_sigilo_local'], JSON_UNESCAPED_UNICODE);
    }
}
