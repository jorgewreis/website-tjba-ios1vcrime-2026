<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProdutividadeBadgesRepository
{
    private PDO $connection;
    private string $table;
    private string $tableGranted;

    public function __construct(PDO $connection, string $table = 'produtividade_badges', string $tableGranted = 'produtividade_badges_concedidos')
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->tableGranted = $tableGranted;
    }

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT b.`id`, b.`nome`, b.`descricao`, b.`icone`, b.`tipo`, b.`regra_id`, b.`valor_threshold`, b.`periodo`, b.`ativo`, b.`criado_em`,
                    r.`tipo_ato`, r.`tipo_documento`, r.`sistema_modulo`
             FROM `%s` b
             LEFT JOIN `produtividade_regras` r ON r.`id` = b.`regra_id`
             WHERE b.`id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT b.`id`, b.`nome`, b.`descricao`, b.`icone`, b.`tipo`, b.`regra_id`, b.`valor_threshold`, b.`periodo`, b.`ativo`, b.`criado_em`,
                    r.`tipo_ato`, r.`tipo_documento`, r.`sistema_modulo`
             FROM `%s` b
             LEFT JOIN `produtividade_regras` r ON r.`id` = b.`regra_id`
             %s
             ORDER BY b.`ativo` DESC, b.`nome` ASC, b.`criado_em` DESC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT COUNT(*)
             FROM `%s` b
             %s',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function insert(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PBG', 30);
        $sql = sprintf(
            'INSERT INTO `%s`
                (`id`, `nome`, `descricao`, `icone`, `tipo`, `regra_id`, `valor_threshold`, `periodo`, `ativo`)
             VALUES
                (:id, :nome, :descricao, :icone, :tipo, :regra_id, :valor_threshold, :periodo, :ativo)',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':nome', $row['nome']);
        $statement->bindValue(':descricao', $row['descricao'] ?? null);
        $statement->bindValue(':icone', $row['icone'] ?? null);
        $statement->bindValue(':tipo', $row['tipo']);
        $statement->bindValue(':regra_id', $row['regra_id'] ?? null);
        $statement->bindValue(':valor_threshold', $row['valor_threshold']);
        $statement->bindValue(':periodo', $row['periodo']);
        $statement->bindValue(':ativo', ((int) ($row['ativo'] ?? 1)) === 1 ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `nome` = :nome,
                 `descricao` = :descricao,
                 `icone` = :icone,
                 `tipo` = :tipo,
                 `regra_id` = :regra_id,
                 `valor_threshold` = :valor_threshold,
                 `periodo` = :periodo,
                 `ativo` = :ativo
             WHERE `id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':nome', $row['nome']);
        $statement->bindValue(':descricao', $row['descricao'] ?? null);
        $statement->bindValue(':icone', $row['icone'] ?? null);
        $statement->bindValue(':tipo', $row['tipo']);
        $statement->bindValue(':regra_id', $row['regra_id'] ?? null);
        $statement->bindValue(':valor_threshold', $row['valor_threshold']);
        $statement->bindValue(':periodo', $row['periodo']);
        $statement->bindValue(':ativo', ((int) ($row['ativo'] ?? 1)) === 1 ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function setAtivo(string $id, bool $ativo): bool
    {
        $sql = sprintf('UPDATE `%s` SET `ativo` = :ativo WHERE `id` = :id LIMIT 1', $this->table);
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':ativo', $ativo ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listAtivos(): array
    {
        $sql = sprintf(
            'SELECT `id`, `nome`, `descricao`, `icone`, `tipo`, `regra_id`, `valor_threshold`, `periodo`, `ativo`
             FROM `%s`
             WHERE `ativo` = 1
             ORDER BY `nome` ASC',
            $this->table
        );
        $statement = $this->connection->query($sql);
        if ($statement === false) {
            return [];
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function concederSeNaoExiste(string $badgeId, string $usuarioId): bool
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->tableGranted, 'id', 'PBC', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `badge_id`, `usuario_id`, `notificado`)
             VALUES (:id, :badge_id, :usuario_id, 0)',
            $this->tableGranted
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':badge_id', $badgeId);
        $statement->bindValue(':usuario_id', $usuarioId);

        try {
            $statement->execute();
            return true;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listPendentesByUsuario(string $usuarioId, int $limit = 10): array
    {
        $sql = sprintf(
            'SELECT bc.`id` AS concedido_id, bc.`badge_id`, bc.`usuario_id`, bc.`concedido_em`,
                    b.`nome`, b.`descricao`, b.`icone`, b.`tipo`, b.`periodo`, b.`valor_threshold`
             FROM `%s` bc
             INNER JOIN `%s` b ON b.`id` = bc.`badge_id`
             WHERE bc.`usuario_id` = :usuario_id
               AND bc.`notificado` = 0
             ORDER BY bc.`concedido_em` DESC
             LIMIT :limit',
            $this->tableGranted,
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Badges já concedidos (definição ativa) para os usuários indicados — ex.: ranking.
     *
     * @param list<string> $usuarioIds
     * @return array<string, list<array{nome: string, icone: string}>>
     */
    public function listConcedidosGroupedByUsuarioId(array $usuarioIds): array
    {
        $unique = [];
        foreach ($usuarioIds as $uid) {
            $t = trim((string) $uid);
            if ($t !== '') {
                $unique[$t] = true;
            }
        }
        $list = array_keys($unique);
        $out = [];
        foreach ($list as $uid) {
            $out[$uid] = [];
        }
        if ($list === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($list) as $i => $uid) {
            $key = ':uid' . $i;
            $placeholders[] = $key;
            $params[$key] = $uid;
        }
        $inClause = implode(', ', $placeholders);
        $sql = sprintf(
            'SELECT bc.`usuario_id`, b.`nome`, b.`icone`
             FROM `%s` bc
             INNER JOIN `%s` b ON b.`id` = bc.`badge_id` AND b.`ativo` = 1
             WHERE bc.`usuario_id` IN (%s)
             ORDER BY bc.`usuario_id` ASC, b.`nome` ASC',
            $this->tableGranted,
            $this->table,
            $inClause
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (string) ($row['usuario_id'] ?? '');
            if ($uid === '' || !array_key_exists($uid, $out)) {
                continue;
            }
            $out[$uid][] = [
                'nome' => (string) ($row['nome'] ?? ''),
                'icone' => trim((string) ($row['icone'] ?? '')),
            ];
        }

        return $out;
    }

    public function marcarNotificado(string $concedidoId, string $usuarioId): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `notificado` = 1
             WHERE `id` = :id
               AND `usuario_id` = :usuario_id
             LIMIT 1',
            $this->tableGranted
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $concedidoId);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * @return array{0:list<string>,1:array<string,mixed>}
     */
    private function buildFilter(array $filters): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(b.`nome` LIKE :q_nome OR b.`descricao` LIKE :q_desc)';
            $params[':q_nome'] = $like;
            $params[':q_desc'] = $like;
        }

        $ativo = $filters['ativo'] ?? null;
        if ($ativo !== null && $ativo !== '') {
            $where[] = 'b.`ativo` = :ativo';
            $params[':ativo'] = ((int) $ativo) === 1 ? 1 : 0;
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'b.`tipo` = :tipo';
            $params[':tipo'] = $tipo;
        }

        return [$where, $params];
    }
}
