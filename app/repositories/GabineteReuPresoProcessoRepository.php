<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteReuPresoProcessoRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'gabinete_reu_preso_processo')
    {
        $this->connection = $connection;
        $this->table = $table;
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

    /**
     * @return list<array<string, mixed>>
     */
    public function findByReuPresoIdWithNumero(string $reuPresoId): array
    {
        $reuPresoId = trim($reuPresoId);
        if ($reuPresoId === '') {
            return [];
        }
        $sql = sprintf(
            'SELECT gpp.*, p.`numero_processo`
             FROM `%s` gpp
             INNER JOIN `processos` p ON p.`id` = gpp.`processo_id`
             WHERE gpp.`reu_preso_id` = :rid
             ORDER BY gpp.`principal` DESC, gpp.`created_at` ASC',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':rid', $reuPresoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public function insert(array $row): void
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `reu_preso_id`, `processo_id`, `principal`, `situacao`, `data_prisao`, `data_fato`, `created_at`, `updated_at`)
             VALUES (:id, :reu_preso_id, :processo_id, :principal, :situacao, :data_prisao, :data_fato, NOW(), NOW())',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':reu_preso_id', $row['reu_preso_id']);
        $st->bindValue(':processo_id', $row['processo_id']);
        $st->bindValue(':principal', (int) ($row['principal'] ?? 0), PDO::PARAM_INT);
        $st->bindValue(':situacao', $row['situacao'] ?? 'preso');
        $st->bindValue(':data_prisao', $row['data_prisao'] ?? null);
        $st->bindValue(':data_fato', $row['data_fato'] ?? null);
        $st->execute();
    }

    public function update(string $id, array $fields): bool
    {
        $allowed = ['processo_id', 'principal', 'situacao', 'data_prisao', 'data_fato'];
        $sets = [];
        $params = [':id' => $id];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $sets[] = '`' . $col . '` = :' . $col;
                if ($col === 'principal') {
                    $params[':' . $col] = (int) $fields[$col];
                } elseif ($col === 'processo_id') {
                    $params[':' . $col] = (string) $fields[$col];
                } else {
                    $params[':' . $col] = $fields[$col];
                }
            }
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = '`updated_at` = NOW()';
        $sql = sprintf('UPDATE `%s` SET %s WHERE `id` = :id LIMIT 1', $this->table, implode(', ', $sets));
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function deleteById(string $id): bool
    {
        $st = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function clearPrincipalForReu(string $reuPresoId): void
    {
        $st = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `principal` = 0, `updated_at` = NOW() WHERE `reu_preso_id` = :rid', $this->table)
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->execute();
    }

    public function updateDadosCustodiaByReuPresoId(string $reuPresoId, string $situacao, ?string $dataPrisao, ?string $dataFato): void
    {
        $st = $this->connection->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `situacao` = :situacao, `data_prisao` = :data_prisao, `data_fato` = :data_fato, `updated_at` = NOW()
                 WHERE `reu_preso_id` = :rid',
                $this->table
            )
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->bindValue(':situacao', $situacao);
        $st->bindValue(':data_prisao', $dataPrisao);
        $st->bindValue(':data_fato', $dataFato);
        $st->execute();
    }

    public function countByReuPresoId(string $reuPresoId): int
    {
        $st = $this->connection->prepare(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE `reu_preso_id` = :rid', $this->table)
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->execute();

        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByReuPresoId(string $reuPresoId): array
    {
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `reu_preso_id` = :rid', $this->table)
        );
        $st->bindValue(':rid', $reuPresoId);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * Lista nomes de réus com situação "preso" vinculados ao processo informado.
     *
     * @return list<string>
     */
    public function listReusPresosNomesByProcessoId(string $processoId): array
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT DISTINCT TRIM(grp.`nome`) AS `nome`
             FROM `%s` gpp
             INNER JOIN `gabinete_reu_preso` grp ON grp.`id` = gpp.`reu_preso_id`
             WHERE gpp.`processo_id` = :processo_id
               AND grp.`status` = :status_ativo
             ORDER BY grp.`nome` ASC',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':processo_id', $processoId);
        $st->bindValue(':status_ativo', 'ativo');
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $nomes = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome !== '') {
                $nomes[] = $nome;
            }
        }

        return $nomes;
    }

    /**
     * Fallback: lista nomes de réus presos ativos vinculados ao mesmo CNJ
     * (útil quando o `processo_id` diverge entre módulos, mas o CNJ é o mesmo).
     *
     * @return list<string>
     */
    public function listReusPresosNomesByNumeroProcesso(string $numeroProcesso): array
    {
        $numeroProcesso = trim($numeroProcesso);
        if ($numeroProcesso === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $numeroProcesso);
        if ($digits === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT DISTINCT TRIM(grp.`nome`) AS `nome`
             FROM `%s` gpp
             INNER JOIN `gabinete_reu_preso` grp ON grp.`id` = gpp.`reu_preso_id`
             INNER JOIN `processos` pr ON pr.`id` = gpp.`processo_id`
             WHERE pr.`numero_processo` IS NOT NULL
               AND REPLACE(REPLACE(pr.`numero_processo`, ''-'', ''''), ''.'', '''') LIKE :proc_digits
               AND grp.`status` = :status_ativo
             ORDER BY grp.`nome` ASC',
            $this->table
        );

        $st = $this->connection->prepare($sql);
        $st->bindValue(':proc_digits', '%' . $digits . '%');
        $st->bindValue(':status_ativo', 'ativo');
        $st->execute();

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) {
            return [];
        }

        $nomes = [];
        foreach ($rows as $row) {
            $nome = trim((string) ($row['nome'] ?? ''));
            if ($nome !== '') {
                $nomes[] = $nome;
            }
        }

        return $nomes;
    }

    /**
     * Processos vinculados ao réu que não são o principal (lista na subtarefa da listagem).
     *
     * @param list<string> $reuIds
     * @return array<string, list<string>> reu_preso_id => números no formato CNJ
     */
    public function fetchNumerosNaoPrincipaisByReuIds(array $reuIds): array
    {
        $reuIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $reuIds), static function ($id) {
            return $id !== '';
        }));
        if ($reuIds === []) {
            return [];
        }

        $result = [];
        foreach ($reuIds as $id) {
            $result[$id] = [];
        }

        $placeholders = [];
        $params = [];
        foreach ($reuIds as $i => $id) {
            $k = ':r' . $i;
            $placeholders[] = $k;
            $params[$k] = $id;
        }
        $in = implode(',', $placeholders);
        $sql = sprintf(
            'SELECT gpp.`reu_preso_id`, p.`numero_processo`
             FROM `%s` gpp
             INNER JOIN `processos` p ON p.`id` = gpp.`processo_id`
             WHERE gpp.`principal` = 0 AND gpp.`reu_preso_id` IN (%s)
             ORDER BY gpp.`reu_preso_id` ASC, gpp.`created_at` ASC',
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
            return $result;
        }
        foreach ($rows as $row) {
            $rid = trim((string) ($row['reu_preso_id'] ?? ''));
            $num = trim((string) ($row['numero_processo'] ?? ''));
            if ($rid !== '' && $num !== '' && isset($result[$rid])) {
                $result[$rid][] = $num;
            }
        }

        return $result;
    }

    /**
     * Processos vinculados ao réu (para subtarefa na listagem): número, classe, flag principal.
     * Ordem: principal primeiro, depois demais por data de criação do vínculo.
     *
     * @param list<string> $reuIds
     * @return list<array<string, mixed>>
     */
    public function fetchProcessosSubtarefaPorReuIds(array $reuIds): array
    {
        $reuIds = array_values(array_filter(array_map(static function ($id) {
            return trim((string) $id);
        }, $reuIds), static function ($id) {
            return $id !== '';
        }));
        if ($reuIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($reuIds as $i => $id) {
            $k = ':r' . $i;
            $placeholders[] = $k;
            $params[$k] = $id;
        }
        $in = implode(',', $placeholders);
        $sql = sprintf(
            'SELECT gpp.`id`, gpp.`reu_preso_id`, gpp.`processo_id`, gpp.`principal`, p.`numero_processo`,
                    COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'\') AS `classe_nome`
             FROM `%s` gpp
             INNER JOIN `processos` p ON p.`id` = gpp.`processo_id`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             WHERE gpp.`reu_preso_id` IN (%s)
             ORDER BY gpp.`reu_preso_id` ASC, gpp.`principal` DESC, gpp.`created_at` ASC',
            $this->table,
            $in
        );
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
