<?php
declare(strict_types=1);

/** Fila leve para atualizar classe e assuntos sem segurar a requisição da tela. */
final class ProcessoDataJudSyncQueue
{
    public static function enqueueFirstRegistration(PDO $db, string $processoId): void
    {
        $processoId = trim($processoId);
        $requestedBy = trim((string) ($_SESSION['id_usuario'] ?? ''));
        if ($processoId === '' || $requestedBy === '') return;
        try {
            // Uma linha para o primeiro cadastro. Um duplicado nunca reabre o trabalho
            // nem troca a identidade de quem solicitou a primeira consulta.
            $statement = $db->prepare("INSERT INTO processo_datajud_sync_queue
                (processo_id, requested_by, status, tentativas, proxima_tentativa_em, solicitado_em, created_at, updated_at)
                VALUES (:id, :requested_by, 'cadastro_inicial', 0, NOW(), NOW(6), NOW(), NOW())
                ON DUPLICATE KEY UPDATE id = id");
            $statement->execute([':id' => $processoId, ':requested_by' => $requestedBy]);
        } catch (Throwable $e) {
            // A fila nunca deve interromper o cadastro.
            error_log('processo datajud enqueue indisponivel: ' . get_class($e));
        }
    }
}
