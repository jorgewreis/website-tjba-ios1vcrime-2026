<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class SalaMidiasSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_EM_ANDAMENTO = 'em_andamento';
    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_CANCELADA = 'cancelada';

    public function __construct(PDO $connection, string $table = 'sala_midias_subtarefa')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'SMS', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`,`midia_id`,`codigo`,`titulo`,`sistema`,`ordem`,`status`,`pontos_calculados`,`dados_json`,`created_at`)
             VALUES (:id,:midia_id,:codigo,:titulo,:sistema,:ordem,:status,:pontos,:dados_json,NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':midia_id', $row['midia_id']);
        $statement->bindValue(':codigo', $row['codigo']);
        $statement->bindValue(':titulo', $row['titulo']);
        $statement->bindValue(':sistema', $row['sistema']);
        $statement->bindValue(':ordem', (int) $row['ordem'], PDO::PARAM_INT);
        $statement->bindValue(':status', $row['status'] ?? self::STATUS_PENDENTE);
        $statement->bindValue(':pontos', isset($row['pontos_calculados']) ? (float) $row['pontos_calculados'] : 0.0);
        $statement->bindValue(':dados_json', $row['dados_json'] ?? null);
        $statement->execute();

        return $id;
    }

    public function findByMidiaId(string $midiaId): array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `midia_id` = :midia_id ORDER BY `ordem` ASC', $this->table)
        );
        $statement->bindValue(':midia_id', $midiaId);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(string $id): ?array
    {
        $statement = $this->connection->prepare(sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findByMidiaIdAndOrdem(string $midiaId, int $ordem): ?array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `midia_id` = :midia_id AND `ordem` = :ordem LIMIT 1', $this->table)
        );
        $statement->bindValue(':midia_id', $midiaId);
        $statement->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function updateStatus(
        string $id,
        string $status,
        ?float $pontosCalculados = null,
        ?string $dadosJson = null,
        ?string $finalizadoPor = null
    ): bool {
        $sql = sprintf(
            'UPDATE `%s`
             SET `status` = :status,
                 `pontos_calculados` = COALESCE(:pontos, `pontos_calculados`),
                 `dados_json` = COALESCE(:dados_json, `dados_json`),
                 `finalizado_por` = :finalizado_por,
                 `finalizado_em` = CASE WHEN :status = \'concluida\' THEN NOW() ELSE `finalizado_em` END,
                 `updated_at` = NOW()
             WHERE `id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':pontos', $pontosCalculados);
        $statement->bindValue(':dados_json', $dadosJson);
        $statement->bindValue(':finalizado_por', $finalizadoPor);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function deleteByMidiaId(string $midiaId): void
    {
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `midia_id` = :midia_id', $this->table));
        $statement->bindValue(':midia_id', $midiaId);
        $statement->execute();
    }
}
