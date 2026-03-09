<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';

final class PartesRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'partes')
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
            sprintf('SELECT `id`, `cpf`, `nome_completo`, `sexo`, `data_nascimento`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findByCpf(string $cpf): ?array
    {
        $cpf = $this->normalizeCpf($cpf);
        if ($cpf === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `cpf`, `nome_completo`, `sexo`, `data_nascimento`, `created_at`, `updated_at` FROM `%s` WHERE `cpf` = :cpf LIMIT 1', $this->table)
        );
        $statement->bindValue(':cpf', $cpf);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function insert(array $row): string
    {
        $cpf = $this->normalizeCpf((string) ($row['cpf'] ?? ''));
        $nomeCompleto = TextFormatter::formatProperName((string) ($row['nome_completo'] ?? ''), 255);
        $sexo = trim((string) ($row['sexo'] ?? ''));
        $dataNascimento = trim((string) ($row['data_nascimento'] ?? ''));

        if ($cpf === '' || $nomeCompleto === '' || $sexo === '' || $dataNascimento === '') {
            throw new InvalidArgumentException('CPF, nome completo, sexo e data de nascimento são obrigatórios para cadastrar a parte.');
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PRT', 30);
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `cpf`, `nome_completo`, `sexo`, `data_nascimento`, `created_at`, `updated_at`) VALUES (:id, :cpf, :nome_completo, :sexo, :data_nascimento, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':cpf', $cpf);
        $statement->bindValue(':nome_completo', $nomeCompleto);
        $statement->bindValue(':sexo', $sexo);
        $statement->bindValue(':data_nascimento', $dataNascimento);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['cpf', 'nome_completo', 'sexo', 'data_nascimento'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if ($column === 'cpf') {
                $value = $this->normalizeCpf((string) $value);
            } elseif ($column === 'nome_completo') {
                $value = TextFormatter::formatProperName((string) $value, 255);
            } else {
                $value = trim((string) $value);
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

    public function ensureParte(string $cpf, string $nomeCompleto, string $sexo, string $dataNascimento): string
    {
        $cpf = $this->normalizeCpf($cpf);
        $nomeCompleto = TextFormatter::formatProperName($nomeCompleto, 255);
        $sexo = trim($sexo);
        $dataNascimento = trim($dataNascimento);

        if ($cpf === '' || $nomeCompleto === '' || $sexo === '' || $dataNascimento === '') {
            throw new InvalidArgumentException('CPF, nome completo, sexo e data de nascimento são obrigatórios.');
        }

        $existing = $this->findByCpf($cpf);
        if ($existing !== null) {
            $this->update((string) $existing['id'], [
                'nome_completo' => $nomeCompleto,
                'sexo' => $sexo,
                'data_nascimento' => $dataNascimento,
            ]);
            return (string) $existing['id'];
        }

        return $this->insert([
            'cpf' => $cpf,
            'nome_completo' => $nomeCompleto,
            'sexo' => $sexo,
            'data_nascimento' => $dataNascimento,
        ]);
    }

    private function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', trim($cpf));
    }
}
