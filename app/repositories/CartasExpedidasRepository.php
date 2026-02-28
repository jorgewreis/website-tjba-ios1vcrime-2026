<?php
declare(strict_types=1);

final class CartasExpedidasRepository
{
    private PDO $connection;
    private string $table;

    private const STATUS_ORDER = [
        'aguardando assinatura',
        'aguardando envio',
        'aguardando juntada do termo',
        'aguardando cumprimento',        
        'concluída',
        'cancelada',
    ];

    private const FIELD_MAP = [
        'processo' => 'Processo',
        'numero_processo' => 'Processo',
        'carta_numero' => 'CartaNumero',
        'carta_vara' => 'CartaVara',
        'classe' => 'Classe',
        'finalidade' => 'Finalidade',
        'prazo' => 'Prazo',
        'prazo_cumprimento' => 'Prazo',
        'estado' => 'Estado',
        'cidade' => 'Comarca',
        'comarca' => 'Comarca',
        'status' => 'Status',
        'usuario' => 'Usuario',
        'idcarta' => 'IdCarta',
    ];

    public function __construct(PDO $connection, string $table = 'CartasExpedidas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
    
        // UM único FIELD() com todos os status
        $orderField = sprintf(
            "FIELD(ce.`Status`, %s)",
            implode(
                ', ',
                array_map(
                    fn (string $status) => $this->connection->quote($status),
                    self::STATUS_ORDER
                )
            )
        );
    
        $fromClause = sprintf(
            '`%s` ce LEFT JOIN `processos` p ON REPLACE(REPLACE(p.`numero`, \'-\', \'\'), \'.\', \'\') = REPLACE(REPLACE(ce.`Processo`, \'-\', \'\'), \'.\', \'\') LEFT JOIN `classes` cl ON cl.`cod` = p.`classe`',
            $this->table
        );
    
        $sql = sprintf(
            'SELECT ce.`IdCarta`, ce.`Processo`, ce.`CartaNumero`, ce.`CartaVara`, ce.`Classe`, ce.`Finalidade`, ce.`Prazo`, ce.`Comarca`, ce.`Estado`, ce.`Status`, ce.`Data`, ce.`Usuario`, COALESCE(cl.`descricao`, \'Classe não informada\') AS `ClasseNome` FROM %s %s ORDER BY %s, ce.`Data` DESC LIMIT :limit OFFSET :offset',
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
        // Adicionar o alias 'ce' aqui para consistência
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
        $sql = sprintf('SELECT COUNT(*) AS total FROM `%s` ce %s', $this->table, $whereClause ? 'WHERE ' . $whereClause : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        // Adicionar o alias 'ce' aqui para consistência
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
        $sql = sprintf(
            'SELECT ce.`Status`, COUNT(*) AS total FROM `%s` ce %s GROUP BY ce.`Status`',
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

    public function countByClasse(array $filters): array
    {
        // Adicionar o alias 'ce' aqui para consistência
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce');
        $sql = sprintf(
            'SELECT ce.`Classe`, COUNT(*) AS total FROM `%s` ce %s GROUP BY ce.`Classe`',
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
            $summary[$row['Classe']] = (int) $row['total'];
        }

        return $summary;
    }

    public function insert(array $payload): string
    {
        $data = $this->mapPayloadToColumns($payload);
        if (empty($data)) {
            throw new RuntimeException('Nenhum campo válido foi fornecido para criação.');
        }

        $columns = array_keys($data);
        $columnList = implode(', ', array_map(fn (string $column) => sprintf('`%s`', $column), $columns));
        $placeholders = implode(', ', array_map(fn (string $column) => ':' . $column, $columns));
        $sql = sprintf(
            'INSERT INTO `%s` (%s, `Data`) VALUES (%s, NOW())',
            $this->table,
            $columnList,
            $placeholders
        );

        $statement = $this->connection->prepare($sql);
        foreach ($data as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }
        $statement->execute();

        if (isset($data['IdCarta']) && $data['IdCarta'] !== '') {
            return (string) $data['IdCarta'];
        }

        return (string) $this->connection->lastInsertId();
    }

    public function update(string $id, array $payload): bool
    {
        $data = $this->mapPayloadToColumns($payload);
        if (empty($data)) {
            throw new RuntimeException('Nenhum campo válido foi fornecido para atualização.');
        }

        $setClauses = [];
        foreach ($data as $column => $value) {
            $setClauses[] = sprintf('`%s` = :%s', $column, $column);
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `IdCarta` = :id LIMIT 1',
            $this->table,
            implode(', ', $setClauses)
        );

        $statement = $this->connection->prepare($sql);
        foreach ($data as $column => $value) {
            $statement->bindValue(':' . $column, $value);
        }
        $statement->bindValue(':id', $id);

        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `IdCarta` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function find(string $id): ?array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `IdCarta` = :id LIMIT 1', $this->table);
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

    private function buildFilterClause(array $filters, string $alias = ''): array
    {
        $clauses = [];
        $params = [];
        $prefix = $alias !== '' ? $alias . '.' : '';

        if (isset($filters['classe']) && $filters['classe'] !== '') {
            $clauses[] = $prefix . '`Classe` = :classe';
            $params[':classe'] = trim($filters['classe']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $clauses[] = $prefix . '`Status` = :status';
            $params[':status'] = trim($filters['status']);
        }

        return [implode(' AND ', $clauses), $params];
    }

    private function mapPayloadToColumns(array $payload): array
    {
        $mapped = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);
            if (!isset(self::FIELD_MAP[$normalized])) {
                continue;
            }

            $mapped[self::FIELD_MAP[$normalized]] = $value;
        }

        return $mapped;
    }
}
