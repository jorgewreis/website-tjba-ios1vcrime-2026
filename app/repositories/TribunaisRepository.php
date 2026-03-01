<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class TribunaisRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'tribunais')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findBySigla(string $sigla): ?array
    {
        $sigla = strtoupper(trim($sigla));
        if ($sigla === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `sigla`, `nome`, `created_at`, `updated_at` FROM `%s` WHERE `sigla` = :sigla LIMIT 1', $this->table)
        );
        $statement->bindValue(':sigla', $sigla);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** Retorna o id do tribunal (existente ou recém-criado). */
    public function ensureTribunal(string $sigla, string $nome): string
    {
        $sigla = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '', $sigla)));
        if ($sigla === '') {
            throw new InvalidArgumentException('Sigla do tribunal não pode ser vazia.');
        }

        $existing = $this->findBySigla($sigla);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $nome = trim($nome);
        if ($nome === '') {
            $nome = 'Tribunal ' . $sigla;
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'TRI',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `sigla`, `nome`, `created_at`, `updated_at`) VALUES (:id, :sigla, :nome, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':sigla', $sigla);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        return $id;
    }
}
