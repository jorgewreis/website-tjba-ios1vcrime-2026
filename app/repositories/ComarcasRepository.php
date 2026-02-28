<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ComarcasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'comarcas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findByTribunalUfNome(string $tribunalId, string $uf, string $nome): ?array
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if ($tribunalId === '' || strlen($uf) !== 2 || $nome === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s` WHERE `tribunal_id` = :tribunal_id AND `uf` = :uf AND `nome` = :nome LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':tribunal_id', $tribunalId);
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** Busca por UF e nome da comarca (cidade), sem tribunal. Útil quando há um tribunal padrão por UF. */
    public function findByUfNome(string $uf, string $nome): ?array
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if (strlen($uf) !== 2 || $nome === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at` FROM `%s` WHERE `uf` = :uf AND `nome` = :nome LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** Retorna o id da comarca (existente ou recém-criada). cidade e status_cidade são int. */
    public function ensureComarca(string $tribunalId, string $uf, string $nome, int $cidade = 0, int $statusCidade = 0): string
    {
        $uf = strtoupper(trim($uf));
        $nome = trim($nome);
        if ($tribunalId === '' || strlen($uf) !== 2 || $nome === '') {
            throw new InvalidArgumentException('Tribunal, UF (2 caracteres) e nome da comarca são obrigatórios.');
        }

        $existing = $this->findByTribunalUfNome($tribunalId, $uf, $nome);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'COM',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `tribunal_id`, `uf`, `nome`, `cidade`, `status_cidade`, `created_at`, `updated_at`) VALUES (:id, :tribunal_id, :uf, :nome, :cidade, :status_cidade, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tribunal_id', $tribunalId);
        $statement->bindValue(':uf', $uf);
        $statement->bindValue(':nome', $nome);
        $statement->bindValue(':cidade', $cidade, PDO::PARAM_INT);
        $statement->bindValue(':status_cidade', $statusCidade, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }
}
