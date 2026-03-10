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

    public function findByNomeCompleto(string $nomeCompleto): ?array
    {
        $nomeCompleto = TextFormatter::formatProperName(trim($nomeCompleto), 255);
        if ($nomeCompleto === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `cpf`, `nome_completo`, `sexo`, `data_nascimento`, `created_at`, `updated_at` FROM `%s` WHERE `nome_completo` = :nome_completo LIMIT 1', $this->table)
        );
        $statement->bindValue(':nome_completo', $nomeCompleto);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    /** @return list<array<string, mixed>> */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);

        $sql = sprintf(
            'SELECT p.`id`, p.`cpf`, p.`nome_completo`, p.`sexo`, p.`data_nascimento`, p.`created_at`, p.`updated_at` FROM `%s` p %s %s ORDER BY p.`nome_completo` ASC LIMIT :limit OFFSET :offset',
            $this->table,
            $joinClause,
            $whereClause ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        [$whereClause, $params, $joinClause] = $this->buildListFilter($filters);
        $sql = sprintf('SELECT COUNT(DISTINCT p.`id`) AS total FROM `%s` p %s %s', $this->table, $joinClause, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: string} */
    private function buildListFilter(array $filters): array
    {
        $conditions = [];
        $params = [];
        $joinClause = '';

        $nome = trim((string) ($filters['nome'] ?? ''));
        if ($nome !== '') {
            $conditions[] = 'p.`nome_completo` LIKE :nome';
            $params[':nome'] = '%' . $nome . '%';
        }

        $cpf = $this->normalizeCpf((string) ($filters['cpf'] ?? ''));
        if ($cpf !== '') {
            $conditions[] = 'p.`cpf` = :cpf';
            $params[':cpf'] = $cpf;
        }

        $instituicaoId = trim((string) ($filters['instituicao_id'] ?? ''));
        if ($instituicaoId !== '') {
            $joinClause = ' INNER JOIN `partes_instituicoes` pi ON pi.`parte_id` = p.`id` AND pi.`instituicao_id` = :instituicao_id ';
            $params[':instituicao_id'] = $instituicaoId;
        }

        $where = $conditions === [] ? '' : implode(' AND ', $conditions);
        return [$where, $params, $joinClause];
    }

    public function insert(array $row): string
    {
        $nomeCompleto = TextFormatter::formatProperName((string) ($row['nome_completo'] ?? ''), 255);
        if ($nomeCompleto === '') {
            throw new InvalidArgumentException('Nome completo é obrigatório para cadastrar a parte.');
        }

        $cpf = $this->normalizeCpf((string) ($row['cpf'] ?? ''));
        $sexo = trim((string) ($row['sexo'] ?? ''));
        $dataNascimento = trim((string) ($row['data_nascimento'] ?? ''));

        if ($cpf !== '') {
            $existenteCpf = $this->findByCpf($cpf);
            if ($existenteCpf !== null) {
                throw new InvalidArgumentException('Já existe pessoa cadastrada com este CPF.');
            }
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
        $statement->bindValue(':cpf', $cpf === '' ? null : $cpf);
        $statement->bindValue(':nome_completo', $nomeCompleto);
        $statement->bindValue(':sexo', $sexo === '' ? null : $sexo);
        $statement->bindValue(':data_nascimento', $dataNascimento === '' ? null : $dataNascimento);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $id = trim($id);
        $allowed = ['cpf', 'nome_completo', 'sexo', 'data_nascimento'];
        $set = [];
        $params = [':id' => $id];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if ($column === 'cpf') {
                $value = $this->normalizeCpf((string) $value);
                $value = $value === '' ? null : $value;
                if ($value !== null && $value !== '') {
                    $existente = $this->findByCpf($value);
                    if ($existente !== null && (string) $existente['id'] !== $id) {
                        throw new InvalidArgumentException('Já existe outra pessoa cadastrada com este CPF.');
                    }
                }
            } elseif ($column === 'nome_completo') {
                $value = TextFormatter::formatProperName((string) $value, 255);
            } else {
                $value = trim((string) $value);
                if (in_array($column, ['sexo', 'data_nascimento'], true)) {
                    $value = $value === '' ? null : $value;
                }
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
