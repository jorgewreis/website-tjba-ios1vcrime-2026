<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CartasRecebidasRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOINs entre tabelas. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    private const STATUS_ORDER = [
        'pendente',
        'aguardando cumprimento',
        'aguardando devolução',
        'concluída',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'cartas_recebidas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'cr', true);

        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';
        $fromClause = $this->buildFromClause();
        $auditSelect = RegistroMetadataHelper::sqlSelectAuditoriaExtra('cr');

        // Ordem oficial da listagem: data de cadastro (mais recente primeiro).
        $sql = sprintf(
            'SELECT cr.`id` AS `IdCarta`, cr.`numero_processo_origem` AS `Processo`, cr.`finalidade` AS `Finalidade`, cr.`prazo_dias` AS `Prazo`, cr.`cidade_origem` AS `Comarca`, cr.`uf_origem` AS `Estado`, cr.`data_recebimento` AS `DataRecebimento`, cr.`status` AS `Status`, cr.`created_at` AS `Data`, COALESCE(cl.`nome`, \'Classe não informada\') AS `ClasseNome`%s FROM %s %s ORDER BY cr.`created_at` DESC LIMIT :limit OFFSET :offset',
            $auditSelect,
            $fromClause,
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'cr', true);
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'cr', false);
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT cr.`status` AS `Status`, COUNT(*) AS total FROM %s %s GROUP BY cr.`status`',
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

    public function countByClasse(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'cr');
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT COALESCE(cl.`nome`, \'Classe não informada\') AS `Classe`, COUNT(*) AS total FROM %s %s GROUP BY cl.`id`',
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
            $summary[$row['Classe']] = (int) $row['total'];
        }

        return $summary;
    }

    public function countByFinalidade(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'cr');
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT cr.`finalidade` AS `Finalidade`, COUNT(*) AS total FROM %s %s GROUP BY cr.`finalidade`',
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
            $label = trim((string) ($row['Finalidade'] ?? ''));
            if ($label === '') {
                $label = 'Finalidade não informada';
            }
            $summary[$label] = (int) $row['total'];
        }

        return $summary;
    }

    public function insert(array $row): string
    {
        $required = [
            'id', 'numero_processo_origem', 'comarca_origem_id', 'cidade_origem', 'uf_origem',
            'finalidade', 'prazo_dias', 'status',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $row) || (string) $row[$key] === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente ou vazio: {$key}");
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_origem_id`, `numero_processo_origem`, `comarca_origem_id`, `cidade_origem`, `uf_origem`, `data_recebimento`, `finalidade`, `prazo_dias`, `status`, `observacoes`, `numero_processo_destino`, `vara_destino_id`, `vara_destino_nome`, `criado_por`, `atualizado_por`, `created_at`, `updated_at`) VALUES (:id, :processo_origem_id, :numero_processo_origem, :comarca_origem_id, :cidade_origem, :uf_origem, :data_recebimento, :finalidade, :prazo_dias, :status, :observacoes, :numero_processo_destino, :vara_destino_id, :vara_destino_nome, :criado_por, :atualizado_por, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_origem_id', $row['processo_origem_id'] ?? null);
        $statement->bindValue(':numero_processo_origem', $row['numero_processo_origem']);
        $statement->bindValue(':comarca_origem_id', $row['comarca_origem_id']);
        $statement->bindValue(':cidade_origem', $row['cidade_origem']);
        $statement->bindValue(':uf_origem', $row['uf_origem']);
        $statement->bindValue(':data_recebimento', $row['data_recebimento'] ?? null);
        $statement->bindValue(':finalidade', $row['finalidade']);
        $statement->bindValue(':prazo_dias', (int) $row['prazo_dias'], PDO::PARAM_INT);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':numero_processo_destino', $row['numero_processo_destino'] ?? null);
        $statement->bindValue(':vara_destino_id', $row['vara_destino_id'] ?? null);
        $statement->bindValue(':vara_destino_nome', $row['vara_destino_nome'] ?? null);
        $cp = $row['criado_por'] ?? null;
        $statement->bindValue(':criado_por', $cp !== null && $cp !== '' ? $cp : null);
        $statement->bindValue(':atualizado_por', $cp !== null && $cp !== '' ? $cp : null);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'finalidade', 'prazo_dias', 'comarca_origem_id', 'cidade_origem', 'uf_origem',
            'data_recebimento', 'numero_processo_destino', 'vara_destino_id', 'vara_destino_nome', 'status', 'observacoes',
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

    public function find(string $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * Carta + nome da classe processual do processo de origem (join em processos/classes).
     *
     * @return array<string, mixed>|null
     */
    public function findWithClasseProcessualNome(string $id): ?array
    {
        $coll = self::COLLATION;
        $from = $this->buildFromClause();
        $sql = sprintf(
            'SELECT cr.*, COALESCE(cl.`nome`, \'\') AS `classe_nome_processual` FROM %s WHERE cr.`id` COLLATE %s = :id LIMIT 1',
            $from,
            $coll
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

    private function buildFromClause(): string
    {
        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';
        $coll = self::COLLATION;
        return sprintf(
            '`%s` cr LEFT JOIN `processos` p ON p.`id` COLLATE %s = cr.`processo_origem_id` COLLATE %s LEFT JOIN `classes` cl ON cl.`id` COLLATE %s = p.`classe_id` COLLATE %s%s',
            $this->table,
            $coll,
            $coll,
            $coll,
            $coll,
            RegistroMetadataHelper::sqlUsuarioAuditoria('cr')
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
        if (isset($filters['classe']) && $filters['classe'] !== '') {
            $clauses[] = "(cl.`codigo` COLLATE {$coll} = :classe OR cl.`nome` COLLATE {$coll} = :classe)";
            $params[':classe'] = trim($filters['classe']);
        }

        if (isset($filters['finalidade']) && $filters['finalidade'] !== '') {
            $clauses[] = $prefix . "`finalidade` COLLATE {$coll} = :finalidade";
            $params[':finalidade'] = trim($filters['finalidade']);
        }

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
