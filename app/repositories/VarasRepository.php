<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class VarasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'varas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /** Retorna a primeira vara da comarca (qualquer uma). */
    public function findFirstByComarca(string $comarcaId): ?array
    {
        if ($comarcaId === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `comarca_id`, `codigo`, `nome`, `email`, `telefone`, `created_at`, `updated_at` FROM `%s` WHERE `comarca_id` = :comarca_id ORDER BY `nome` ASC LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':comarca_id', $comarcaId);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findByComarcaAndCodigo(string $comarcaId, string $codigo): ?array
    {
        if ($comarcaId === '') {
            return null;
        }

        $codigo = trim($codigo);
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `comarca_id`, `codigo`, `nome`, `email`, `telefone`, `created_at`, `updated_at` FROM `%s` WHERE `comarca_id` = :comarca_id AND `codigo` = :codigo LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':comarca_id', $comarcaId);
        $statement->bindValue(':codigo', $codigo);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** Retorna o id da vara (existente ou recém-criada). codigo, email, telefone opcionais. */
    public function ensureVara(string $comarcaId, string $nome, ?string $codigo = null, ?string $email = null, ?string $telefone = null): string
    {
        if ($comarcaId === '') {
            throw new InvalidArgumentException('Comarca é obrigatória para a vara.');
        }

        $nome = trim($nome);
        if ($nome === '') {
            $nome = '1ª Vara';
        }

        if ($codigo !== null && $codigo !== '') {
            $existing = $this->findByComarcaAndCodigo($comarcaId, $codigo);
            if ($existing !== null) {
                return (string) $existing['id'];
            }
        }

        $existing = $this->findFirstByComarca($comarcaId);
        if ($existing !== null && ($codigo === null || $codigo === '')) {
            return (string) $existing['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'VAR',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `comarca_id`, `codigo`, `nome`, `email`, `telefone`, `created_at`, `updated_at`) VALUES (:id, :comarca_id, :codigo, :nome, :email, :telefone, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':comarca_id', $comarcaId);
        $statement->bindValue(':codigo', $codigo ?? '', PDO::PARAM_STR);
        $statement->bindValue(':nome', $nome);
        $statement->bindValue(':email', $email ?? '', PDO::PARAM_STR);
        $statement->bindValue(':telefone', $telefone ?? '', PDO::PARAM_STR);
        $statement->execute();

        return $id;
    }
}
