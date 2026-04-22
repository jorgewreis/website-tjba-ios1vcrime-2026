<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class SalaMidiasTarefasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'sala_midias_tarefa')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'SMT', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (
                `id`,`pauta_id`,`processo_id`,`numero_processo`,`classe_processo`,`data_audiencia`,`tipo_audiencia`,
                `qtd_midias`,`origem`,`status`,`observacoes`,`criado_por`,`atualizado_por`,`ativo`
            ) VALUES (
                :id,:pauta_id,:processo_id,:numero_processo,:classe_processo,:data_audiencia,:tipo_audiencia,
                :qtd_midias,:origem,:status,:observacoes,:criado_por,:atualizado_por,1
            )',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':pauta_id', $row['pauta_id'] ?? null);
        $statement->bindValue(':processo_id', $row['processo_id'] ?? null);
        $statement->bindValue(':numero_processo', $row['numero_processo']);
        $statement->bindValue(':classe_processo', $row['classe_processo'] ?? null);
        $statement->bindValue(':data_audiencia', $row['data_audiencia']);
        $statement->bindValue(':tipo_audiencia', $row['tipo_audiencia']);
        $statement->bindValue(':qtd_midias', (int) $row['qtd_midias'], PDO::PARAM_INT);
        $statement->bindValue(':origem', $row['origem'] ?? 'manual');
        $statement->bindValue(':status', $row['status'] ?? 'pendente');
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':criado_por', $row['criado_por'] ?? null);
        $statement->bindValue(':atualizado_por', $row['atualizado_por'] ?? null);
        $statement->execute();

        return $id;
    }

    public function update(string $id, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s`
             SET `qtd_midias` = :qtd_midias,
                 `status` = :status,
                 `observacoes` = :observacoes,
                 `atualizado_por` = :atualizado_por,
                 `atualizado_em` = NOW()
             WHERE `id` = :id AND `ativo` = 1
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':qtd_midias', (int) $row['qtd_midias'], PDO::PARAM_INT);
        $statement->bindValue(':status', $row['status']);
        $statement->bindValue(':observacoes', $row['observacoes'] ?? null);
        $statement->bindValue(':atualizado_por', $row['atualizado_por'] ?? null);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function findById(string $id): ?array
    {
        $sql = sprintf(
            'SELECT t.*, p.`status` AS pauta_status
             FROM `%s` t
             LEFT JOIN `sala_audiencias_pauta` p ON p.`id_pauta` = t.`pauta_id`
             WHERE t.`id` = :id AND t.`ativo` = 1
             LIMIT 1',
            $this->table
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':id', $id);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findByPautaId(string $pautaId): ?array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `pauta_id` = :pauta_id AND `ativo` = 1 LIMIT 1', $this->table)
        );
        $statement->bindValue(':pauta_id', $pautaId);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Qualquer registro com o mesmo `pauta_id` (ativo ou inativo), para checagem de duplicidade na UK.
     */
    public function findAnyByPautaId(string $pautaId): ?array
    {
        $statement = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `pauta_id` = :pauta_id LIMIT 1', $this->table)
        );
        $statement->bindValue(':pauta_id', $pautaId);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Cadastro manual (sem `pauta_id`): mesma audiência = mesmo processo (CNJ, só dígitos) + data + tipo.
     *
     * @param string $processoDigits Apenas dígitos do número do processo
     * @param string $tipoLower Tipo de audiência em minúsculas (trim)
     */
    public function findActiveManualDuplicate(string $processoDigits, string $dataYmd, string $tipoLower): ?array
    {
        if ($processoDigits === '' || $dataYmd === '' || $tipoLower === '') {
            return null;
        }
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT * FROM `%s` WHERE `ativo` = 1 AND `pauta_id` IS NULL AND `data_audiencia` = :data',
                $this->table
            )
        );
        $statement->bindValue(':data', $dataYmd);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $digits = preg_replace('/\D+/', '', (string) ($row['numero_processo'] ?? ''));
            $tipo = mb_strtolower(trim((string) ($row['tipo_audiencia'] ?? '')), 'UTF-8');
            if ($digits === $processoDigits && $tipo === $tipoLower) {
                return $row;
            }
        }

        return null;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = sprintf(
            'SELECT t.*
             FROM `%s` t
             %s
             ORDER BY t.`data_audiencia` DESC, t.`criado_em` DESC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $where !== '' ? 'WHERE ' . $where : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = sprintf('SELECT COUNT(*) FROM `%s` t %s', $this->table, $where !== '' ? 'WHERE ' . $where : '');
        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters, false);
        $sql = sprintf(
            'SELECT t.`status` AS st, COUNT(*) AS total
             FROM `%s` t
             %s
             GROUP BY t.`status`',
            $this->table,
            $where !== '' ? 'WHERE ' . $where : ''
        );
        $statement = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $statement->bindValue($k, $v);
        }
        $statement->execute();
        $out = ['pendente' => 0, 'em_andamento' => 0, 'concluida' => 0, 'cancelada' => 0];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $key = trim((string) ($row['st'] ?? ''));
            if (!array_key_exists($key, $out)) {
                $out[$key] = 0;
            }
            $out[$key] += (int) ($row['total'] ?? 0);
        }

        return $out;
    }

    public function softDelete(string $id, ?string $usuarioId): bool
    {
        $statement = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `ativo` = 0, `atualizado_por` = :usuario_id, `atualizado_em` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function delete(string $id): bool
    {
        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    private function buildWhere(array $filters, bool $includeStatus = true): array
    {
        $conds = ['t.`ativo` = 1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conds[] = '(t.`numero_processo` LIKE :search_processo OR t.`classe_processo` LIKE :search_classe OR t.`tipo_audiencia` LIKE :search_tipo)';
            $like = '%' . $search . '%';
            $params[':search_processo'] = $like;
            $params[':search_classe'] = $like;
            $params[':search_tipo'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($includeStatus && $status !== '') {
            $conds[] = 't.`status` = :status';
            $params[':status'] = $status;
        } elseif ($includeStatus && $status === '' && !empty($filters['excluir_concluida'])) {
            $conds[] = 't.`status` <> :excl_status_concluida';
            $params[':excl_status_concluida'] = 'concluida';
        }

        $data = trim((string) ($filters['data_audiencia'] ?? ''));
        if ($data !== '') {
            $conds[] = 't.`data_audiencia` = :data_audiencia';
            $params[':data_audiencia'] = $data;
        }

        return [implode(' AND ', $conds), $params];
    }
}
