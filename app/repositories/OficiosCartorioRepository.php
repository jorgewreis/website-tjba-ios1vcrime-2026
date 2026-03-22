<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class OficiosCartorioRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    /** Ordem na listagem: pendentes primeiro, depois finalizadas, canceladas. */
    private const STATUS_ORDER = [
        'pendente',
        'finalizada',
        'cancelada',
    ];

    public function __construct(PDO $connection, string $table = 'oficios_cartorio')
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
        $join = ' LEFT JOIN `processos` p ON p.`id` = o.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id` ';
        $sql = sprintf(
            'SELECT o.*, COALESCE(cl.`nome`, \'\') AS `classe_nome` FROM `%s` o %s WHERE o.`id` = :id LIMIT 1',
            $this->table,
            $join
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Próximo número sequencial para o ano (transação externa recomendada).
     */
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
            'INSERT INTO `%s` (`id`, `processo_id`, `numero_processo`, `ano`, `numero_sequencial`, `tipo_oficio`, `forma_cumprimento`, `meio_envio`, `status`, `criado_por`, `atualizado_por`, `created_at`, `updated_at`) VALUES (:id, :processo_id, :numero_processo, :ano, :numero_sequencial, :tipo_oficio, :forma_cumprimento, :meio_envio, :status, :criado_por, :atualizado_por, NOW(), NOW())',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':processo_id', $row['processo_id'] ?? null);
        $st->bindValue(':numero_processo', $row['numero_processo']);
        $st->bindValue(':ano', (int) $row['ano'], PDO::PARAM_INT);
        $st->bindValue(':numero_sequencial', (int) $row['numero_sequencial'], PDO::PARAM_INT);
        $st->bindValue(':tipo_oficio', $row['tipo_oficio']);
        $st->bindValue(':forma_cumprimento', $row['forma_cumprimento']);
        $st->bindValue(':meio_envio', $row['meio_envio'] ?? null);
        $st->bindValue(':status', $row['status']);
        $cp = $row['criado_por'] ?? null;
        $ap = $row['atualizado_por'] ?? $cp;
        $st->bindValue(':criado_por', $cp !== null && $cp !== '' ? $cp : null);
        $st->bindValue(':atualizado_por', $ap !== null && $ap !== '' ? $ap : null);
        $st->execute();
    }

    public function updateStatus(string $id, string $status, ?string $atualizadoPor = null): bool
    {
        if ($atualizadoPor !== null && $atualizadoPor !== '') {
            $st = $this->connection->prepare(
                sprintf('UPDATE `%s` SET `status` = :status, `atualizado_por` = :atualizado_por, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
            );
            $st->bindValue(':atualizado_por', $atualizadoPor);
        } else {
            $st = $this->connection->prepare(
                sprintf('UPDATE `%s` SET `status` = :status, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
            );
        }
        $st->bindValue(':status', $status);
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'o', true);
        $coll = self::COLLATION;
        $orderField = sprintf(
            "FIELD(o.`status` COLLATE %s, %s)",
            $coll,
            implode(', ', array_map(fn (string $s) => $this->connection->quote($s), self::STATUS_ORDER))
        );
        $join = ' LEFT JOIN `processos` p ON p.`id` = o.`processo_id` LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id` LEFT JOIN `usuarios` ucr ON ucr.`id_usuario` = o.`criado_por` LEFT JOIN `usuarios` ued ON ued.`id_usuario` = o.`atualizado_por` ';
        $sql = sprintf(
            'SELECT o.`id` AS `IdOficio`, o.`numero_processo` AS `Processo`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `ClasseNome`, o.`ano` AS `Ano`, o.`numero_sequencial` AS `NumeroSeq`, o.`tipo_oficio` AS `TipoOficio`, o.`forma_cumprimento` AS `FormaCumprimento`, o.`meio_envio` AS `MeioEnvio`, o.`status` AS `Status`, o.`created_at` AS `Data`, TRIM(CONCAT(COALESCE(ucr.`nome`, \'\'), \' \', COALESCE(ucr.`sobrenome`, \'\'))) AS `CriadoPorNome`, TRIM(CONCAT(COALESCE(ued.`nome`, \'\'), \' \', COALESCE(ued.`sobrenome`, \'\'))) AS `UltimoEditorNome` FROM `%s` o %s %s ORDER BY %s, o.`created_at` DESC LIMIT :limit OFFSET :offset',
            $this->table,
            $join,
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
        [$whereClause, $params] = $this->buildFilterClause($filters, 'o', true);
        $join = ' LEFT JOIN `processos` p ON p.`id` = o.`processo_id` ';
        $sql = sprintf('SELECT COUNT(*) FROM `%s` o %s %s', $this->table, $join, $whereClause ? 'WHERE ' . $whereClause : '');
        $st = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $st->bindValue($name, $value);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /**
     * Anos em que existe pelo menos um ofício expedido (número sequencial no ano).
     *
     * @return list<int>
     */
    public function fetchDistinctAnos(): array
    {
        $sql = sprintf('SELECT DISTINCT `ano` FROM `%s` ORDER BY `ano` DESC', $this->table);
        $st = $this->connection->query($sql);
        if ($st === false) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($rows === false) {
            return [];
        }
        return array_map(static fn ($y) => (int) $y, $rows);
    }

    public function countByStatus(array $filters): array
    {
        [$whereClause, $params] = $this->buildFilterClause($filters, 'o', false);
        $join = ' LEFT JOIN `processos` p ON p.`id` = o.`processo_id` ';
        $sql = sprintf(
            'SELECT o.`status` AS st, COUNT(*) AS total FROM `%s` o %s %s GROUP BY o.`status`',
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

    private function buildFilterClause(array $filters, string $alias, bool $includeStatusFilter): array
    {
        $conditions = [];
        $params = [];

        $processo = trim((string) ($filters['processo'] ?? ''));
        if ($processo !== '') {
            $conditions[] = sprintf('%s.`numero_processo` LIKE :f_proc', $alias);
            $params[':f_proc'] = '%' . $processo . '%';

            return [implode(' AND ', $conditions), $params];
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($includeStatusFilter && $status !== '') {
            if (in_array($status, self::STATUS_ORDER, true)) {
                $conditions[] = sprintf('%s.`status` = :f_status', $alias);
                $params[':f_status'] = $status;
            }
        }

        $ano = trim((string) ($filters['ano'] ?? ''));
        if ($ano !== '' && ctype_digit($ano)) {
            $conditions[] = sprintf('%s.`ano` = :f_ano', $alias);
            $params[':f_ano'] = (int) $ano;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
