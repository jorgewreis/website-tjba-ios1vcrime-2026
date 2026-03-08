<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GuiasRepository
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

    public function __construct(PDO $connection, string $table = 'guias')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', true);

        $coll = self::COLLATION;
        $orderField = sprintf(
            "FIELD(g.`status` COLLATE %s, %s)",
            $coll,
            implode(
                ', ',
                array_map(
                    fn (string $status) => $this->connection->quote($status),
                    self::STATUS_ORDER
                )
            )
        );

        $sql = sprintf(
            'SELECT g.`id` AS `IdGuia`, g.`numero_processo` AS `Processo`, g.`denunciado` AS `Denunciado`, g.`tipo` AS `Tipo`, g.`etapa` AS `Etapa`, g.`status` AS `Status`, g.`created_at` AS `Data`, g.`data_fato` AS `data_fato`, g.`dias_multa` AS `dias_multa` FROM `%s` g %s ORDER BY %s, g.`created_at` DESC LIMIT :limit OFFSET :offset',
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', true);
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s` g %s', $this->table, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', false);
        $sql = sprintf(
            'SELECT g.`status` AS `Status`, COUNT(*) AS total FROM `%s` g %s GROUP BY g.`status`',
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
            $summary[$row['Status']] = (int) $row['total'];
        }

        foreach (self::STATUS_ORDER as $status) {
            $summary[$status] = $summary[$status] ?? 0;
        }

        return $summary;
    }

    public function countByTipo(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g');
        $sql = sprintf(
            'SELECT g.`tipo` AS `Tipo`, COUNT(*) AS total FROM `%s` g %s GROUP BY g.`tipo`',
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
            $label = trim((string) ($row['Tipo'] ?? ''));
            if ($label === '') {
                $label = 'Tipo não informado';
            }
            $summary[$label] = (int) $row['total'];
        }

        return $summary;
    }

    public function insert(array $row): string
    {
        $required = ['id', 'numero_processo', 'tipo', 'status'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $row) || (string) $row[$key] === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente ou vazio: {$key}");
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `numero_processo`, `processo_id`, `denunciado`, `reu_id`, `tipo`, `etapa`, `status`, `regime`, `data_fato`, `dias_multa`, `observacoes`, `created_at`, `updated_at`, `user_cadastro`) VALUES (:id, :numero_processo, :processo_id, :denunciado, :reu_id, :tipo, :etapa, :status, :regime, :data_fato, :dias_multa, :observacoes, NOW(), NOW(), :user_cadastro)',
                $this->table
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':numero_processo', $row['numero_processo']);
        $statement->bindValue(':processo_id', $row['processo_id'] ?? null);
        $statement->bindValue(':denunciado', $row['denunciado'] ?? null);
        $statement->bindValue(':reu_id', $row['reu_id'] ?? null);
        $statement->bindValue(':tipo', $row['tipo']);
        $statement->bindValue(':etapa', $row['etapa'] ?? null);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':regime', $row['regime'] ?? null);
        $statement->bindValue(':data_fato', $row['data_fato'] ?? null);
        $statement->bindValue(':dias_multa', isset($row['dias_multa']) ? (int) $row['dias_multa'] : null, PDO::PARAM_INT);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':user_cadastro', $row['user_cadastro'] ?? null);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'numero_processo', 'processo_id', 'denunciado', 'reu_id', 'tipo', 'etapa', 'status',
            'regime', 'data_fato', 'dias_multa', 'observacoes', 'user_cadastro',
        ];
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

        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function getTableName(): string
    {
        return $this->table;
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

        if (isset($filters['tipo']) && $filters['tipo'] !== '') {
            $clauses[] = $prefix . "`tipo` COLLATE {$coll} = :tipo";
            $params[':tipo'] = trim($filters['tipo']);
        }

        if (isset($filters['processo'])) {
            $processoValue = trim((string) $filters['processo']);
            if ($processoValue !== '') {
                $clauses[] = $prefix . "`numero_processo` LIKE :processo";
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
}
