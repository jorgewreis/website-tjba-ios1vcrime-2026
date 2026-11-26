<?php
declare(strict_types=1);

/** Fila leve para atualizar classe e assuntos sem segurar a requisição da tela. */
final class ProcessoDataJudSyncQueue
{
    public static function enqueueBestEffort(PDO $db, string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') return;
        try {
            // Reset completed/failed work on a new process use. Keep a job that
            // is currently claimed by another worker intact. MySQL evaluates
            // duplicate-key assignments left-to-right, so all checks of the
            // previous status are deliberately before changing `status`.
            $statement = $db->prepare("INSERT INTO processo_datajud_sync_queue (processo_id, status, tentativas, proxima_tentativa_em, solicitado_em, created_at, updated_at) VALUES (:id, 'pendente', 0, NOW(), NOW(6), NOW(), NOW()) ON DUPLICATE KEY UPDATE tentativas = IF(status = 'processando', tentativas, 0), proxima_tentativa_em = IF(status = 'processando', proxima_tentativa_em, NOW()), processando_em = IF(status = 'processando', processando_em, NULL), solicitado_em = NOW(6), updated_at = NOW(), status = IF(status = 'processando', status, 'pendente')");
            $statement->execute([':id' => $processoId]);
        } catch (Throwable $e) {
            // A fila nunca deve interromper o cadastro ou a leitura da tela.
            error_log('processo datajud enqueue indisponivel: ' . get_class($e));
        }
    }
}
