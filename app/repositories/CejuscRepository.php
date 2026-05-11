<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CejuscRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOINs. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    private const STATUS_ORDER = [
        'pendente',
        'em_andamento',
        'concluída',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'cejusc_tarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct', true);

        $coll = self::COLLATION;
        $orderField = sprintf(
            "FIELD(ct.`status` COLLATE %s, %s)",
            $coll,
            implode(
                ', ',
                array_map(
                    fn (string $status) => $this->connection->quote($status),
                    self::STATUS_ORDER
                )
            )
        );

        $fromClause = $this->buildFromClause();

        $sql = sprintf(
            'SELECT ct.`id` AS `IdCarta`, ct.`numero_processo_origem` AS `Processo`, ct.`status` AS `Status`, ct.`created_at` AS `Data`, COALESCE(p.`nome_completo`, \'\') AS `ReuNome`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), NULLIF(TRIM(cl_alt.`nome`), \'\'), \'Classe não informada\') AS `ClasseNome` FROM %s %s ORDER BY %s, ct.`created_at` DESC LIMIT :limit OFFSET :offset',
            $fromClause,
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct', true);
        $fromClause = $this->buildFromClause();
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s %s', $fromClause, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ct', false);
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ct.`status` AS `Status`, COUNT(*) AS total FROM %s %s GROUP BY ct.`status`',
            $fromClause,
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
        $required = ['id', 'numero_processo_origem', 'parte_id', 'status'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $row) || (string) $row[$key] === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente ou vazio: {$key}");
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (%s, `created_at`, `updated_at`) VALUES (%s, NOW(), NOW())',
                $this->table,
                '`id`, `processo_origem_id`, `numero_processo_origem`, `parte_id`, `status`',
                ':id, :processo_origem_id, :numero_processo_origem, :parte_id, :status'
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_origem_id', $row['processo_origem_id'] ?? null);
        $statement->bindValue(':numero_processo_origem', $row['numero_processo_origem']);
        $statement->bindValue(':parte_id', $row['parte_id']);
        $statement->bindValue(':status', $row['status']);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = ['status'];
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

    public function updateStatus(string $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
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

        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ct.*, COALESCE(p.`nome_completo`, \'\') AS `reu_nome` FROM %s WHERE ct.`id` = :id LIMIT 1',
            $fromClause
        );
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
     * Busca tarefa Cejusc já existente para o mesmo processo e mesmo réu (parte).
     */
    public function findByProcessoEParte(string $numeroProcessoOrigem, string $parteId): ?array
    {
        $numeroProcessoOrigem = trim($numeroProcessoOrigem);
        $parteId = trim($parteId);
        if ($numeroProcessoOrigem === '' || $parteId === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT ct.* FROM `%s` ct WHERE ct.`numero_processo_origem` = :processo AND ct.`parte_id` = :parte LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':processo', $numeroProcessoOrigem);
        $statement->bindValue(':parte', $parteId);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    private function buildFromClause(): string
    {
        $coll = self::COLLATION;

        return sprintf(
            '`%s` ct'
            . ' LEFT JOIN `partes` p ON p.`id` COLLATE %s = ct.`parte_id` COLLATE %s'
            . ' LEFT JOIN `processos` proc ON proc.`id` COLLATE %s = ct.`processo_origem_id` COLLATE %s'
            . ' LEFT JOIN `classes` cl ON cl.`id` COLLATE %s = proc.`classe_id` COLLATE %s'
            . ' LEFT JOIN `processos` proc_alt ON proc.`id` IS NULL AND proc_alt.`numero_processo` COLLATE %s = ct.`numero_processo_origem` COLLATE %s'
            . ' LEFT JOIN `classes` cl_alt ON cl_alt.`id` COLLATE %s = proc_alt.`classe_id` COLLATE %s',
            $this->table,
            $coll, $coll,
            $coll, $coll,
            $coll, $coll,
            $coll, $coll,
            $coll, $coll
        );
    }

    /**
     * @param bool $excludeConcluidaCanceladaQuandoStatusVazio Quando true e status não informado, exclui concluída e cancelada da lista.
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
                $clauses[] = $prefix . "`numero_processo_origem` LIKE :processo";
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
