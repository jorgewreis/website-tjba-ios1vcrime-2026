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
            'INSERT INTO `%s` (`id`, `reu_preso_id`, `reu_preso_processo_id`, `tipo_codigo`, `data_movimentacao`, `observacoes`, `created_at`, `user_cadastro`)
             VALUES (:id, :reu_preso_id, :reu_preso_processo_id, :tipo_codigo, :data_movimentacao, :observacoes, NOW(), :user_cadastro)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':reu_preso_id', $row['reu_preso_id']);
        $st->bindValue(':reu_preso_processo_id', $row['reu_preso_processo_id'] ?? null);
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
}
