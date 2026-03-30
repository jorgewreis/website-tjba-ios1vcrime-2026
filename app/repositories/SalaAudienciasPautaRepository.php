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
            'SELECT a.*, p.`numero_processo`, COALESCE(p.`sigiloso`, 0) AS processo_sigiloso, COALESCE(cl.`nome`, \'\') AS classe_nome
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
        if ($st->rowCount() > 0) {
            return true;
        }
        // MySQL pode reportar 0 linhas afetadas quando os valores enviados são idênticos aos já gravados;
        // nesse caso o UPDATE ainda é válido se o registro existir e estiver ativo.
        $check = $this->connection->prepare(sprintf(
            'SELECT 1 FROM `%s` WHERE `id_pauta` = :id AND `ativo` = 1 LIMIT 1',
            $this->table
        ));
        $check->bindValue(':id', $id);
        $check->execute();

        return $check->fetchColumn() !== false;
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

    /**
     * Retorna o id da audiência em andamento na data (ou null).
     */
    public function findIdEmAndamentoNaData(string $dataYmd): ?string
    {
        $dataYmd = trim($dataYmd);
        if ($dataYmd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataYmd)) {
            return null;
        }
        $sql = sprintf(
            'SELECT `id_pauta` FROM `%s` WHERE `ativo` = 1 AND `status` = \'em_andamento\' AND `data_audiencia` = :d LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':d', $dataYmd);
        $st->execute();
        $id = $st->fetchColumn();

        return $id !== false ? trim((string) $id) : null;
    }

    /**
     * Ações do painel: atualiza status e timestamps efetivos num único UPDATE.
     *
     * @param 'iniciar'|'reiniciar'|'encerrar' $acao
     */
    public function applyPainelAcao(string $id, string $acao, ?string $atualizadoPor): bool
    {
        $acao = strtolower(trim($acao));
        $now = date('Y-m-d H:i:s');
        if ($acao === 'iniciar') {
            $sql = sprintf(
                'UPDATE `%s` SET `status`=\'em_andamento\',`atualizado_por`=:u,`data_hora_inicio_efetivo`=:now,`data_hora_fim_efetivo`=NULL WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1',
                $this->table
            );
            $st = $this->connection->prepare($sql);
            $st->bindValue(':id', $id);
            $st->bindValue(':u', $atualizadoPor);
            $st->bindValue(':now', $now);
            $st->execute();

            return true;
        }
        if ($acao === 'reiniciar') {
            $sql = sprintf(
                'UPDATE `%s` SET `status`=\'designada\',`atualizado_por`=:u,`data_hora_inicio_efetivo`=NULL,`data_hora_fim_efetivo`=NULL WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1',
                $this->table
            );
            $st = $this->connection->prepare($sql);
            $st->bindValue(':id', $id);
            $st->bindValue(':u', $atualizadoPor);
            $st->execute();

            return true;
        }
        if ($acao === 'encerrar') {
            $sql = sprintf(
                'UPDATE `%s` SET `status`=\'finalizada\',`atualizado_por`=:u,`data_hora_fim_efetivo`=:now WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1',
                $this->table
            );
            $st = $this->connection->prepare($sql);
            $st->bindValue(':id', $id);
            $st->bindValue(':u', $atualizadoPor);
            $st->bindValue(':now', $now);
            $st->execute();

            return true;
        }

        return false;
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

    /**
     * Verifica se o horário está ocupado por outra audiência **designada** (único status que reserva o slot).
     * Cancelada, redesignada e realizada liberam o horário para nova designação.
     *
     * @param string|null $exceptIdPauta Ao editar, exclui o próprio registro da verificação.
     */
    public function existsActiveSlotEm(string $dataAudiencia, string $horaAudiencia, ?string $exceptIdPauta = null): bool
    {
        $dataAudiencia = trim($dataAudiencia);
        $horaAudiencia = trim($horaAudiencia);
        if ($dataAudiencia === '' || $horaAudiencia === '') {
            return false;
        }
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `ativo` = 1 AND `status` IN (:designada, :legacy_agendada)
             AND `data_audiencia` = :data AND TIME(`hora_audiencia`) = TIME(:hora)',
            $this->table
        );
        $exceptIdPauta = $exceptIdPauta !== null ? trim($exceptIdPauta) : '';
        if ($exceptIdPauta !== '') {
            $sql .= ' AND `id_pauta` <> :exc';
        }
        $sql .= ' LIMIT 1';
        $st = $this->connection->prepare($sql);
        $st->bindValue(':designada', 'designada');
        $st->bindValue(':legacy_agendada', 'agendada');
        $st->bindValue(':data', $dataAudiencia);
        $st->bindValue(':hora', $horaAudiencia);
        if ($exceptIdPauta !== '') {
            $st->bindValue(':exc', $exceptIdPauta);
        }
        $st->execute();

        return $st->fetchColumn() !== false;
    }

    /**
     * Horários (HH:MM) já reservados por audiências **designada** ou **agendada** na data.
     *
     * @return list<string>
     */
    public function listHorariosOcupadosNaData(string $dataAudiencia, ?string $exceptIdPauta = null): array
    {
        $dataAudiencia = trim($dataAudiencia);
        if ($dataAudiencia === '') {
            return [];
        }
        $sql = sprintf(
            'SELECT `hora_audiencia` FROM `%s` WHERE `ativo` = 1 AND `status` IN (:designada, :legacy_agendada)
             AND `data_audiencia` = :data',
            $this->table
        );
        $exceptIdPauta = $exceptIdPauta !== null ? trim($exceptIdPauta) : '';
        if ($exceptIdPauta !== '') {
            $sql .= ' AND `id_pauta` <> :exc';
        }
        $st = $this->connection->prepare($sql);
        $st->bindValue(':designada', 'designada');
        $st->bindValue(':legacy_agendada', 'agendada');
        $st->bindValue(':data', $dataAudiencia);
        if ($exceptIdPauta !== '') {
            $st->bindValue(':exc', $exceptIdPauta);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $seen = [];
        foreach ($rows as $row) {
            $h = trim((string) ($row['hora_audiencia'] ?? ''));
            if ($h === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) {
                $hm = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
                $seen[$hm] = true;
            }
        }

        return array_keys($seen);
    }

    public function fetchList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = sprintf(
            'SELECT a.`id_pauta`, a.`tipo_audiencia`, a.`data_audiencia`, a.`hora_audiencia`, a.`data_hora_designacao`, a.`hora_inicio`, a.`hora_fim`, a.`duracao_estimada_minutos`, a.`localizacao`, a.`status`, p.`numero_processo`, COALESCE(p.`sigiloso`, 0) AS `processo_sigiloso`, COALESCE(NULLIF(TRIM(cl.`nome`), \'\'), \'Classe não informada\') AS `classe_nome`
             FROM `%s` a
             LEFT JOIN `processos` p ON p.`id` = a.`processo`
             LEFT JOIN `classes` cl ON cl.`id` = p.`classe_id`
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
        $out = ['designada' => 0, 'realizada' => 0, 'redesignada' => 0, 'cancelada' => 0, 'em_andamento' => 0, 'finalizada' => 0];
        while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
            $key = (string) $row['st'];
            if ($key === 'agendada') {
                $key = 'designada';
            } elseif ($key === 'adiada') {
                $key = 'redesignada';
            }
            if (!array_key_exists($key, $out)) {
                $out[$key] = 0;
            }
            $out[$key] += (int) $row['total'];
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
            if ($status === 'designada') {
                $conds[] = 'a.`status` IN (:st1, :st2)';
                $params[':st1'] = 'designada';
                $params[':st2'] = 'agendada';
            } elseif ($status === 'redesignada') {
                $conds[] = 'a.`status` IN (:st1, :st2)';
                $params[':st1'] = 'redesignada';
                $params[':st2'] = 'adiada';
            } else {
                $conds[] = 'a.`status` = :status';
                $params[':status'] = $status;
            }
        } elseif ($includeStatus && $status === '' && empty($filters['incluir_todos_status'])) {
            // "Mostrar todos": não listar finalizadas; para vê-las, usar o filtro Status = Finalizada.
            $conds[] = 'a.`status` <> \'finalizada\'';
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

    public function updateTimingEstimado(string $id, ?string $dataHoraDesignacao, ?string $horaInicio, ?string $horaFim, int $duracaoMin): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `data_hora_designacao`=:dtdes,`hora_inicio`=:hi,`hora_fim`=:hf,`duracao_estimada_minutos`=:dur WHERE `id_pauta`=:id AND `ativo`=1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        if ($dataHoraDesignacao === null || $dataHoraDesignacao === '') {
            $st->bindValue(':dtdes', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':dtdes', $dataHoraDesignacao);
        }
        if ($horaInicio === null || $horaInicio === '') {
            $st->bindValue(':hi', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':hi', $horaInicio);
        }
        if ($horaFim === null || $horaFim === '') {
            $st->bindValue(':hf', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':hf', $horaFim);
        }
        $st->bindValue(':dur', $duracaoMin, PDO::PARAM_INT);
        $st->execute();

        return true;
    }
}

