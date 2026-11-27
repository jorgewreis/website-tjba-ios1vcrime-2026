<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/CartasExpedidasEstadoFluxo.php';

final class CartasExpedidasRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOINs entre tabelas. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    private const STATUS_ORDER = [
        'em_andamento',
        'aguardando envio',
        'aguardando cumprimento',
        'concluída',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'cartas_expedidas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function fetchList(array $filters, ?int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'ce', true);

        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';
        $fromClause = $this->buildFromClause();
        $auditSelect = RegistroMetadataHelper::sqlSelectAuditoriaExtra('ce');

        // Ordem oficial da listagem: data de cadastro (mais recente primeiro).
        $sql = sprintf(
            'SELECT ce.`id` AS `IdCarta`, ce.`numero_processo_origem` AS `Processo`, ce.`finalidade` AS `Finalidade`, ce.`prazo_dias` AS `Prazo`, ce.`cidade_destino` AS `Comarca`, ce.`uf_destino` AS `Estado`, ce.`status` AS `Status`, ce.`created_at` AS `Data`, COALESCE(cl.`nome`, \'Classe não informada\') AS `ClasseNome`%s FROM %s %s ORDER BY ce.`created_at` DESC, ce.`id` DESC%s',
            $auditSelect,
            $fromClause,
            $whereClause ? 'WHERE ' . $whereClause : '',
            $limit !== null ? ' LIMIT :limit OFFSET :offset' : ''
        );

        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
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
        $states = CartasExpedidasEstadoFluxo::consultaEstados();
        $effectiveStatus = CartasExpedidasEstadoFluxo::expressaoStatus();
        $sql = sprintf(
            'SELECT %s AS `Status`, COUNT(*) AS total FROM %s JOIN (%s) fluxo ON fluxo.id=ce.id %s GROUP BY %s',
            $effectiveStatus, $fromClause, $states,
            $whereClause ? 'WHERE ' . $whereClause : '', $effectiveStatus
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

        if (($summary['indeterminada'] ?? 0) > 0) {
            $summary['em_andamento'] = null;
            $summary['aguardando envio'] = null;
            $summary['aguardando cumprimento'] = null;
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

        $idempotencyValues = array_key_exists('idempotency_key', $row)
            ? ', `idempotency_key`, `idempotency_payload_hash`'
            : '';
        $idempotencyPlaceholders = array_key_exists('idempotency_key', $row)
            ? ', :idempotency_key, :idempotency_payload_hash'
            : '';
        if (array_key_exists('prioridade_tarefa', $row)) {
            $idempotencyValues .= ', `prioridade_tarefa`';
            $idempotencyPlaceholders .= ', :prioridade_tarefa';
        }
        $statement = $this->connection->prepare(sprintf(
            'INSERT INTO `%s` (`id`, `processo_origem_id`, `numero_processo_origem`, `finalidade`, `prazo_dias`, `comarca_destino_id`, `cidade_destino`, `uf_destino`, `numero_processo_destino`, `vara_destino_id`, `vara_destino_nome`, `status`, `observacoes`, `criado_por`, `atualizado_por`%s, `created_at`, `updated_at`) VALUES (:id, :processo_origem_id, :numero_processo_origem, :finalidade, :prazo_dias, :comarca_destino_id, :cidade_destino, :uf_destino, :numero_processo_destino, :vara_destino_id, :vara_destino_nome, :status, :observacoes, :criado_por, :atualizado_por%s, NOW(), NOW())',
            $this->table,
            $idempotencyValues,
            $idempotencyPlaceholders
        ));
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
        $cp = $row['criado_por'] ?? null;
        $statement->bindValue(':criado_por', $cp !== null && $cp !== '' ? $cp : null);
        $statement->bindValue(':atualizado_por', $cp !== null && $cp !== '' ? $cp : null);
        if (array_key_exists('idempotency_key', $row)) {
            $statement->bindValue(':idempotency_key', $row['idempotency_key']);
            $statement->bindValue(':idempotency_payload_hash', $row['idempotency_payload_hash']);
        }
        if (array_key_exists('prioridade_tarefa', $row)) $statement->bindValue(':prioridade_tarefa', $row['prioridade_tarefa']);
        $statement->execute();

        return (string) $row['id'];
    }

    public function findByIdempotencyKey(string $userId, string $key, bool $currentRead = false): ?array
    {
        $suffix = $currentRead ? ' FOR UPDATE' : '';
        $statement = $this->connection->prepare(
            sprintf('SELECT `id`, `idempotency_payload_hash` FROM `%s` WHERE `criado_por` = :user_id AND `idempotency_key` = :request_key LIMIT 1%s', $this->table, $suffix)
        );
        $statement->execute([':user_id' => $userId, ':request_key' => $key]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result === false ? null : $result;
    }

    public function assertIdempotencyColumnsAvailable(): void
    {
        $columns = $this->connection->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $columns->execute([':table_name' => $this->table]);
        $definitions = [];
        foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $definitions[strtolower((string) $column['COLUMN_NAME'])] = $column;
        }
        foreach (['idempotency_key', 'idempotency_payload_hash'] as $name) {
            $column = $definitions[$name] ?? null;
            if (!is_array($column)
                || strtolower((string) ($column['COLUMN_TYPE'] ?? '')) !== 'char(64)'
                || strtoupper((string) ($column['IS_NULLABLE'] ?? '')) !== 'YES') {
                throw new RuntimeException('Schema idempotente indisponível ou parcialmente aplicado.');
            }
        }

        $index = $this->connection->prepare(
            'SELECT NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX'
        );
        $index->execute([
            ':table_name' => $this->table,
            ':index_name' => 'uq_cartas_expedidas_idempotency',
        ]);
        $parts = $index->fetchAll(PDO::FETCH_ASSOC);
        $valid = count($parts) === 2
            && (int) $parts[0]['NON_UNIQUE'] === 0
            && (int) $parts[0]['SEQ_IN_INDEX'] === 1
            && strtolower((string) $parts[0]['COLUMN_NAME']) === 'criado_por'
            && $parts[0]['SUB_PART'] === null
            && (int) $parts[1]['NON_UNIQUE'] === 0
            && (int) $parts[1]['SEQ_IN_INDEX'] === 2
            && strtolower((string) $parts[1]['COLUMN_NAME']) === 'idempotency_key'
            && $parts[1]['SUB_PART'] === null;
        if (!$valid) {
            throw new RuntimeException('Índice único de idempotência ausente ou incompatível.');
        }
    }

    public function update(string $id, array $payload): bool
    {
        $allowed = [
            'finalidade', 'prazo_dias', 'comarca_destino_id', 'cidade_destino', 'uf_destino',
            'numero_processo_destino', 'vara_destino_id', 'vara_destino_nome', 'status', 'observacoes', 'prioridade_tarefa',
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
        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';
        $coll = self::COLLATION;
        return sprintf(
            '`%s` ce LEFT JOIN `processos` p ON p.`id` COLLATE %s = ce.`processo_origem_id` COLLATE %s LEFT JOIN `classes` cl ON cl.`id` COLLATE %s = p.`classe_id` COLLATE %s%s',
            $this->table,
            $coll,
            $coll,
            $coll,
            $coll,
            RegistroMetadataHelper::sqlUsuarioAuditoria('ce')
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
            $clauses[] = "(cl.`codigo` COLLATE {$coll} = :classe_codigo OR cl.`nome` COLLATE {$coll} = :classe_nome)";
            $params[':classe_codigo'] = trim($filters['classe']);
            $params[':classe_nome'] = trim($filters['classe']);
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
            $mapped = ['aguardando_envio'=>'em_andamento','aguardando envio'=>'em_andamento',
                'aguardando_cumprimento'=>'aguardando cumprimento','concluida'=>'concluída'];
            $states = CartasExpedidasEstadoFluxo::consultaEstados();
            $effectiveStatus = CartasExpedidasEstadoFluxo::expressaoStatus();
            $clauses[] = "EXISTS (SELECT 1 FROM ({$states}) fluxo WHERE fluxo.id={$prefix}`id` AND ({$effectiveStatus})=:status_efetivo)";
            $params[':status_efetivo'] = $mapped[$statusVal] ?? $statusVal;
        } elseif ($excludeConcluidaCanceladaQuandoStatusVazio) {
            // Fluxos ambíguos permanecem na lista geral para reconciliação explícita.
            $states = CartasExpedidasEstadoFluxo::consultaEstados();
            $clauses[] = "EXISTS (SELECT 1 FROM ({$states}) fluxo WHERE fluxo.id={$prefix}`id` AND fluxo.etapa<>'encerrada')";
        }

        if (($filters['piloto_sem_envio'] ?? false) === true) {
            $states = CartasExpedidasEstadoFluxo::consultaEstados();
            $clauses[] = "EXISTS (SELECT 1 FROM ({$states}) fila WHERE fila.id={$prefix}`id` AND fila.etapa<>'envio')";
        }

        return [implode(' AND ', $clauses), $params];
    }
}
