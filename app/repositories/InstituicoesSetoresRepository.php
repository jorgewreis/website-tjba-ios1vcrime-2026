<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class InstituicoesSetoresRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'instituicoes_setores')
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
                'SELECT `id`, `instituicao_id`, `nome_setor`, `sigla`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function findByInstituicaoId(string $instituicaoId, bool $onlyActive = false): array
    {
        $instituicaoId = trim($instituicaoId);
        if ($instituicaoId === '') {
            return [];
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `instituicao_id`, `nome_setor`, `sigla`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `instituicao_id` = :instituicao_id%s ORDER BY `nome_setor` ASC',
                $this->table, $onlyActive ? ' AND `ativo` = 1' : ''
            )
        );
        $statement->bindValue(':instituicao_id', $instituicaoId);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(array $row): string
    {
        $instituicaoId = trim((string) ($row['instituicao_id'] ?? ''));
        $nomeSetor = TextFormatter::formatProperName((string) ($row['nome_setor'] ?? ''), 120);
        if ($instituicaoId === '' || $nomeSetor === '') {
            throw new InvalidArgumentException('instituicao_id e nome_setor são obrigatórios.');
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'ISE', 30);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `instituicao_id`, `nome_setor`, `sigla`, `ativo`, `created_at`, `updated_at`) VALUES (:id, :instituicao_id, :nome_setor, :sigla, :ativo, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':instituicao_id', $instituicaoId);
        $statement->bindValue(':nome_setor', $nomeSetor);
        $statement->bindValue(':sigla', trim((string) ($row['sigla'] ?? '')) ?: null);
        $statement->bindValue(':ativo', isset($row['ativo']) ? (int) (bool) $row['ativo'] : 1, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['nome_setor', 'sigla', 'ativo'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if ($column === 'nome_setor') {
                $value = TextFormatter::formatProperName((string) $value, 120);
            } elseif ($column === 'ativo') {
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
}
