<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProcessoPrioridadesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'processo_prioridades')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function insert(string $processoId, string $prioridade): string
    {
        $processoId = trim($processoId);
        $prioridade = trim($prioridade);
        if ($processoId === '' || $prioridade === '') {
            throw new InvalidArgumentException('processo_id e prioridade são obrigatórios.');
        }

        $existing = $this->findOne($processoId, $prioridade);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PRP', 30);
        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `prioridade`, `created_at`) VALUES (:id, :processo_id, :prioridade, NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':prioridade', $prioridade);
        $statement->execute();

        return $id;
    }

    public function findByProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `processo_id`, `prioridade`, `created_at` FROM `%s` WHERE `processo_id` = :processo_id ORDER BY `prioridade` ASC', $this->table)
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    public function deleteByProcessoId(string $processoId): void
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return;
        }

        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `processo_id` = :processo_id', $this->table)
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->execute();
    }

    private function findOne(string $processoId, string $prioridade): ?array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `processo_id`, `prioridade`, `created_at` FROM `%s` WHERE `processo_id` = :processo_id AND `prioridade` = :prioridade LIMIT 1', $this->table)
        );
        $statement->bindValue(':processo_id', $processoId);
        $statement->bindValue(':prioridade', $prioridade);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }
}
