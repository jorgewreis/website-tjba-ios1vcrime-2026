<?php
declare(strict_types=1);

class AuditLogger
{
    /**
     * Log básico de auditoria. Tenta inserir em tabela `audit_log` (se existir),
     * caso contrário registra via error_log.
     * Contexto será serializado em JSON.
     */
    public static function log(PDO $pdo, string $user, string $action, array $context = []): void
    {
        $ctxJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($ctxJson === false) $ctxJson = '{}';

        $sql = "INSERT INTO audit_log (id, user_id, action, context, created_at) VALUES (?, ?, ?, ?, ?)";
        try {
            $id = bin2hex(random_bytes(8));
            $created = (new DateTime('now', new DateTimeZone('America/Bahia')))->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $user, $action, $ctxJson, $created]);
        } catch (Throwable $e) {
            // ignore erros de inserção, mas registra no error_log
            error_log('AuditLogger insert failed: ' . $e->getMessage());
            // fallback: registrar no error_log
            error_log(sprintf('AUDIT [%s] user=%s action=%s context=%s', date('c'), $user, $action, $ctxJson));
        }
    }
}
