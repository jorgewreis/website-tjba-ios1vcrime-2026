<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/TextFormatter.php';
require_once __DIR__ . '/../../config/security.php';

final class InstituicoesPublicasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'instituicoes_publicas')
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
                'SELECT `id`, `nome`, `nome_fantasia`, `cnpj`, `observacoes`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findByNome(string $nome): ?array
    {
        $nome = $this->normalizeNomeInstituicao($nome);
        if ($nome === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `nome`, `nome_fantasia`, `cnpj`, `observacoes`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `nome` = :nome LIMIT 1', $this->table)
        );
        $statement->bindValue(':nome', $nome);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function findByCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D+/', '', trim((string) $cnpj));
        if ($cnpj === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `nome`, `nome_fantasia`, `cnpj`, `observacoes`, `ativo`, `created_at`, `updated_at` FROM `%s` WHERE `cnpj` = :cnpj LIMIT 1', $this->table)
        );
        $statement->bindValue(':cnpj', $cnpj);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters);

        $sql = sprintf(
            'SELECT `id`, `nome`, `nome_fantasia`, `cnpj`, `observacoes`, `ativo`, `created_at`, `updated_at` FROM `%s` %s ORDER BY `nome` ASC LIMIT :limit OFFSET :offset',
            $this->table,
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
        [$whereClause, $params] = $this->buildFilterClause($filters);
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s` %s', $this->table, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function insert(array $row): string
    {
        $nome = $this->normalizeNomeInstituicao((string) ($row['nome'] ?? ''));
        if ($nome === '') {
            throw new InvalidArgumentException('Nome da instituição é obrigatório.');
        }

        $existenteNome = $this->findByNome($nome);
        if ($existenteNome !== null) {
            throw new InvalidArgumentException('Já existe instituição cadastrada com este nome.');
        }

        $cnpj = preg_replace('/\D+/', '', (string) ($row['cnpj'] ?? ''));
        if ($cnpj !== '') {
            if (!Security::validarCNPJ($cnpj)) {
                throw new InvalidArgumentException('CNPJ inválido. Verifique os dígitos.');
            }
            $existenteCnpj = $this->findByCnpj($cnpj);
            if ($existenteCnpj !== null) {
                throw new InvalidArgumentException('Já existe instituição cadastrada com este CNPJ.');
            }
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'INS', 30);
        }

        $nomeFantasia = (string) ($row['nome_fantasia'] ?? '');
        $nomeFantasia = $nomeFantasia !== '' ? $this->normalizeSiglaInstituicao(trim($nomeFantasia)) : '';
        $observacoes = trim((string) ($row['observacoes'] ?? ''));
        $ativo = isset($row['ativo']) ? (int) (bool) $row['ativo'] : 1;

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `nome`, `nome_fantasia`, `cnpj`, `observacoes`, `ativo`, `created_at`, `updated_at`) VALUES (:id, :nome, :nome_fantasia, :cnpj, :observacoes, :ativo, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':nome', $nome);
        $statement->bindValue(':nome_fantasia', $nomeFantasia === '' ? null : $nomeFantasia);
        $statement->bindValue(':cnpj', $cnpj === '' ? null : $cnpj);
        $statement->bindValue(':observacoes', $observacoes === '' ? null : $observacoes);
        $statement->bindValue(':ativo', $ativo, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $payload): bool
    {
        $id = trim($id);
        $allowed = ['nome', 'nome_fantasia', 'cnpj', 'observacoes', 'ativo'];
        $set = [];
        $params = [':id' => $id];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $value = $payload[$column];
            if ($column === 'nome') {
                $value = $this->normalizeNomeInstituicao((string) $value);
                if ($value !== '') {
                    $existente = $this->findByNome($value);
                    if ($existente !== null && (string) $existente['id'] !== $id) {
                        throw new InvalidArgumentException('Já existe outra instituição cadastrada com este nome.');
                    }
                }
            } elseif ($column === 'cnpj') {
                $value = preg_replace('/\D+/', '', (string) $value);
                $value = $value === '' ? null : $value;
                if ($value !== null && $value !== '') {
                    if (!Security::validarCNPJ($value)) {
                        throw new InvalidArgumentException('CNPJ inválido. Verifique os dígitos.');
                    }
                    $existente = $this->findByCnpj($value);
                    if ($existente !== null && (string) $existente['id'] !== $id) {
                        throw new InvalidArgumentException('Já existe outra instituição cadastrada com este CNPJ.');
                    }
                }
            } elseif ($column === 'nome_fantasia') {
                $value = trim((string) $value);
                $value = $value === '' ? null : $this->normalizeSiglaInstituicao($value);
            } elseif ($column === 'ativo') {
                $value = (int) (bool) $value;
            } else {
                $value = trim((string) $value);
                $value = $value === '' && $column === 'observacoes' ? null : $value;
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

    /** Nome da instituição: trim, normaliza espaços, limita 255. Permite números e símbolos (hífen, ª, º, etc.). */
    private function normalizeNomeInstituicao(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        if (strlen($value) > 255) {
            $value = substr($value, 0, 255);
            $value = rtrim($value);
        }
        return $value;
    }

    /** Sigla / nome fantasia: trim, maiúsculas, limita 120. */
    private function normalizeSiglaInstituicao(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtoupper')) {
            $value = mb_strtoupper($value, 'UTF-8');
        } else {
            $value = strtoupper($value);
        }
        if (strlen($value) > 120) {
            $value = substr($value, 0, 120);
        }
        return $value;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildFilterClause(array $filters): array
    {
        $conditions = [];
        $params = [];

        $nome = trim((string) ($filters['nome'] ?? ''));
        if ($nome !== '') {
            $conditions[] = '(`nome` LIKE :nome_filter OR `nome_fantasia` LIKE :nome_fantasia_filter)';
            $params[':nome_filter'] = '%' . $nome . '%';
            $params[':nome_fantasia_filter'] = '%' . $nome . '%';
        }

        $ativo = $filters['ativo'] ?? null;
        if ($ativo !== null && $ativo !== '') {
            $conditions[] = '`ativo` = :ativo';
            $params[':ativo'] = (int) (bool) $ativo;
        }

        $where = $conditions === [] ? '' : implode(' AND ', $conditions);
        return [$where, $params];
    }
}
