<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class ProdutividadeLancamentosRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'produtividade_lancamentos')
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
            'SELECT l.*,
                    r.`tipo_ato`, r.`tipo_documento`, r.`sistema_modulo`,
                    CONCAT(COALESCE(u.`nome`, \'\'), CASE WHEN COALESCE(u.`sobrenome`, \'\') = \'\' THEN \'\' ELSE CONCAT(\' \', u.`sobrenome`) END) AS usuario_nome
             FROM `%s` l
             LEFT JOIN `produtividade_regras` r ON r.`id` = l.`regra_id`
             LEFT JOIN `usuarios` u ON u.`id_usuario` = l.`usuario_id`
             WHERE l.`id` = :id
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function insert(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'PLC', 30);
        $sql = sprintf(
            'INSERT INTO `%s`
                (`id`, `usuario_id`, `regra_id`, `tipo_lancamento`, `status`, `quantidade`, `pontos_calculados`, `contexto_hash`, `referencia_item_id`, `referencia_sistema`, `numero_processo`, `classe_processo`, `observacoes`, `data_hora_ato`)
             VALUES
                (:id, :usuario_id, :regra_id, :tipo_lancamento, :status, :quantidade, :pontos_calculados, :contexto_hash, :referencia_item_id, :referencia_sistema, :numero_processo, :classe_processo, :observacoes, :data_hora_ato)',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':usuario_id', $row['usuario_id']);
        $statement->bindValue(':regra_id', $row['regra_id'] ?? null);
        $statement->bindValue(':tipo_lancamento', $row['tipo_lancamento']);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':quantidade', (int) $row['quantidade'], PDO::PARAM_INT);
        $statement->bindValue(':pontos_calculados', $row['pontos_calculados']);
        $statement->bindValue(':contexto_hash', $row['contexto_hash'] ?? null);
        $statement->bindValue(':referencia_item_id', $row['referencia_item_id'] ?? null);
        $statement->bindValue(':referencia_sistema', $row['referencia_sistema'] ?? null);
        $statement->bindValue(':numero_processo', $row['numero_processo'] ?? null);
        $statement->bindValue(':classe_processo', $row['classe_processo'] ?? null);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':data_hora_ato', $row['data_hora_ato']);
        $statement->execute();

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT l.`id`, l.`usuario_id`, l.`regra_id`, l.`tipo_lancamento`, l.`status`, l.`quantidade`, l.`pontos_calculados`,
                    l.`numero_processo`, l.`classe_processo`, l.`observacoes`, l.`data_hora_ato`, l.`criado_em`, l.`anulado_por`, l.`anulado_em`, l.`motivo_anulacao`,
                    r.`tipo_ato`, r.`tipo_documento`, r.`sistema_modulo`,
                    CONCAT(COALESCE(u.`nome`, \'\'), CASE WHEN COALESCE(u.`sobrenome`, \'\') = \'\' THEN \'\' ELSE CONCAT(\' \', u.`sobrenome`) END) AS usuario_nome
             FROM `%s` l
             LEFT JOIN `produtividade_regras` r ON r.`id` = l.`regra_id`
             LEFT JOIN `usuarios` u ON u.`id_usuario` = l.`usuario_id`
             %s
             ORDER BY l.`data_hora_ato` DESC, l.`id` DESC
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
             FROM `%s` l
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

    public function sumPontos(array $filters): float
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT COALESCE(SUM(l.`pontos_calculados`), 0)
             FROM `%s` l
             %s',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return (float) $statement->fetchColumn();
    }


    public function sumNetAutomaticoAtivo(string $referenciaItemId, string $referenciaSistema): float
    {
        $referenciaItemId = trim($referenciaItemId);
        $referenciaSistema = trim($referenciaSistema);
        if ($referenciaItemId === '' || $referenciaSistema === '') {
            return 0.0;
        }

        $sql = sprintf(
            'SELECT COALESCE(SUM(l.`pontos_calculados`), 0)
             FROM `%s` l
             WHERE l.`referencia_item_id` = :ref_id
               AND l.`referencia_sistema` = :ref_sys
               AND l.`tipo_lancamento` = \'automatico\'
               AND l.`status` = \'ativo\'',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':ref_id', $referenciaItemId);
        $statement->bindValue(':ref_sys', $referenciaSistema);
        $statement->execute();

        return (float) $statement->fetchColumn();
    }

    public function anular(string $id, string $anuladoPor, string $motivo): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `status` = \'anulado\',
                 `anulado_por` = :anulado_por,
                 `anulado_em` = NOW(),
                 `motivo_anulacao` = :motivo
             WHERE `id` = :id
               AND `status` = \'ativo\'
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':anulado_por', $anuladoPor);
        $statement->bindValue(':motivo', $motivo);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchForExport(array $filters, int $limit = 5000): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $sql = sprintf(
            'SELECT l.`id`, l.`usuario_id`, l.`regra_id`, l.`tipo_lancamento`, l.`status`, l.`quantidade`, l.`pontos_calculados`,
                    l.`numero_processo`, l.`classe_processo`, l.`observacoes`, l.`data_hora_ato`, l.`criado_em`, l.`anulado_por`, l.`anulado_em`, l.`motivo_anulacao`,
                    r.`tipo_ato`, r.`tipo_documento`, r.`sistema_modulo`,
                    CONCAT(COALESCE(u.`nome`, \'\'), CASE WHEN COALESCE(u.`sobrenome`, \'\') = \'\' THEN \'\' ELSE CONCAT(\' \', u.`sobrenome`) END) AS usuario_nome
             FROM `%s` l
             LEFT JOIN `produtividade_regras` r ON r.`id` = l.`regra_id`
             LEFT JOIN `usuarios` u ON u.`id_usuario` = l.`usuario_id`
             %s
             ORDER BY l.`data_hora_ato` DESC, l.`id` DESC
             LIMIT :limit',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', max(1, min(10000, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchRanking(array $filters, int $limit = 200): array
    {
        [$where, $params] = $this->buildRankingFilter($filters);
        $sql = sprintf(
            'SELECT l.`usuario_id`,
                    CONCAT(COALESCE(u.`nome`, \'\'), CASE WHEN COALESCE(u.`sobrenome`, \'\') = \'\' THEN \'\' ELSE CONCAT(\' \', u.`sobrenome`) END) AS usuario_nome,
                    COALESCE(s.`nome`, \'Sem setor\') AS setor_nome,
                    UPPER(COALESCE(s.`codigo_area`, \'\')) AS setor_codigo_area,
                    COALESCE(SUM(l.`pontos_calculados`), 0) AS pontos_total,
                    COUNT(*) AS total_lancamentos
             FROM `%s` l
             LEFT JOIN `usuarios` u ON u.`id_usuario` = l.`usuario_id`
             LEFT JOIN `usuario_setor` us ON us.`usuario` = l.`usuario_id`
             LEFT JOIN `setores` s ON s.`id_setor` = us.`setor`
             %s
             GROUP BY l.`usuario_id`, usuario_nome, setor_nome, setor_codigo_area
             ORDER BY pontos_total DESC, usuario_nome ASC
             LIMIT :limit',
            $this->table,
            $where !== [] ? 'WHERE ' . implode(' AND ', $where) : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{0:list<string>,1:array<string,mixed>}
     */
    private function buildFilter(array $filters): array
    {
        $where = [];
        $params = [];

        $usuarioId = trim((string) ($filters['usuario_id'] ?? ''));
        if ($usuarioId !== '') {
            $where[] = 'l.`usuario_id` = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'l.`status` = :status';
            $params[':status'] = $status;
        }

        $tipo = trim((string) ($filters['tipo_lancamento'] ?? ''));
        if ($tipo !== '') {
            $where[] = 'l.`tipo_lancamento` = :tipo_lancamento';
            $params[':tipo_lancamento'] = $tipo;
        }

        $dataInicio = trim((string) ($filters['data_inicio'] ?? ''));
        if ($dataInicio !== '') {
            $where[] = 'l.`data_hora_ato` >= :data_inicio';
            $params[':data_inicio'] = $dataInicio . ' 00:00:00';
        }

        $dataFim = trim((string) ($filters['data_fim'] ?? ''));
        if ($dataFim !== '') {
            $where[] = 'l.`data_hora_ato` <= :data_fim';
            $params[':data_fim'] = $dataFim . ' 23:59:59';
        }

        $setorCodigoArea = strtoupper(trim((string) ($filters['setor_codigo_area'] ?? '')));
        if ($setorCodigoArea !== '') {
            $where[] = 'EXISTS (
                SELECT 1 FROM `usuario_setor` us_f
                INNER JOIN `setores` s_f ON s_f.`id_setor` = us_f.`setor`
                WHERE us_f.`usuario` = l.`usuario_id`
                  AND s_f.`ativo` = 1
                  AND UPPER(TRIM(COALESCE(s_f.`codigo_area`, \'\'))) = :setor_codigo_area_lanc
            )';
            $params[':setor_codigo_area_lanc'] = $setorCodigoArea;
        }

        return [$where, $params];
    }

    /**
     * @return array{0:list<string>,1:array<string,mixed>}
     */
    private function buildRankingFilter(array $filters): array
    {
        return $this->buildFilter($filters);
    }
}

