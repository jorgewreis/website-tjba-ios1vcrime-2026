<?php
declare(strict_types=1);

final class CeapaRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';
    private const STATUS_ORDER = [
        'pendente',
        'em_andamento',
        'finalizada',
    ];

    public function __construct(PDO $connection, string $table = 'ceapa_tarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct');
        $coll = self::COLLATION;
        $orderField = sprintf(
            "FIELD(ct.`status_cumprimento` COLLATE %s, %s)",
            $coll,
            implode(', ', array_map(fn (string $status): string => $this->connection->quote($status), self::STATUS_ORDER))
        );

        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ct.`id` AS `IdCeapa`, ct.`numero_processo_origem` AS `Processo`, ct.`tipo_suspensao` AS `TipoSuspensao`, ct.`decisao_cumprir` AS `DecisaoCumprir`, ct.`data_decisao` AS `DataDecisao`, ct.`status_cumprimento` AS `StatusCumprimento`, ct.`created_at` AS `DataCadastro`, COALESCE(p.`nome_completo`, \'\') AS `ReuNome`, COALESCE(cl.`nome`, \'\') AS `ClasseNome` FROM %s %s ORDER BY %s, ct.`created_at` DESC LIMIT :limit OFFSET :offset',
            $fromClause,
            $whereClause !== '' ? 'WHERE ' . $whereClause : '',
            $orderField
        );

        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters): int
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct');
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT COUNT(*) AS total FROM %s %s',
            $fromClause,
            $whereClause !== '' ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct', false);
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ct.`status_cumprimento` AS `Status`, COUNT(*) AS total FROM %s %s GROUP BY ct.`status_cumprimento`',
            $fromClause,
            $whereClause !== '' ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        $summary = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $summary[(string) ($row['Status'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        foreach (self::STATUS_ORDER as $status) {
            $summary[$status] = $summary[$status] ?? 0;
        }

        return $summary;
    }

    public function insert(array $row): string
    {
        $required = [
            'id',
            'numero_processo_origem',
            'parte_id',
            'tipo_suspensao',
            'decisao_cumprir',
            'data_decisao',
            'status_cumprimento',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $row) || trim((string) ($row[$key] ?? '')) === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente ou vazio: {$key}");
            }
        }

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `processo_origem_id`, `numero_processo_origem`, `parte_id`, `tipo_suspensao`, `decisao_cumprir`, `data_decisao`, `observacoes_cumprimento`, `status_cumprimento`, `created_at`, `updated_at`) VALUES (:id, :processo_origem_id, :numero_processo_origem, :parte_id, :tipo_suspensao, :decisao_cumprir, :data_decisao, :observacoes_cumprimento, :status_cumprimento, NOW(), NOW())',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_origem_id', $row['processo_origem_id'] ?? null);
        $statement->bindValue(':numero_processo_origem', $row['numero_processo_origem']);
        $statement->bindValue(':parte_id', $row['parte_id']);
        $statement->bindValue(':tipo_suspensao', $row['tipo_suspensao']);
        $statement->bindValue(':decisao_cumprir', $row['decisao_cumprir']);
        $statement->bindValue(':data_decisao', $row['data_decisao']);
        $statement->bindValue(':observacoes_cumprimento', $row['observacoes_cumprimento'] ?? null);
        $statement->bindValue(':status_cumprimento', $row['status_cumprimento']);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'processo_origem_id',
            'numero_processo_origem',
            'parte_id',
            'tipo_suspensao',
            'decisao_cumprir',
            'data_decisao',
            'observacoes_cumprimento',
            'status_cumprimento',
        ];
        $set = [];
        $params = [':id' => $id];
        foreach ($allowed as $column) {
            if (!array_key_exists($column, $payload)) {
                continue;
            }
            $set[] = "`{$column}` = :{$column}";
            $params[":{$column}"] = $payload[$column];
        }
        if ($set === []) {
            return false;
        }
        $set[] = '`updated_at` = NOW()';
        $sql = sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $set));
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ct.*, COALESCE(p.`nome_completo`, \'\') AS `reu_nome`, COALESCE(cl.`nome`, \'\') AS `classe_nome`, COALESCE(cl.`codigo`, \'\') AS `classe_codigo` FROM %s WHERE ct.`id` = :id LIMIT 1',
            $fromClause
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    private function buildFromClause(): string
    {
        return sprintf(
            '`%s` ct LEFT JOIN `partes` p ON p.`id` = ct.`parte_id` LEFT JOIN `processos` pr ON pr.`id` = ct.`processo_origem_id` LEFT JOIN `classes` cl ON cl.`id_classe` = pr.`classe_id`',
            $this->table
        );
    }

    private function buildFilterClause(array $filters, string $alias, bool $hideFinalizadaWhenEmpty = true): array
    {
        $clauses = [];
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';

        if (isset($filters['processo'])) {
            $processo = trim((string) ($filters['processo'] ?? ''));
            if ($processo !== '') {
                $clauses[] = $prefix . '`numero_processo_origem` LIKE :processo';
                $params[':processo'] = '%' . $processo . '%';
            }
        }

        $coll = self::COLLATION;
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $clauses[] = $prefix . "`status_cumprimento` COLLATE {$coll} = :status";
            $params[':status'] = $status;
        } elseif ($hideFinalizadaWhenEmpty) {
            $clauses[] = $prefix . "`status_cumprimento` COLLATE {$coll} <> :status_hide";
            $params[':status_hide'] = 'finalizada';
        }

        return [implode(' AND ', $clauses), $params];
    }
}
