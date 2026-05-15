<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class AssuntosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'assuntos')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function normalizeCodigo(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $codigo));
    }

    /** Retorna o id do assunto (existente ou recém-criado). */
    public function ensureAssunto(string $codigo, string $descricao): string
    {
        $codigo = self::normalizeCodigo($codigo);
        if ($codigo === '') {
            throw new InvalidArgumentException('Código do assunto não pode ser vazio.');
        }

        $descricaoNormalizada = trim(TextFormatter::formatProperName(trim($descricao), 500));
        if ($descricaoNormalizada === '') {
            $descricaoNormalizada = $codigo;
        }

        $existing = $this->findByCodigo($codigo);
        if ($existing !== null) {
            $id = (string) ($existing['id'] ?? '');
            $descricaoAtual = trim((string) ($existing['descricao'] ?? ''));
            if ($id !== '' && $descricaoNormalizada !== '' && $descricaoAtual !== $descricaoNormalizada) {
                $this->updateDescricao($id, $descricaoNormalizada);
            }

            return $id;
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'ASU',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `codigo`, `descricao`, `created_at`, `updated_at`) VALUES (:id, :codigo, :descricao, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':codigo', $codigo);
        $statement->bindValue(':descricao', $descricaoNormalizada);
        $statement->execute();

        return $id;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $codigo = self::normalizeCodigo($codigo);
        if ($codigo === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `codigo`, `descricao`, `created_at`, `updated_at` FROM `%s` WHERE `codigo` = :codigo LIMIT 1', $this->table)
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
                'SELECT `id`, `codigo`, `descricao`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    private function updateDescricao(string $id, string $descricao): void
    {
        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `descricao` = :descricao, `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':descricao', $descricao);
        $statement->execute();
    }
}
