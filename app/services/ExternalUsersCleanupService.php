<?php
declare(strict_types=1);

final class ExternalUsersCleanupService
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Remove estudantes sem acesso por mais de N dias.
     * Critério: perfil ESTUDANTE e COALESCE(ultimo_acesso, created_at) anterior ao limite.
     *
     * @return array{deleted_users:int, deleted_students:int}
     */
    public function purgeInactiveStudents(int $daysWithoutAccess = 30): array
    {
        if ($daysWithoutAccess < 1) {
            $daysWithoutAccess = 30;
        }

        $sql = "
            SELECT u.id_usuario, u.cpf
            FROM usuarios u
            INNER JOIN usuario_perfil up ON up.usuario = u.id_usuario AND up.perfil = 'ESTUDANTE'
            WHERE COALESCE(u.ultimo_acesso, u.created_at) IS NOT NULL
              AND COALESCE(u.ultimo_acesso, u.created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':days', $daysWithoutAccess, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return ['deleted_users' => 0, 'deleted_students' => 0];
        }

        $userIds = [];
        $cpfs = [];
        foreach ($rows as $row) {
            $userId = trim((string) ($row['id_usuario'] ?? ''));
            $cpf = trim((string) ($row['cpf'] ?? ''));
            if ($userId !== '') {
                $userIds[] = $userId;
            }
            if ($cpf !== '') {
                $cpfs[] = $cpf;
            }
        }

        if ($userIds === [] || $cpfs === []) {
            return ['deleted_users' => 0, 'deleted_students' => 0];
        }

        $userIds = array_values(array_unique($userIds));
        $cpfs = array_values(array_unique($cpfs));

        $deletedUsers = 0;
        $deletedStudents = 0;

        $this->connection->beginTransaction();
        try {
            $this->deleteByIds('usuario_setor', 'usuario', $userIds);
            $this->deleteByIds('usuario_perfil', 'usuario', $userIds);
            $this->deleteByIds('recuperacao_senha', 'usuario', $userIds);
            $this->deleteByIds('historico_senha', 'usuario', $userIds);
            $this->deleteByIds('tentativas_login', 'cpf', $cpfs);

            $deletedStudents = $this->deleteByIds('estudantes', 'cpf', $cpfs);
            $deletedUsers = $this->deleteByIds('usuarios', 'id_usuario', $userIds);

            $this->connection->commit();
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
        }

        return ['deleted_users' => $deletedUsers, 'deleted_students' => $deletedStudents];
    }

    /**
     * @param array<int, string> $values
     */
    private function deleteByIds(string $table, string $column, array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = sprintf('DELETE FROM %s WHERE %s IN (%s)', $table, $column, $placeholders);
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }
}
