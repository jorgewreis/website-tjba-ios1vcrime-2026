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

    public function existsByReuProcessoTipoData(string $reuPresoId, string $processoId, string $tipoCodigo, string $dataMovimentacao): bool
    {
        $reuPresoId = trim($reuPresoId);
        $processoId = trim($processoId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($reuPresoId === '' || $processoId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return false;
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'SELECT 1
                 FROM `%s` m
                 WHERE m.`reu_preso_id` = :reu AND m.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            )
            : sprintf(
                'SELECT 1
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE m.`reu_preso_id` = :reu AND gpp.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':reu', $reuPresoId);
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
    public function maxDataMovimentacaoForTipoPrefixesByReuAndProcessos(string $reuPresoId, array $processoIds, array $prefixes): ?string
    {
        $reuPresoId = trim($reuPresoId);
        if ($reuPresoId === '') {
            return null;
        }
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
        $params = [':reu' => $reuPresoId];
        foreach ($processoIds as $i => $pid) {
            $k = ':pid' . $i;
            $pidPlaceholders[] = $k;
            $params[$k] = $pid;
        }
        $whereLike = [];
        foreach ($prefixes as $i => $prefix) {
            $k = ':pf' . $i;
            $whereLike[] = 'm.`tipo_codigo` LIKE ' . $k;
            $params[$k] = $prefix . '%';
        }
        $sql = $this->hasProcessoIdColumn()
            ? sprintf(
                'SELECT MAX(m.`data_movimentacao`)
                 FROM `%s` m
                 WHERE m.`reu_preso_id` = :reu AND m.`processo_id` IN (%s) AND (%s)',
                $this->table,
                implode(',', $pidPlaceholders),
                implode(' OR ', $whereLike)
            )
            : sprintf(
                'SELECT MAX(m.`data_movimentacao`)
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE m.`reu_preso_id` = :reu AND gpp.`processo_id` IN (%s) AND (%s)',
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

    public function deleteById(string $id): int
    {
        $id = trim($id);
        if ($id === '') {
            return 0;
        }
        $st = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':id', $id);
        $st->execute();

        return (int) $st->rowCount();
    }

    /**
     * @param list<array{0: string, 1: string}> $pairs lista de pares (reu_preso_id, processo_id)
     * @return array<string, array<string, mixed>> mapa "reuId|processoId" => linha da última movimentação
     */
    public function fetchUltimaPorReuPresoEProcessoIds(array $pairs): array
    {
        $wanted = [];
        $reuIds = [];
        $processoIds = [];
        foreach ($pairs as $p) {
            if (!is_array($p) || !isset($p[0], $p[1])) {
                continue;
            }
            $r = trim((string) $p[0]);
            $pid = trim((string) $p[1]);
            if ($r === '' || $pid === '') {
                continue;
            }
            $key = $r . '|' . $pid;
            if (isset($wanted[$key])) {
                continue;
            }
            $wanted[$key] = true;
            $reuIds[$r] = true;
            $processoIds[$pid] = true;
        }
        if ($wanted === []) {
            return [];
        }

        $reuArr = array_keys($reuIds);
        $pidArr = array_keys($processoIds);
        $reuPh = [];
        $pidPh = [];
        $params = [];
        foreach ($reuArr as $i => $r) {
            $k = ':r' . $i;
            $reuPh[] = $k;
            $params[$k] = $r;
        }
        foreach ($pidArr as $i => $pid) {
            $k = ':p' . $i;
            $pidPh[] = $k;
            $params[$k] = $pid;
        }

        $hasProcessoId = $this->hasProcessoIdColumn();
        $sql = $hasProcessoId
            ? sprintf(
                'SELECT m.`id`, m.`reu_preso_id`, m.`processo_id`, m.`tipo_codigo`, m.`data_movimentacao`, m.`observacoes`
                 FROM `%s` m
                 WHERE m.`reu_preso_id` IN (%s) AND m.`processo_id` IN (%s)
                 ORDER BY m.`reu_preso_id` ASC, m.`processo_id` ASC, m.`data_movimentacao` DESC, m.`created_at` DESC',
                $this->table,
                implode(',', $reuPh),
                implode(',', $pidPh)
            )
            : sprintf(
                'SELECT m.`id`, m.`reu_preso_id`, gpp.`processo_id`, m.`tipo_codigo`, m.`data_movimentacao`, m.`observacoes`
                 FROM `%s` m
                 INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
                 WHERE m.`reu_preso_id` IN (%s) AND gpp.`processo_id` IN (%s)
                 ORDER BY m.`reu_preso_id` ASC, gpp.`processo_id` ASC, m.`data_movimentacao` DESC, m.`created_at` DESC',
                $this->table,
                implode(',', $reuPh),
                implode(',', $pidPh)
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
            $r = trim((string) ($row['reu_preso_id'] ?? ''));
            $pid = trim((string) ($row['processo_id'] ?? ''));
            if ($r === '' || $pid === '') {
                continue;
            }
            $key = $r . '|' . $pid;
            if (!isset($wanted[$key])) {
                continue;
            }
            if (!isset($out[$key])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByReuPresoIdOrderDesc(string $reuPresoId, int $limit = 200): array
    {
        $reuPresoId = trim($reuPresoId);
        if ($reuPresoId === '') {
            return [];
        }
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `reu_preso_id` = :reu ORDER BY `data_movimentacao` DESC, `created_at` DESC LIMIT %d',
            $this->table,
            $limit
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':reu', $reuPresoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @param list<string> $reuIds
     * @param list<string> $prefixes
     * @return array<string, list<array<string,mixed>>>
     */
    public function fetchUltimasPorReuIdsComPrefixos(array $reuIds, array $prefixes, int $maxPorReu = 3): array
    {
        $ids = array_values(array_filter(array_map(static function ($v): string {
            return trim((string) $v);
        }, $reuIds), static function (string $v): bool {
            return $v !== '';
        }));
        $prefixes = array_values(array_filter(array_map(static function ($v): string {
            return trim((string) $v);
        }, $prefixes), static function (string $v): bool {
            return $v !== '';
        }));
        $maxPorReu = max(1, (int) $maxPorReu);

        if ($ids === [] || $prefixes === []) {
            return [];
        }

        $reuPh = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $k = ':reu' . $i;
            $reuPh[] = $k;
            $params[$k] = $id;
        }

        $whereLike = [];
        foreach ($prefixes as $i => $prefix) {
            $k = ':pf' . $i;
            $whereLike[] = 'm.`tipo_codigo` LIKE ' . $k;
            $params[$k] = $prefix . '%';
        }

        $sql = sprintf(
            'SELECT m.`id`, m.`reu_preso_id`, m.`tipo_codigo`, m.`data_movimentacao`, m.`observacoes`, m.`created_at`
             FROM `%s` m
             WHERE m.`reu_preso_id` IN (%s)
               AND (%s)
             ORDER BY m.`reu_preso_id` ASC, m.`data_movimentacao` DESC, m.`created_at` DESC',
            $this->table,
            implode(',', $reuPh),
            implode(' OR ', $whereLike)
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
            $rid = trim((string) ($row['reu_preso_id'] ?? ''));
            if ($rid === '') {
                continue;
            }
            if (!isset($out[$rid])) {
                $out[$rid] = [];
            }
            if (count($out[$rid]) >= $maxPorReu) {
                continue;
            }
            $out[$rid][] = $row;
        }

        return $out;
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
