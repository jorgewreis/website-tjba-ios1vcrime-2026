<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class PartesEnderecosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'partes_enderecos')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `parte_id`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `principal`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function findByParteId(string $parteId): array
    {
        $parteId = trim($parteId);
        if ($parteId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `parte_id`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `principal`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `parte_id` = :parte_id ORDER BY `principal` DESC, `created_at` ASC',
                $this->table
            )
        );
        $statement->bindValue(':parte_id', $parteId);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $row): string
    {
        $parteId = trim((string) ($row['parte_id'] ?? ''));
        if ($parteId === '') {
            throw new InvalidArgumentException('parte_id é obrigatório.');
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PEN', 30);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `parte_id`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `principal`, `ativo`, `created_at`, `updated_at`) VALUES (:id, :parte_id, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :uf, :principal, :ativo, NOW(), NOW())',
                $this->table
            )
        );
        $this->bindRow($statement, $id, $parteId, $row);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'principal', 'ativo'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if (in_array($column, ['principal', 'ativo'], true)) {
                $value = (int) (bool) $value;
            } else {
                $value = trim((string) $value);
                $value = $value === '' ? null : $value;
            }
            $set[] = sprintf('`%s` = :%s', $column, $column);
            $params[':' . $column] = $value;
        }

        if (empty($set)) {
            return false;
        }

        $set[] = '`updated_at` = NOW()';
        $statement = $this->connection->prepare(
            sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $set))
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    private function bindRow(\PDOStatement $statement, string $id, string $parteId, array $row): void
    {
        $statement->bindValue(':id', $id);
        $statement->bindValue(':parte_id', $parteId);
        $statement->bindValue(':cep', trim((string) ($row['cep'] ?? '')) ?: null);
        $statement->bindValue(':logradouro', trim((string) ($row['logradouro'] ?? '')) ?: null);
        $statement->bindValue(':numero', trim((string) ($row['numero'] ?? '')) ?: null);
        $statement->bindValue(':complemento', trim((string) ($row['complemento'] ?? '')) ?: null);
        $statement->bindValue(':bairro', trim((string) ($row['bairro'] ?? '')) ?: null);
        $statement->bindValue(':cidade', trim((string) ($row['cidade'] ?? '')) ?: null);
        $statement->bindValue(':uf', trim((string) ($row['uf'] ?? '')) ?: null);
        $statement->bindValue(':principal', isset($row['principal']) ? (int) (bool) $row['principal'] : 0, PDO::PARAM_INT);
        $statement->bindValue(':ativo', isset($row['ativo']) ? (int) (bool) $row['ativo'] : 1, PDO::PARAM_INT);
    }
}
