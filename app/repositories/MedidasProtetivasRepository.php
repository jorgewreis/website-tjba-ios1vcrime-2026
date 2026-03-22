<?php
declare(strict_types=1);

final class MedidasProtetivasRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';
    private const STATUS_ORDER = [
        'pendente',
        'em_andamento',
        'concluída',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'medidas_protetivas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'mp', true);

        $orderField = sprintf(
            "FIELD(mp.`status` COLLATE %s, %s)",
            self::COLLATION,
            implode(', ', array_map(fn (string $status): string => $this->connection->quote($status), self::STATUS_ORDER))
        );

        $sql = sprintf(
            'SELECT mp.`id` AS `IdMedida`, COALESCE(p.`numero_processo`, \'\') AS `Processo`, COALESCE(cl.`nome`, \'\') AS `Classe`, COALESCE(pr.`nome_completo`, \'\') AS `ReuNome`, mp.`status` AS `Status`, mp.`cejusc_tarefa_id` AS `CejuscTarefaId`, mp.`created_at` AS `Data`, mp.`observacoes`
             FROM `%s` mp
             LEFT JOIN `processos` p ON p.`id` = mp.`processo_id`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             LEFT JOIN `partes` pr ON pr.`id` = mp.`parte_reu_id`
             %s
             ORDER BY %s, mp.`created_at` DESC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $whereClause ? 'WHERE ' . $whereClause : '',
            $orderField
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'mp', true);
        $sql = sprintf(
            'SELECT COUNT(*) AS total
             FROM `%s` mp
             LEFT JOIN `processos` p ON p.`id` = mp.`processo_id`
             %s',
            $this->table,
            $whereClause ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'mp', false);
        $sql = sprintf(
            'SELECT mp.`status` AS `Status`, COUNT(*) AS total
             FROM `%s` mp
             LEFT JOIN `processos` p ON p.`id` = mp.`processo_id`
             %s
             GROUP BY mp.`status`',
            $this->table,
            $whereClause ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        $summary = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $summary[(string) $row['Status']] = (int) $row['total'];
        }
        foreach (self::STATUS_ORDER as $status) {
            $summary[$status] = $summary[$status] ?? 0;
        }

        return $summary;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT mp.*, COALESCE(p.`numero_processo`, \'\') AS `numero_processo`, p.`id` AS `processo_origem_id`, COALESCE(cl.`nome`, \'\') AS `classe_nome`, COALESCE(cl.`codigo`, \'\') AS `classe_codigo`, pr.`cpf` AS `reu_cpf`, pr.`nome_completo` AS `reu_nome_completo`, pr.`sexo` AS `reu_sexo`, pr.`data_nascimento` AS `reu_data_nascimento`
             FROM `%s` mp
             LEFT JOIN `processos` p ON p.`id` = mp.`processo_id`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             LEFT JOIN `partes` pr ON pr.`id` = mp.`parte_reu_id`
             WHERE mp.`id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function insert(array $row): string
    {
        foreach (['id', 'status'] as $required) {
            if (!array_key_exists($required, $row) || trim((string) $row[$required]) === '') {
                throw new InvalidArgumentException('Campo obrigatório ausente ou vazio: ' . $required);
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `parte_reu_id`, `cejusc_tarefa_id`, `status`, `observacoes`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :parte_reu_id, :cejusc_tarefa_id, :status, :observacoes, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_id', $row['processo_id'] ?? null);
        $statement->bindValue(':parte_reu_id', $row['parte_reu_id'] ?? null);
        $statement->bindValue(':cejusc_tarefa_id', $row['cejusc_tarefa_id'] ?? null);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['processo_id', 'parte_reu_id', 'cejusc_tarefa_id', 'status', 'observacoes'];
        $set = [];
        $params = [':id' => trim($id)];

        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $set[] = sprintf('`%s` = :%s', $column, $column);
            $params[':' . $column] = $payload[$column];
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
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    private function buildFilterClause(array $filters, string $alias, bool $excludeConcluidaCanceladaQuandoStatusVazio): array
    {
        $clauses = [];
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';

        if (isset($filters['processo'])) {
            $processoValue = trim((string) $filters['processo']);
            if ($processoValue !== '') {
                $clauses[] = 'p.`numero_processo` LIKE :processo';
                $params[':processo'] = '%' . $processoValue . '%';
            }
        }

        if (isset($filters['status']) && trim((string) $filters['status']) !== '') {
            $clauses[] = $prefix . '`status` COLLATE ' . self::COLLATION . ' = :status';
            $params[':status'] = trim((string) $filters['status']);
        } elseif ($excludeConcluidaCanceladaQuandoStatusVazio) {
            $clauses[] = $prefix . '`status` COLLATE ' . self::COLLATION . ' NOT IN (:status_exclude_1, :status_exclude_2)';
            $params[':status_exclude_1'] = 'concluída';
            $params[':status_exclude_2'] = 'cancelada';
        }

        return [implode(' AND ', $clauses), $params];
    }
}
