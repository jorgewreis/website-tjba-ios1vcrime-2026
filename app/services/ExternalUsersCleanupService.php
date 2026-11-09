<?php
declare(strict_types=1);

/** Exclui contas exclusivamente externas após 30 dias de inatividade. */
final class ExternalUsersCleanupService
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /** @return array{deleted_users:int,failed_users:int} */
    public function purgeInactiveStudents(): array
    {
        $sql = "SELECT u.id_usuario FROM usuarios u
                WHERE COALESCE(u.ultimo_acesso, u.created_at) <= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND EXISTS (SELECT 1 FROM usuario_perfil up WHERE up.usuario = u.id_usuario AND up.perfil IN ('ESTUDANTE', 'CONSULTOR'))
                  AND NOT EXISTS (SELECT 1 FROM usuario_perfil up WHERE up.usuario = u.id_usuario AND (up.perfil IS NULL OR TRIM(up.perfil) = '' OR up.perfil NOT IN ('ESTUDANTE', 'CONSULTOR')))";
        $ids = $this->connection->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $this->connection->beginTransaction();
                // Bloqueia e reavalia os dados: o usuário pode ter acessado o sistema
                // ou recebido outro perfil desde a seleção inicial.
                $stmt = $this->connection->prepare('SELECT cpf, ultimo_acesso, created_at FROM usuarios WHERE id_usuario = ? AND COALESCE(ultimo_acesso, created_at) <= DATE_SUB(NOW(), INTERVAL 30 DAY) FOR UPDATE');
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $profiles = $this->connection->prepare('SELECT perfil FROM usuario_perfil WHERE usuario = ? FOR UPDATE');
                $profiles->execute([$id]);
                $values = $profiles->fetchAll(PDO::FETCH_COLUMN);
                if (!$user || $values === [] || count(array_filter($values, static fn ($p): bool => !in_array($p, ['ESTUDANTE', 'CONSULTOR'], true))) > 0) {
                    $this->connection->rollBack();
                    continue;
                }

                foreach (['usuario_setor', 'usuario_perfil', 'recuperacao_senha', 'historico_senha'] as $table) {
                    $this->connection->prepare('DELETE FROM ' . $table . ' WHERE usuario = ?')->execute([$id]);
                }
                // Não apagar tentativas vinculadas ao CPF: podem ser compartilhadas
                // por outro cadastro existente e são úteis à segurança do login.
                $stmt = $this->connection->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
                $stmt->execute([$id]);
                $count = $stmt->rowCount();
                $this->connection->commit();
                $deleted += $count;
            } catch (Throwable $exception) {
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }
                $failed++;
                error_log('Falha na exclusão de conta externa inativa: ' . $exception->getMessage());
            }
        }

        return ['deleted_users' => $deleted, 'failed_users' => $failed];
    }
}
