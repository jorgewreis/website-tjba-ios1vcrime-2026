<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class ClassesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'classes')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /** Retorna o id da classe (existente ou recém-criada). */
    public function ensureClass(string $codigo, string $nome): string
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $codigo));
        if ($codigo === '') {
            throw new InvalidArgumentException('Código da classe não pode ser vazio.');
        }

        $existing = $this->findByCodigo($codigo);
        if ($existing !== null) {
            return (string) $existing['id'];
        }

        $nome = trim($nome);
        $nome = $nome === '' ? $codigo : TextFormatter::formatProperName($nome, 200);
        if ($nome === '') {
            $nome = $codigo;
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'CLA',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `codigo`, `nome`, `created_at`, `updated_at`) VALUES (:id, :codigo, :nome, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':codigo', $codigo);
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        return $id;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $codigo));
        if ($codigo === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `codigo`, `nome`, `created_at`, `updated_at` FROM `%s` WHERE `codigo` = :codigo LIMIT 1', $this->table)
        );
        $statement->bindValue(':codigo', $codigo);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findById(string $id): ?array
    {
        $id = trim((string) $id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `codigo`, `nome`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }
}
