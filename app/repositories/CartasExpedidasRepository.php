<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CartasExpedidasRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOINs entre tabelas. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    private const STATUS_ORDER = [
        'pendente',
        'em_andamento',
        'aguardando assinatura',
        'aguardando envio',
        'aguardando juntada do termo',
        'aguardando cumprimento',
        'concluída',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'cartas_expedidas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce', true);

        $fromClause = $this->buildFromClause();

        // Ordem oficial da listagem: data de cadastro (mais recente primeiro).
        $sql = sprintf(
            'SELECT ce.`id` AS `IdCarta`, ce.`numero_processo_origem` AS `Processo`, ce.`finalidade` AS `Finalidade`, ce.`prazo_dias` AS `Prazo`, ce.`cidade_destino` AS `Comarca`, ce.`uf_destino` AS `Estado`, ce.`status` AS `Status`, ce.`created_at` AS `Data`, COALESCE(cl.`nome`, \'Classe não informada\') AS `ClasseNome` FROM %s %s ORDER BY ce.`created_at` DESC LIMIT :limit OFFSET :offset',
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce', true);
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce', false);
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ce.`status` AS `Status`, COUNT(*) AS total FROM %s %s GROUP BY ce.`status`',
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
        $fromClause = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ce.`finalidade` AS `Finalidade`, COUNT(*) AS total FROM %s %s GROUP BY ce.`finalidade`',
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
            'id', 'processo_origem_id', 'numero_processo_origem', 'finalidade', 'prazo_dias',
            'comarca_destino_id', 'cidade_destino', 'uf_destino', 'status',
        ];
        foreach ($required as $key) {
            if (!array_key_exists($key, $row) || (string) $row[$key] === '') {
                throw new InvalidArgumentException("Campo obrigatório ausente ou vazio: {$key}");
            }
        }

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `processo_origem_id`, `numero_processo_origem`, `finalidade`, `prazo_dias`, `comarca_destino_id`, `cidade_destino`, `uf_destino`, `numero_processo_destino`, `vara_destino_id`, `vara_destino_nome`, `status`, `observacoes`, `created_at`, `updated_at`) VALUES (:id, :processo_origem_id, :numero_processo_origem, :finalidade, :prazo_dias, :comarca_destino_id, :cidade_destino, :uf_destino, :numero_processo_destino, :vara_destino_id, :vara_destino_nome, :status, :observacoes, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $row['id']);
        $statement->bindValue(':processo_origem_id', $row['processo_origem_id']);
        $statement->bindValue(':numero_processo_origem', $row['numero_processo_origem']);
        $statement->bindValue(':finalidade', $row['finalidade']);
        $statement->bindValue(':prazo_dias', (int) $row['prazo_dias'], PDO::PARAM_INT);
        $statement->bindValue(':comarca_destino_id', $row['comarca_destino_id']);
        $statement->bindValue(':cidade_destino', $row['cidade_destino']);
        $statement->bindValue(':uf_destino', $row['uf_destino']);
        $statement->bindValue(':numero_processo_destino', $row['numero_processo_destino'] ?? null);
        $statement->bindValue(':vara_destino_id', $row['vara_destino_id'] ?? null);
        $statement->bindValue(':vara_destino_nome', $row['vara_destino_nome'] ?? null);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->execute();

        return (string) $row['id'];
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'finalidade', 'prazo_dias', 'comarca_destino_id', 'cidade_destino', 'uf_destino',
            'numero_processo_destino', 'vara_destino_id', 'vara_destino_nome', 'status', 'observacoes',
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
     * Carta expedida + nome da classe processual do processo de origem.
     *
     * @return array<string, mixed>|null
     */
    public function findWithClasseProcessualNome(string $id): ?array
    {
        $coll = self::COLLATION;
        $from = $this->buildFromClause();
        $sql = sprintf(
            'SELECT ce.*, COALESCE(cl.`nome`, \'\') AS `classe_nome_processual` FROM %s WHERE ce.`id` COLLATE %s = :id LIMIT 1',
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
        $coll = self::COLLATION;
        return sprintf(
            '`%s` ce LEFT JOIN `processos` p ON p.`id` COLLATE %s = ce.`processo_origem_id` COLLATE %s LEFT JOIN `classes` cl ON cl.`id` COLLATE %s = p.`classe_id` COLLATE %s',
            $this->table,
            $coll,
            $coll,
            $coll,
            $coll
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
            $statusVal = trim($filters['status']);
            if ($statusVal === 'em_andamento') {
                $statusList = ['em_andamento', 'aguardando assinatura', 'aguardando envio', 'aguardando juntada do termo', 'aguardando juntada de termo', 'aguardando cumprimento'];
                $placeholders = [];
                foreach ($statusList as $i => $s) {
                    $key = ':status_' . $i;
                    $placeholders[] = $prefix . "`status` COLLATE {$coll} = {$key}";
                    $params[$key] = $s;
                }
                $clauses[] = '(' . implode(' OR ', $placeholders) . ')';
            } else {
                $clauses[] = $prefix . "`status` COLLATE {$coll} = :status";
                $params[':status'] = $statusVal;
            }
        } elseif ($excludeConcluidaCanceladaQuandoStatusVazio) {
            // Por padrão não listar Concluída e Cancelada; usuário escolhe no filtro se quiser ver.
            $clauses[] = $prefix . "`status` COLLATE {$coll} NOT IN (:status_exclude_1, :status_exclude_2)";
            $params[':status_exclude_1'] = 'concluída';
            $params[':status_exclude_2'] = 'cancelada';
        }

        return [implode(' AND ', $clauses), $params];
    }
}
