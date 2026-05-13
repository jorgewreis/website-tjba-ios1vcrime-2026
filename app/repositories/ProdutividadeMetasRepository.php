<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProdutividadeMetasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'produtividade_metas')
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

        $sql = sprintf(
            'SELECT `id`, `tipo_alvo`, `alvo_valor`, `periodo_tipo`, `vigencia_inicio`, `vigencia_fim`, `pontos_meta`, `ativo`, `criado_por`, `criado_em`
             FROM `%s`
             WHERE `id` = :id
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
     * @return list<array<string, mixed>>
     */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT m.`id`, m.`tipo_alvo`, m.`alvo_valor`, m.`periodo_tipo`, m.`vigencia_inicio`, m.`vigencia_fim`, m.`pontos_meta`, m.`ativo`, m.`criado_por`, m.`criado_em`,
                    CONCAT(COALESCE(u.`nome`, \'\'), CASE WHEN COALESCE(u.`sobrenome`, \'\') = \'\' THEN \'\' ELSE CONCAT(\' \', u.`sobrenome`) END) AS criado_por_nome
             FROM `%s` m
             LEFT JOIN `usuarios` u ON u.`id_usuario` = m.`criado_por`
             %s
             ORDER BY m.`ativo` DESC, m.`criado_em` DESC, m.`id` DESC
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
             FROM `%s` m
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
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PMT', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `tipo_alvo`, `alvo_valor`, `periodo_tipo`, `vigencia_inicio`, `vigencia_fim`, `pontos_meta`, `ativo`, `criado_por`)
             VALUES (:id, :tipo_alvo, :alvo_valor, :periodo_tipo, :vigencia_inicio, :vigencia_fim, :pontos_meta, :ativo, :criado_por)',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tipo_alvo', $row['tipo_alvo']);
        $statement->bindValue(':alvo_valor', $row['alvo_valor']);
        $statement->bindValue(':periodo_tipo', $row['periodo_tipo']);
        $statement->bindValue(':vigencia_inicio', $row['vigencia_inicio']);
        $statement->bindValue(':vigencia_fim', $row['vigencia_fim'] ?? null);
        $statement->bindValue(':pontos_meta', $row['pontos_meta']);
        $statement->bindValue(':ativo', ((int) ($row['ativo'] ?? 1)) === 1 ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':criado_por', $row['criado_por'] ?? null);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `tipo_alvo` = :tipo_alvo,
                 `alvo_valor` = :alvo_valor,
                 `periodo_tipo` = :periodo_tipo,
                 `vigencia_inicio` = :vigencia_inicio,
                 `vigencia_fim` = :vigencia_fim,
                 `pontos_meta` = :pontos_meta,
                 `ativo` = :ativo
             WHERE `id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':tipo_alvo', $row['tipo_alvo']);
        $statement->bindValue(':alvo_valor', $row['alvo_valor']);
        $statement->bindValue(':periodo_tipo', $row['periodo_tipo']);
        $statement->bindValue(':vigencia_inicio', $row['vigencia_inicio']);
        $statement->bindValue(':vigencia_fim', $row['vigencia_fim'] ?? null);
        $statement->bindValue(':pontos_meta', $row['pontos_meta']);
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
     * @return array{0:list<string>,1:array<string,mixed>}
     */
    private function buildFilter(array $filters): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(m.`tipo_alvo` LIKE :q_tipo OR m.`alvo_valor` LIKE :q_alvo OR m.`periodo_tipo` LIKE :q_periodo)';
            $params[':q_tipo'] = $like;
            $params[':q_alvo'] = $like;
            $params[':q_periodo'] = $like;
        }

        $ativo = $filters['ativo'] ?? null;
        if ($ativo !== null && $ativo !== '') {
            $where[] = 'm.`ativo` = :ativo';
            $params[':ativo'] = ((int) $ativo) === 1 ? 1 : 0;
        }

        $tipoAlvo = trim((string) ($filters['tipo_alvo'] ?? ''));
        if ($tipoAlvo !== '') {
            $where[] = 'm.`tipo_alvo` = :tipo_alvo';
            $params[':tipo_alvo'] = $tipoAlvo;
        }

        $periodo = trim((string) ($filters['periodo_tipo'] ?? ''));
        if ($periodo !== '') {
            $where[] = 'm.`periodo_tipo` = :periodo_tipo';
            $params[':periodo_tipo'] = $periodo;
        }

        return [$where, $params];
    }
}

