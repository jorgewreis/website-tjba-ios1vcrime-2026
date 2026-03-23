<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteReuPresoMovimentacaoRepository
{
    private PDO $connection;
    private string $table;
    private ?bool $hasProcessoIdColumnCache = null;

    public function __construct(PDO $connection, string $table = 'gabinete_reu_preso_movimentacao')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function insert(array $row): void
    {
        $hasProcessoId = $this->hasProcessoIdColumn();
        $sql = $hasProcessoId
            ? sprintf(
                'INSERT INTO `%s` (`id`, `reu_preso_id`, `reu_preso_processo_id`, `processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`, `created_at`, `user_cadastro`)
                 VALUES (:id, :reu_preso_id, :reu_preso_processo_id, :processo_id, :tipo_codigo, :data_movimentacao, :observacoes, NOW(), :user_cadastro)',
                $this->table
            )
            : sprintf(
                'INSERT INTO `%s` (`id`, `reu_preso_id`, `reu_preso_processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`, `created_at`, `user_cadastro`)
                 VALUES (:id, :reu_preso_id, :reu_preso_processo_id, :tipo_codigo, :data_movimentacao, :observacoes, NOW(), :user_cadastro)',
                $this->table
            );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':reu_preso_id', $row['reu_preso_id']);
        $st->bindValue(':reu_preso_processo_id', $row['reu_preso_processo_id'] ?? null);
        if ($hasProcessoId) {
            $st->bindValue(':processo_id', $row['processo_id'] ?? null);
        }
        $st->bindValue(':tipo_codigo', $row['tipo_codigo']);
        $st->bindValue(':data_movimentacao', $row['data_movimentacao']);
        $st->bindValue(':observacoes', $row['observacoes'] ?? null);
        $st->bindValue(':user_cadastro', $row['user_cadastro'] ?? null);
        $st->execute();
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':id', $id);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function existsByProcessoTipoData(string $processoId, string $tipoCodigo, string $dataMovimentacao): bool
    {
        $processoId = trim($processoId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($processoId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return false;
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'SELECT 1
                 FROM `%s` m
                 WHERE m.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            )
            : sprintf(
                'SELECT 1
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE gpp.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':pid', $processoId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return $st->fetchColumn() !== false;
    }

    /**
     * @param list<string> $processoIds
     * @param list<string> $prefixes
     */
    public function maxDataMovimentacaoForTipoPrefixesByProcessos(array $processoIds, array $prefixes): ?string
    {
        $processoIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $processoIds), static function ($id) {
            return $id !== '';
        }));
        $prefixes = array_values(array_filter(array_map(static function ($p) {
            return trim((string) $p);
        }, $prefixes), static function ($p) {
            return $p !== '';
        }));
        if ($processoIds === [] || $prefixes === []) {
            return null;
        }
        $pidPlaceholders = [];
        $params = [];
        foreach ($processoIds as $i => $pid) {
            $k = ':pid' . $i;
            $pidPlaceholders[] = $k;
            $params[$k] = $pid;
        }
        $whereLike = [];
        foreach ($prefixes as $i => $prefix) {
            $k = ':pf' . $i;
            $whereLike[] = 'm.`tipo_codigo` LIKE ' . $k;
            $params[$k] = '%' . $prefix . '%';
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'SELECT MAX(m.`data_movimentacao`)
                 FROM `%s` m
                 WHERE m.`processo_id` IN (%s) AND (%s)',
                $this->table,
                implode(',', $pidPlaceholders),
                implode(' OR ', $whereLike)
            )
            : sprintf(
                'SELECT MAX(m.`data_movimentacao`)
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE gpp.`processo_id` IN (%s) AND (%s)',
                $this->table,
                implode(',', $pidPlaceholders),
                implode(' OR ', $whereLike)
            );
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $v = $st->fetchColumn();
        if ($v === null || $v === false || $v === '') {
            return null;
        }

        return (string) $v;
    }

    public function deleteByProcessoTipoData(string $processoId, string $tipoCodigo, string $dataMovimentacao): int
    {
        $processoId = trim($processoId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($processoId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return 0;
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'DELETE m
                 FROM `%s` m
                 WHERE m.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt',
                $this->table
            )
            : sprintf(
                'DELETE m
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE gpp.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt',
                $this->table
            );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':pid', $processoId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return (int) $st->rowCount();
    }

    /**
     * @param list<string> $processoIds
     * @return array<string, array<string, mixed>> processo_id => linha da movimentação
     */
    public function fetchUltimaPorProcessoIds(array $processoIds): array
    {
        $processoIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $processoIds), static function ($id) {
            return $id !== '';
        }));
        if ($processoIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($processoIds as $i => $id) {
            $k = ':p' . $i;
            $placeholders[] = $k;
            $params[$k] = $id;
        }
        $in = implode(',', $placeholders);
        $hasProcessoId = $this->hasProcessoIdColumn();
        $sql = $hasProcessoId
            ? sprintf(
                'SELECT `id`, `processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`
                 FROM `%s`
                 WHERE `processo_id` IN (%s)
                 ORDER BY `processo_id` ASC, `data_movimentacao` DESC, `created_at` DESC',
                $this->table,
                $in
            )
            : sprintf(
                'SELECT m.`id`, gpp.`processo_id`, m.`tipo_codigo`, m.`data_movimentacao`, m.`observacoes`
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE gpp.`processo_id` IN (%s)
                 ORDER BY gpp.`processo_id` ASC, m.`data_movimentacao` DESC, m.`created_at` DESC',
                $this->table,
                $in
            );
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($pid !== '' && !isset($out[$pid])) {
                $out[$pid] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByProcessoIdOrderDesc(string $processoId, int $limit = 200): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'SELECT * FROM `%s` WHERE `processo_id` = :pid ORDER BY `data_movimentacao` DESC, `created_at` DESC LIMIT %d',
                $this->table,
                $limit
            )
            : sprintf(
                'SELECT m.*
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE gpp.`processo_id` = :pid
                 ORDER BY m.`data_movimentacao` DESC, m.`created_at` DESC
                 LIMIT %d',
                $this->table,
                $limit
            );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':pid', $processoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    private function hasProcessoIdColumn(): bool
    {
        if ($this->hasProcessoIdColumnCache !== null) {
            return $this->hasProcessoIdColumnCache;
        }
        try {
            $st = $this->connection->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
            );
            $st->bindValue(':table', $this->table);
            $st->bindValue(':column', 'processo_id');
            $st->execute();
            $this->hasProcessoIdColumnCache = ((int) $st->fetchColumn()) > 0;
        } catch (Throwable $e) {
            $this->hasProcessoIdColumnCache = false;
        }

        return $this->hasProcessoIdColumnCache;
    }
}
