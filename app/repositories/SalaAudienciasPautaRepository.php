<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class SalaAudienciasPautaRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'sala_audiencias_pauta')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function nextSequencialForYear(int $ano): int
    {
        $st = $this->connection->prepare(sprintf('SELECT COALESCE(MAX(`numero_sequencial`), 0) + 1 FROM `%s` WHERE `ano` = :ano', $this->table));
        $st->bindValue(':ano', $ano, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function getMaxSequencialForYear(int $ano): int
    {
        $st = $this->connection->prepare(sprintf('SELECT COALESCE(MAX(`numero_sequencial`), 0) FROM `%s` WHERE `ano` = :ano', $this->table));
        $st->bindValue(':ano', $ano, PDO::PARAM_INT);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function insert(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id_pauta', 'SAP', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id_pauta`,`numero_sequencial`,`ano`,`processo`,`tipo_audiencia`,`data_audiencia`,`hora_audiencia`,`localizacao`,`status`,`observacoes`,`criado_por`,`atualizado_por`,`ativo`) VALUES (:id,:seq,:ano,:processo,:tipo,:data,:hora,:localizacao,:status,:obs,:criado_por,:atualizado_por,1)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':seq', (int) $row['numero_sequencial'], PDO::PARAM_INT);
        $st->bindValue(':ano', (int) $row['ano'], PDO::PARAM_INT);
        $st->bindValue(':processo', $row['processo']);
        $st->bindValue(':tipo', $row['tipo_audiencia']);
        $st->bindValue(':data', $row['data_audiencia']);
        $st->bindValue(':hora', $row['hora_audiencia']);
        $st->bindValue(':localizacao', $row['localizacao']);
        $st->bindValue(':status', $row['status']);
        $st->bindValue(':obs', $row['observacoes'] ?? null);
        $st->bindValue(':criado_por', $row['criado_por'] ?? null);
        $st->bindValue(':atualizado_por', $row['atualizado_por'] ?? null);
        $st->execute();
        return $id;
    }

    public function findById(string $id): ?array
    {
        $sql = sprintf(
            'SELECT a.*, p.`numero_processo`, COALESCE(cl.`nome`, \'\') AS classe_nome
             FROM `%s` a
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
             WHERE a.`id_pauta` = :id AND a.`ativo` = 1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function update(string $id, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `tipo_audiencia`=:tipo,`data_audiencia`=:data,`hora_audiencia`=:hora,`localizacao`=:localizacao,`status`=:status,`observacoes`=:obs,`atualizado_por`=:atualizado_por WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':tipo', $row['tipo_audiencia']);
        $st->bindValue(':data', $row['data_audiencia']);
        $st->bindValue(':hora', $row['hora_audiencia']);
        $st->bindValue(':localizacao', $row['localizacao']);
        $st->bindValue(':status', $row['status']);
        $st->bindValue(':obs', $row['observacoes'] ?? null);
        $st->bindValue(':atualizado_por', $row['atualizado_por'] ?? null);
        $st->execute();
        return $st->rowCount() > 0;
    }

    public function setStatus(string $id, string $status, ?string $atualizadoPor): bool
    {
        $sql = sprintf('UPDATE `%s` SET `status`=:status,`atualizado_por`=:atualizado_por WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1', $this->table);
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':status', $status);
        $st->bindValue(':atualizado_por', $atualizadoPor);
        $st->execute();
        return $st->rowCount() > 0;
    }

    public function softDelete(string $id, ?string $atualizadoPor): bool
    {
        $sql = sprintf('UPDATE `%s` SET `ativo`=0,`atualizado_por`=:atualizado_por WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1', $this->table);
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':atualizado_por', $atualizadoPor);
        $st->execute();
        return $st->rowCount() > 0;
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = sprintf(
            'SELECT a.`id_pauta`, a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`localizacao`, a.`status`, p.`numero_processo`
             FROM `%s` a
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             %s
             ORDER BY a.`data_audiencia` ASC, a.`hora_audiencia` ASC
             LIMIT :limit OFFSET :offset',
            $this->table,
            $where !== '' ? 'WHERE ' . $where : ''
        );
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = sprintf('SELECT COUNT(*) FROM `%s` a LEFT JOIN `processos` p ON p.`id` = a.`processo` %s', $this->table, $where !== '' ? 'WHERE ' . $where : '');
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        return (int) $st->fetchColumn();
    }

    public function countByStatus(array $filters): array
    {
        [$where, $params] = $this->buildWhere($filters, false);
        $sql = sprintf('SELECT a.`status` AS st, COUNT(*) AS total FROM `%s` a LEFT JOIN `processos` p ON p.`id`=a.`processo` %s GROUP BY a.`status`', $this->table, $where !== '' ? 'WHERE ' . $where : '');
        $st = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $out = ['agendada' => 0, 'realizada' => 0, 'adiada' => 0, 'cancelada' => 0];
        while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $out[(string) $row['st']] = (int) $row['total'];
        }
        return $out;
    }

    private function buildWhere(array $filters, bool $includeStatus = true): array
    {
        $conds = ['a.`ativo` = 1'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conds[] = '(p.`numero_processo` LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($includeStatus && $status !== '') {
            $conds[] = 'a.`status` = :status';
            $params[':status'] = $status;
        }
        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '') {
            $conds[] = 'a.`tipo_audiencia` = :tipo';
            $params[':tipo'] = $tipo;
        }
        $periodo = trim((string) ($filters['periodo'] ?? ''));
        if ($periodo === 'hoje') {
            $conds[] = 'a.`data_audiencia` = CURDATE()';
        } elseif ($periodo === 'semana') {
            $conds[] = 'a.`data_audiencia` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        } elseif ($periodo === 'mes') {
            $conds[] = 'YEAR(a.`data_audiencia`) = YEAR(CURDATE()) AND MONTH(a.`data_audiencia`) = MONTH(CURDATE())';
        }
        return [implode(' AND ', $conds), $params];
    }
}

