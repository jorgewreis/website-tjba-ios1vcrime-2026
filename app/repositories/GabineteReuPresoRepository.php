<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteReuPresoRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'gabinete_reu_preso')
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

    public function insert(array $row): void
    {
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `nome`, `status`, `arquivado_em`, `proxima_revisao`, `created_at`, `updated_at`, `user_cadastro`)
            VALUES (:id, :nome, :status, :arquivado_em, :proxima_revisao, NOW(), NOW(), :user_cadastro)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $row['id']);
        $st->bindValue(':nome', $row['nome']);
        $st->bindValue(':status', $row['status'] ?? 'ativo');
        $st->bindValue(':arquivado_em', $row['arquivado_em'] ?? null);
        $st->bindValue(':proxima_revisao', $row['proxima_revisao'] ?? null);
        $st->bindValue(':user_cadastro', $row['user_cadastro'] ?? null);
        $st->execute();
    }

    public function updateNome(string $id, string $nome): bool
    {
        $st = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `nome` = :nome, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':nome', $nome);
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function updateProximaRevisao(string $id, ?string $dataYmd): bool
    {
        $st = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `proxima_revisao` = :d, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':d', $dataYmd);
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    public function updateStatusArquivado(string $id): bool
    {
        $st = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `status` = :st, `arquivado_em` = NOW(), `updated_at` = NOW() WHERE `id` = :id AND `status` = :ativo LIMIT 1',
                $this->table
            )
        );
        $st->bindValue(':st', 'arquivado');
        $st->bindValue(':ativo', 'ativo');
        $st->bindValue(':id', $id);
        $st->execute();

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, string> $filters `q` (busca única: nome OU número de processo), ou legado `nome` + `processo` (AND)
     * @return array{where: list<string>, params: array<string, mixed>}
     */
    private function buildWhereListAtivos(array $filters): array
    {
        $where = ['g.`status` = :ativo'];
        $params = [':ativo' => 'ativo'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $digits = preg_replace('/\D+/', '', $q);
            $orParts = ['g.`nome` LIKE :q_nome'];
            $params[':q_nome'] = $like;
            if ($digits !== '') {
                $orParts[] = 'EXISTS (
                    SELECT 1 FROM `gabinete_reu_preso_processo` gpp2
                    INNER JOIN `processos` p2 ON p2.`id` = gpp2.`processo_id`
                    WHERE gpp2.`reu_preso_id` = g.`id`
                    AND REPLACE(REPLACE(p2.`numero_processo`, \'-\', \'\'), \'.\', \'\') LIKE :q_proc_digits
                )';
                $params[':q_proc_digits'] = '%' . $digits . '%';
            }
            $where[] = '(' . implode(' OR ', $orParts) . ')';

            return ['where' => $where, 'params' => $params];
        }

        $nome = trim((string) ($filters['nome'] ?? ''));
        $processo = trim((string) ($filters['processo'] ?? ''));

        if ($nome !== '') {
            $where[] = 'g.`nome` LIKE :nome';
            $params[':nome'] = '%' . $nome . '%';
        }

        if ($processo !== '') {
            $digits = preg_replace('/\D+/', '', $processo);
            if ($digits !== '') {
                $where[] = 'EXISTS (
                    SELECT 1 FROM `gabinete_reu_preso_processo` gpp2
                    INNER JOIN `processos` p2 ON p2.`id` = gpp2.`processo_id`
                    WHERE gpp2.`reu_preso_id` = g.`id`
                    AND REPLACE(REPLACE(p2.`numero_processo`, \'-\', \'\'), \'.\', \'\') LIKE :proc_digits
                )';
                $params[':proc_digits'] = '%' . $digits . '%';
            }
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * @param array<string, string> $filters ver {@see buildWhereListAtivos()}
     * @return list<array<string, mixed>>
     *
     * Ordenação: tempo de custódia (maior primeiro), usando `data_prisao` do processo principal —
     * datas mais antigas primeiro; sem data de prisão por último; desempate por nome.
     */
    public function fetchListAtivos(array $filters, int $limit, int $offset): array
    {
        $built = $this->buildWhereListAtivos($filters);
        $where = $built['where'];
        $params = $built['params'];
        $whereSql = implode(' AND ', $where);

        $lastMovSql = '(SELECT CONCAT(m.`tipo_codigo`, \'|\', m.`data_movimentacao`) FROM `gabinete_reu_preso_movimentacao` m WHERE m.`reu_preso_id` = g.`id` ORDER BY m.`data_movimentacao` DESC, m.`created_at` DESC LIMIT 1)';

        $sql = sprintf(
            'SELECT g.`id`, g.`nome`, g.`proxima_revisao`, g.`status`,
                    pr.`numero_processo` AS `processo_principal_numero`,
                    pp.`data_prisao` AS `principal_data_prisao`,
                    %s AS `ultima_mov_raw`
             FROM `%s` g
             LEFT JOIN `gabinete_reu_preso_processo` pp ON pp.`reu_preso_id` = g.`id` AND pp.`principal` = 1
             LEFT JOIN `processos` pr ON pr.`id` = pp.`processo_id`
             WHERE %s
             ORDER BY
                 (pp.`data_prisao` IS NULL) ASC,
                 pp.`data_prisao` ASC,
                 g.`nome` ASC
             LIMIT %d OFFSET %d',
            $lastMovSql,
            $this->table,
            $whereSql,
            $limit,
            $offset
        );

        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    public function countListAtivos(array $filters): int
    {
        $built = $this->buildWhereListAtivos($filters);
        $whereSql = implode(' AND ', $built['where']);
        $params = $built['params'];
        $sql = sprintf('SELECT COUNT(*) FROM `%s` g WHERE %s', $this->table, $whereSql);
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();

        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fetchAtivosPresosComRevisaoEntre(string $hojeYmd, string $limiteYmd): array
    {
        $lastMovSql = '(SELECT CONCAT(m.`tipo_codigo`, \'|\', m.`data_movimentacao`) FROM `gabinete_reu_preso_movimentacao` m WHERE m.`reu_preso_id` = g.`id` ORDER BY m.`data_movimentacao` DESC, m.`created_at` DESC LIMIT 1)';
        $sql = sprintf(
            'SELECT g.`id`, g.`nome`, g.`proxima_revisao`,
                    pr.`numero_processo` AS `processo_principal_numero`,
                    pp.`data_prisao` AS `principal_data_prisao`,
                    %s AS `ultima_mov_raw`
             FROM `%s` g
             INNER JOIN `gabinete_reu_preso_processo` pp
                     ON pp.`reu_preso_id` = g.`id`
                    AND pp.`principal` = 1
                    AND pp.`situacao` = :situacao_preso
             LEFT JOIN `processos` pr ON pr.`id` = pp.`processo_id`
             WHERE g.`status` = :status_ativo
               AND g.`proxima_revisao` IS NOT NULL
               AND (g.`proxima_revisao` <= :limite OR g.`proxima_revisao` < :hoje)
             ORDER BY g.`proxima_revisao` ASC, g.`nome` ASC',
            $lastMovSql,
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':status_ativo', 'ativo');
        $st->bindValue(':situacao_preso', 'preso');
        $st->bindValue(':hoje', $hojeYmd);
        $st->bindValue(':limite', $limiteYmd);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }

    /**
     * @return list<string> nomes distintos e normalizados apenas com trim.
     */
    public function listAtivosNomes(): array
    {
        $sql = sprintf('SELECT DISTINCT TRIM(`nome`) AS `nome` FROM `%s` WHERE `status` = :status_ativo ORDER BY `nome` ASC', $this->table);
        $st = $this->connection->prepare($sql);
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
        return array_values(array_unique($nomes));
    }
}
