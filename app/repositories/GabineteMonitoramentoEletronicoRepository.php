<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteMonitoramentoEletronicoRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    private const STATUS_ORDER = [
        'pendente',
        'em_andamento',
        'finalizada',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'gabinete_monitoramento_eletronico')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $join = ' LEFT JOIN `processos` p ON p.`id` = g.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id` ';
        $sql = sprintf(
            'SELECT g.*, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome` FROM `%s` g %s WHERE g.`id` = :id LIMIT 1',
            $this->table,
            $join
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function nextSequencialForYear(int $ano): int
    {
        $st = $this->connection->prepare(
            sprintf('SELECT COALESCE(MAX(`numero_sequencial`), 0) + 1 AS n FROM `%s` WHERE `ano` = :ano', $this->table)
        );
        $st->bindValue(':ano', $ano, PDO::PARAM_INT);
        $st->execute();

        return (int) $st->fetchColumn();
    }

    public function getMaxSequencialForYear(int $ano): int
    {
        $st = $this->connection->prepare(
            sprintf('SELECT COALESCE(MAX(`numero_sequencial`), 0) AS n FROM `%s` WHERE `ano` = :ano', $this->table)
        );
        $st->bindValue(':ano', $ano, PDO::PARAM_INT);
        $st->execute();

        return (int) $st->fetchColumn();
    }

    public function insert(array $row): void
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `processo_id`, `numero_processo`, `ano`, `numero_sequencial`, `processo_parte_id`, `reu_nome_exibicao`, `reu_complemento`, `status`, `data_inicio`, `data_encerramento`, `criado_por`, `atualizado_por`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :numero_processo, :ano, :numero_sequencial, :processo_parte_id, :reu_nome_exibicao, :reu_complemento, :status, :data_inicio, :data_encerramento, :criado_por, :atualizado_por, NOW(), NOW())',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':processo_id', $row['processo_id'] ?? null);
        $st->bindValue(':numero_processo', $row['numero_processo']);
        $st->bindValue(':ano', (int) $row['ano'], PDO::PARAM_INT);
        $st->bindValue(':numero_sequencial', (int) $row['numero_sequencial'], PDO::PARAM_INT);
        $st->bindValue(':processo_parte_id', $row['processo_parte_id'] ?? null);
        $st->bindValue(':reu_nome_exibicao', $row['reu_nome_exibicao']);
        $st->bindValue(':reu_complemento', $row['reu_complemento'] ?? null);
        $st->bindValue(':status', $row['status']);
        $st->bindValue(':data_inicio', $row['data_inicio'] ?? null);
        $st->bindValue(':data_encerramento', $row['data_encerramento'] ?? null);
        $cp = $row['criado_por'] ?? null;
        $st->bindValue(':criado_por', $cp !== null && $cp !== '' ? $cp : null);
        $st->bindValue(':atualizado_por', $cp !== null && $cp !== '' ? $cp : null);
        $st->execute();
    }

    public function updateStatus(string $id, string $status, string $atualizadoPor = ''): bool
    {
        $st = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `status` = :status, `atualizado_por` = COALESCE(NULLIF(:atualizado_por, \'\'), `atualizado_por`), `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':status', $status);
        $st->bindValue(':atualizado_por', trim($atualizadoPor));
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function updateDatas(string $id, ?string $dataInicio, ?string $dataEncerramento, string $atualizadoPor = ''): bool
    {
        $st = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `data_inicio` = :di, `data_encerramento` = :df, `atualizado_por` = COALESCE(NULLIF(:atualizado_por, \'\'), `atualizado_por`), `updated_at` = NOW() WHERE `id` = :id LIMIT 1',
                $this->table
            )
        );
        $st->bindValue(':di', $dataInicio);
        $st->bindValue(':df', $dataEncerramento);
        $st->bindValue(':atualizado_por', trim($atualizadoPor));
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $st = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', true);
        $coll = self::COLLATION;
        $orderField = sprintf(
            'FIELD(g.`status` COLLATE %s, %s)',
            $coll,
            implode(', ', array_map(fn (string $s) => $this->connection->quote($s), self::STATUS_ORDER))
        );
        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';
        $fromClause = $this->buildFromClause();
        $auditSelect = RegistroMetadataHelper::sqlSelectAuditoriaExtra('g');
        $sql = sprintf(
            'SELECT g.`id` AS `IdMon`, g.`numero_processo` AS `Processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `ClasseNome`, g.`ano` AS `Ano`, g.`numero_sequencial` AS `NumeroSeq`, g.`reu_nome_exibicao` AS `ReuNome`, g.`status` AS `Status`, g.`data_inicio` AS `DataInicio`, g.`data_encerramento` AS `DataEncerramento`, g.`created_at` AS `Data`%s FROM %s %s ORDER BY %s, g.`created_at` DESC LIMIT :limit OFFSET :offset',
            $auditSelect,
            $fromClause,
            $whereClause ? 'WHERE ' . $whereClause : '',
            $orderField
        );
        $st = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $st->bindValue($name, $value);
        }
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', true);
        $join = ' LEFT JOIN `processos` p ON p.`id` = g.`processo_id` ';
        $sql = sprintf('SELECT COUNT(*) FROM `%s` g %s %s', $this->table, $join, $whereClause ? 'WHERE ' . $whereClause : '');
        $st = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $st->bindValue($name, $value);
        }
        $st->execute();

        return (int) $st->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'g', false);
        $join = ' LEFT JOIN `processos` p ON p.`id` = g.`processo_id` ';
        $sql = sprintf(
            'SELECT g.`status` AS st, COUNT(*) AS total FROM `%s` g %s %s GROUP BY g.`status`',
            $this->table,
            $join,
            $whereClause ? 'WHERE ' . $whereClause : ''
        );
        $st = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $st->bindValue($name, $value);
        }
        $st->execute();
        $summary = [];
        while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $summary[(string) $row['st']] = (int) $row['total'];
        }
        foreach (self::STATUS_ORDER as $s) {
            $summary[$s] = $summary[$s] ?? 0;
        }

        return $summary;
    }

    private function buildFromClause(): string
    {
        require_once __DIR__ . '/../helpers/RegistroMetadataHelper.php';

        return sprintf(
            '`%s` g LEFT JOIN `processos` p ON p.`id` = g.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`%s',
            $this->table,
            RegistroMetadataHelper::sqlUsuarioAuditoria('g')
        );
    }

    private function buildFilterClause(array $filters, string $alias, bool $excludeFinalizadaCanceladaQuandoStatusVazio): array
    {
        $conditions = [];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'todos') {
            // Listagem explícita de todos os status (inclui finalizada e cancelada).
        } elseif ($status !== '' && in_array($status, self::STATUS_ORDER, true)) {
            $conditions[] = sprintf('%s.`status` = :f_status', $alias);
            $params[':f_status'] = $status;
        } elseif ($excludeFinalizadaCanceladaQuandoStatusVazio) {
            $conditions[] = sprintf('%s.`status` NOT IN (:f_status_excl_1, :f_status_excl_2)', $alias);
            $params[':f_status_excl_1'] = 'finalizada';
            $params[':f_status_excl_2'] = 'cancelada';
        }

        $ano = trim((string) ($filters['ano'] ?? ''));
        if ($ano !== '' && ctype_digit($ano)) {
            $conditions[] = sprintf('%s.`ano` = :f_ano', $alias);
            $params[':f_ano'] = (int) $ano;
        }

        $processo = trim((string) ($filters['processo'] ?? ''));
        if ($processo !== '') {
            $conditions[] = sprintf('(%s.`numero_processo` LIKE :f_proc OR %s.`reu_nome_exibicao` LIKE :f_reu)', $alias, $alias);
            $params[':f_proc'] = '%' . $processo . '%';
            $params[':f_reu'] = '%' . $processo . '%';
        }

        return [implode(' AND ', $conditions), $params];
    }
}
