<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class EditaisRepository
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

    public function __construct(PDO $connection, string $table = 'editais')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'e', true);

        $coll = self::COLLATION;
        $orderField = sprintf(
            "FIELD(e.`status` COLLATE %s, %s)",
            $coll,
            implode(
                ', ',
                array_map(
                    fn (string $status) => $this->connection->quote($status),
                    self::STATUS_ORDER
                )
            )
        );

        $joinClause = ' LEFT JOIN `processos` p ON p.`id` = e.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id` ';
        $sql = sprintf(
            'SELECT e.`id` AS `IdEdital`, COALESCE(p.`numero_processo`, \'\') AS `Processo`, COALESCE(cl.`nome`, \'\') AS `Classe`, e.`tipo_edital` AS `TipoEdital`, e.`prazo_dias` AS `PrazoDias`, e.`status` AS `Status`, e.`created_at` AS `Data`, e.`observacoes` FROM `%s` e %s %s ORDER BY %s, e.`created_at` DESC LIMIT :limit OFFSET :offset',
            $this->table,
            $joinClause,
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'e', true);
        $joinClause = ' LEFT JOIN `processos` p ON p.`id` = e.`processo_id` ';
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s` e %s %s', $this->table, $joinClause, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'e', false);
        $joinClause = ' LEFT JOIN `processos` p ON p.`id` = e.`processo_id` ';
        $sql = sprintf(
            'SELECT e.`status` AS `Status`, COUNT(*) AS total FROM `%s` e %s %s GROUP BY e.`status`',
            $this->table,
            $joinClause,
            $whereClause ? 'WHERE ' . $whereClause : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        $summary = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $summary[$row['Status']] = (int) $row['total'];
        }

        foreach (self::STATUS_ORDER as $status) {
            $summary[$status] = $summary[$status] ?? 0;
        }

        return $summary;
    }

    public function insert(array $row): string
    {
        if (!array_key_exists('id', $row) || (string) $row['id'] === '') {
            throw new InvalidArgumentException('Campo obrigatório ausente ou vazio: id');
        }
        if (!array_key_exists('status', $row) || (string) $row['status'] === '') {
            throw new InvalidArgumentException('Campo obrigatório ausente ou vazio: status');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_id`, `status`, `tipo_edital`, `prazo_dias`, `observacoes`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :status, :tipo_edital, :prazo_dias, :observacoes, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_id', $row['processo_id'] ?? null);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':tipo_edital', $row['tipo_edital'] ?? null);
        $statement->bindValue(':prazo_dias', isset($row['prazo_dias']) ? (int) $row['prazo_dias'] : 0, PDO::PARAM_INT);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['processo_id', 'status', 'tipo_edital', 'prazo_dias', 'observacoes'];
        $set = [];
        $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $payload)) {
                continue;
            }
            $set[] = "`{$col}` = :{$col}";
            $params[":{$col}"] = $payload[$col];
        }
        if (empty($set)) {
            return false;
        }
        $set[] = '`updated_at` = NOW()';

        $sql = sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $set));
        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $joinClause = ' LEFT JOIN `processos` p ON p.`id` = e.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id` ';
        $sql = sprintf(
            'SELECT e.*, COALESCE(p.`numero_processo`, \'\') AS `numero_processo`, p.`id` AS `processo_origem_id`, COALESCE(cl.`nome`, \'\') AS `classe_nome`, COALESCE(cl.`codigo`, \'\') AS `classe_codigo` FROM `%s` e %s WHERE e.`id` = :id LIMIT 1',
            $this->table,
            $joinClause
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * @param bool $excludeConcluidaCanceladaQuandoStatusVazio Quando true e status não informado, exclui concluída e cancelada.
     */
    private function buildFilterClause(array $filters, string $alias = '', bool $excludeConcluidaCanceladaQuandoStatusVazio = true): array
    {
        $clauses = [];
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';
        $coll = self::COLLATION;

        if (isset($filters['processo'])) {
            $processoValue = trim((string) $filters['processo']);
            if ($processoValue !== '') {
                $clauses[] = 'p.`numero_processo` LIKE :processo';
                $params[':processo'] = '%' . $processoValue . '%';
            }
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $clauses[] = $prefix . "`status` COLLATE {$coll} = :status";
            $params[':status'] = trim($filters['status']);
        } elseif ($excludeConcluidaCanceladaQuandoStatusVazio) {
            $clauses[] = $prefix . "`status` COLLATE {$coll} NOT IN (:status_exclude_1, :status_exclude_2)";
            $params[':status_exclude_1'] = 'concluída';
            $params[':status_exclude_2'] = 'cancelada';
        }

        return [implode(' AND ', $clauses), $params];
    }

    public function getTableName(): string
    {
        return $this->table;
    }
}
