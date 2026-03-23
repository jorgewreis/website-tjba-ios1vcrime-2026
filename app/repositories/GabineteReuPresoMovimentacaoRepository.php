<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteReuPresoMovimentacaoRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'gabinete_reu_preso_movimentacao')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function insert(array $row): void
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `reu_preso_id`, `reu_preso_processo_id`, `processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`, `created_at`, `user_cadastro`)
             VALUES (:id, :reu_preso_id, :reu_preso_processo_id, :processo_id, :tipo_codigo, :data_movimentacao, :observacoes, NOW(), :user_cadastro)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':reu_preso_id', $row['reu_preso_id']);
        $st->bindValue(':reu_preso_processo_id', $row['reu_preso_processo_id'] ?? null);
        $st->bindValue(':processo_id', $row['processo_id'] ?? null);
        $st->bindValue(':tipo_codigo', $row['tipo_codigo']);
        $st->bindValue(':data_movimentacao', $row['data_movimentacao']);
        $st->bindValue(':observacoes', $row['observacoes'] ?? null);
        $st->bindValue(':user_cadastro', $row['user_cadastro'] ?? null);
        $st->execute();
    }

    /**
     * @param list<string> $tipos
     */
    public function maxDataMovimentacaoForTipos(string $reuPresoId, array $tipos): ?string
    {
        $reuPresoId = trim($reuPresoId);
        if ($reuPresoId === '' || $tipos === []) {
            return null;
        }
        $placeholders = [];
        $params = [':rid' => $reuPresoId];
        foreach ($tipos as $i => $t) {
            $k = ':t' . $i;
            $placeholders[] = $k;
            $params[$k] = $t;
        }
        $in = implode(',', $placeholders);
        $sql = sprintf(
            'SELECT MAX(`data_movimentacao`) FROM `%s` WHERE `reu_preso_id` = :rid AND `tipo_codigo` IN (%s)',
            $this->table,
            $in
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

    /**
     * @return list<array<string, mixed>>
     */
    public function findByReuPresoIdOrderDesc(string $reuPresoId, int $limit = 200): array
    {
        $st = $this->connection->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `reu_preso_id` = :rid ORDER BY `data_movimentacao` DESC, `created_at` DESC LIMIT %d',
                $this->table,
                $limit
            )
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public function findLastByReuPresoId(string $reuPresoId): ?array
    {
        $rows = $this->findByReuPresoIdOrderDesc($reuPresoId, 1);

        return $rows[0] ?? null;
    }

    public function clearLinkByReuPresoProcessoId(string $linkId): void
    {
        $linkId = trim($linkId);
        if ($linkId === '') {
            return;
        }
        $st = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `reu_preso_processo_id` = NULL WHERE `reu_preso_processo_id` = :lid',
                $this->table
            )
        );
        $st->bindValue(':lid', $linkId);
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

    public function deleteByReuTipoData(string $reuPresoId, string $tipoCodigo, string $dataMovimentacao): int
    {
        $reuPresoId = trim($reuPresoId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($reuPresoId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return 0;
        }
        $st = $this->connection->prepare(
            sprintf(
                'DELETE FROM `%s` WHERE `reu_preso_id` = :rid AND `tipo_codigo` = :tipo AND `data_movimentacao` = :dt',
                $this->table
            )
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return (int) $st->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByReuPresoProcessoIdOrderDesc(string $linkId, int $limit = 200): array
    {
        $linkId = trim($linkId);
        if ($linkId === '') {
            return [];
        }
        $st = $this->connection->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `reu_preso_processo_id` = :lid ORDER BY `data_movimentacao` DESC, `created_at` DESC LIMIT %d',
                $this->table,
                $limit
            )
        );
        $st->bindValue(':lid', $linkId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public function existsByLinkTipoData(string $linkId, string $tipoCodigo, string $dataMovimentacao): bool
    {
        $linkId = trim($linkId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($linkId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return false;
        }
        $st = $this->connection->prepare(
            sprintf(
                'SELECT 1 FROM `%s`
                 WHERE `reu_preso_processo_id` = :lid AND `tipo_codigo` = :tipo AND `data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            )
        );
        $st->bindValue(':lid', $linkId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return $st->fetchColumn() !== false;
    }

    public function existsByProcessoTipoData(string $processoId, string $tipoCodigo, string $dataMovimentacao): bool
    {
        $processoId = trim($processoId);
        $tipoCodigo = trim($tipoCodigo);
        $dataMovimentacao = trim($dataMovimentacao);
        if ($processoId === '' || $tipoCodigo === '' || $dataMovimentacao === '') {
            return false;
        }
        $st = $this->connection->prepare(
            sprintf(
                'SELECT 1
                 FROM `%s` m
                 WHERE m.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt
                 LIMIT 1',
                $this->table
            )
        );
        $st->bindValue(':pid', $processoId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return $st->fetchColumn() !== false;
    }

    /**
     * @param list<string> $prefixes
     */
    public function maxDataMovimentacaoForTipoPrefixes(string $reuPresoId, array $prefixes): ?string
    {
        $reuPresoId = trim($reuPresoId);
        $prefixes = array_values(array_filter(array_map(static function ($p) {
            return trim((string) $p);
        }, $prefixes), static function ($p) {
            return $p !== '';
        }));
        if ($reuPresoId === '' || $prefixes === []) {
            return null;
        }

        $whereLike = [];
        $params = [':rid' => $reuPresoId];
        foreach ($prefixes as $i => $prefix) {
            $k = ':pf' . $i;
            $whereLike[] = '`tipo_codigo` LIKE ' . $k;
            $params[$k] = '%' . $prefix . '%';
        }
        $sql = sprintf(
            'SELECT MAX(`data_movimentacao`) FROM `%s` WHERE `reu_preso_id` = :rid AND (%s)',
            $this->table,
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
        $sql = sprintf(
            'SELECT MAX(m.`data_movimentacao`)
             FROM `%s` m
             WHERE m.`processo_id` IN (%s) AND (%s)',
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
        $st = $this->connection->prepare(
            sprintf(
                'DELETE m
                 FROM `%s` m
                 WHERE m.`processo_id` = :pid AND m.`tipo_codigo` = :tipo AND m.`data_movimentacao` = :dt',
                $this->table
            )
        );
        $st->bindValue(':pid', $processoId);
        $st->bindValue(':tipo', $tipoCodigo);
        $st->bindValue(':dt', $dataMovimentacao);
        $st->execute();

        return (int) $st->rowCount();
    }

    /**
     * Última movimentação por vínculo processo (reu_preso_processo_id).
     *
     * @param list<string> $linkIds ids de gabinete_reu_preso_processo
     * @return array<string, array<string, mixed>> link_id => linha da movimentação
     */
    public function fetchUltimaPorReuPresoProcessoIds(array $linkIds): array
    {
        $linkIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $linkIds), static function ($id) {
            return $id !== '';
        }));
        if ($linkIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($linkIds as $i => $id) {
            $k = ':l' . $i;
            $placeholders[] = $k;
            $params[$k] = $id;
        }
        $in = implode(',', $placeholders);
        $sql = sprintf(
            'SELECT `id`, `reu_preso_processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`
             FROM `%s`
             WHERE `reu_preso_processo_id` IN (%s)
             ORDER BY `reu_preso_processo_id` ASC, `data_movimentacao` DESC, `created_at` DESC',
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
            $lid = trim((string) ($row['reu_preso_processo_id'] ?? ''));
            if ($lid !== '' && !isset($out[$lid])) {
                $out[$lid] = $row;
            }
        }

        return $out;
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
        $sql = sprintf(
            'SELECT `id`, `processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`
             FROM `%s`
             WHERE `processo_id` IN (%s)
             ORDER BY `processo_id` ASC, `data_movimentacao` DESC, `created_at` DESC',
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
        $st = $this->connection->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `processo_id` = :pid ORDER BY `data_movimentacao` DESC, `created_at` DESC LIMIT %d',
                $this->table,
                $limit
            )
        );
        $st->bindValue(':pid', $processoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
