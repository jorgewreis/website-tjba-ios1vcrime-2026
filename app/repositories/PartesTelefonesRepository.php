<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class PartesTelefonesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'partes_telefones')
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
                'SELECT `id`, `parte_id`, `tipo`, `numero`, `whatsapp`, `principal`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
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
                'SELECT `id`, `parte_id`, `tipo`, `numero`, `whatsapp`, `principal`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `parte_id` = :parte_id ORDER BY `principal` DESC, `created_at` ASC',
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
        $numero = trim((string) ($row['numero'] ?? ''));
        if ($parteId === '' || $numero === '') {
            throw new InvalidArgumentException('parte_id e numero são obrigatórios.');
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'TEL', 30);
        }

        $tipoVal = trim((string) ($row['tipo'] ?? ''));
        $tipoBind = $tipoVal !== '' ? $tipoVal : null;

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `parte_id`, `tipo`, `numero`, `whatsapp`, `principal`, `ativo`, `created_at`, `updated_at`) VALUES (:id, :parte_id, :tipo, :numero, :whatsapp, :principal, :ativo, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':parte_id', $parteId);
        if ($tipoBind === null) {
            $statement->bindValue(':tipo', null, PDO::PARAM_NULL);
        } else {
            $statement->bindValue(':tipo', $tipoBind);
        }
        $statement->bindValue(':numero', $numero);
        $statement->bindValue(':whatsapp', isset($row['whatsapp']) ? (int) (bool) $row['whatsapp'] : 0, PDO::PARAM_INT);
        $statement->bindValue(':principal', isset($row['principal']) ? (int) (bool) $row['principal'] : 0, PDO::PARAM_INT);
        $statement->bindValue(':ativo', isset($row['ativo']) ? (int) (bool) $row['ativo'] : 1, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['tipo', 'numero', 'whatsapp', 'principal', 'ativo'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if (in_array($column, ['whatsapp', 'principal', 'ativo'], true)) {
                $value = (int) (bool) $value;
            } else {
                $value = trim((string) $value);
                $value = $value === '' && $column !== 'numero' ? null : $value;
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
}
