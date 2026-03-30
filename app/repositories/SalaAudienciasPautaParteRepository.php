<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class SalaAudienciasPautaParteRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'sala_audiencias_pauta_parte')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function insert(array $row): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id_parte', 'SPP', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id_parte`,`pauta`,`parte_id_global`,`processo_parte_id`,`nome_parte`,`tipo_parte`,`tipo_defensor`,`defensor_publico_id`,`defensor_publico_outro`,`defensor_privado_id`,`defensor_privado_outro`,`maioridade`,`expedicao`,`cumprimento`,`id_documento_expedido`,`id_documento_cumprido`,`criado_por`,`atualizado_por`,`ativo`) VALUES (:id,:pauta,:parte_global,:processo_parte,:nome,:tipo,:tipo_defensor,:def_publico,:def_publico_outro,:def_privado,:def_outro,:maioridade,:expedicao,:cumprimento,:idexp,:idcum,:criado,:atualizado,1)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':pauta', $row['pauta']);
        $st->bindValue(':parte_global', $row['parte_id_global'] ?? null);
        $st->bindValue(':processo_parte', $row['processo_parte_id'] ?? null);
        $st->bindValue(':nome', $row['nome_parte']);
        $st->bindValue(':tipo', $row['tipo_parte']);
        $st->bindValue(':tipo_defensor', $row['tipo_defensor'] ?? null);
        $st->bindValue(':def_publico', $row['defensor_publico_id'] ?? null);
        $st->bindValue(':def_publico_outro', $row['defensor_publico_outro'] ?? null);
        $st->bindValue(':def_privado', $row['defensor_privado_id'] ?? null);
        $st->bindValue(':def_outro', $row['defensor_privado_outro'] ?? null);
        $st->bindValue(':maioridade', $row['maioridade']);
        $st->bindValue(':expedicao', $row['expedicao']);
        $st->bindValue(':cumprimento', $row['cumprimento']);
        $st->bindValue(':idexp', $row['id_documento_expedido'] ?? null);
        $st->bindValue(':idcum', $row['id_documento_cumprido'] ?? null);
        $st->bindValue(':criado', $row['criado_por'] ?? null);
        $st->bindValue(':atualizado', $row['atualizado_por'] ?? null);
        $st->execute();
        return $id;
    }

    public function update(string $idParte, array $row): bool
    {
        $sql = sprintf(
            'UPDATE `%s` SET `parte_id_global`=:parte_global,`processo_parte_id`=:processo_parte,`nome_parte`=:nome,`tipo_parte`=:tipo,`tipo_defensor`=:tipo_defensor,`defensor_publico_id`=:def_publico,`defensor_publico_outro`=:def_publico_outro,`defensor_privado_id`=:def_privado,`defensor_privado_outro`=:def_outro,`maioridade`=:maioridade,`expedicao`=:expedicao,`cumprimento`=:cumprimento,`id_documento_expedido`=:idexp,`id_documento_cumprido`=:idcum,`atualizado_por`=:atualizado WHERE `id_parte`=:id AND `ativo`=1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $idParte);
        $st->bindValue(':parte_global', $row['parte_id_global'] ?? null);
        $st->bindValue(':processo_parte', $row['processo_parte_id'] ?? null);
        $st->bindValue(':nome', $row['nome_parte']);
        $st->bindValue(':tipo', $row['tipo_parte']);
        $st->bindValue(':tipo_defensor', $row['tipo_defensor'] ?? null);
        $st->bindValue(':def_publico', $row['defensor_publico_id'] ?? null);
        $st->bindValue(':def_publico_outro', $row['defensor_publico_outro'] ?? null);
        $st->bindValue(':def_privado', $row['defensor_privado_id'] ?? null);
        $st->bindValue(':def_outro', $row['defensor_privado_outro'] ?? null);
        $st->bindValue(':maioridade', $row['maioridade']);
        $st->bindValue(':expedicao', $row['expedicao']);
        $st->bindValue(':cumprimento', $row['cumprimento']);
        $st->bindValue(':idexp', $row['id_documento_expedido'] ?? null);
        $st->bindValue(':idcum', $row['id_documento_cumprido'] ?? null);
        $st->bindValue(':atualizado', $row['atualizado_por'] ?? null);
        $st->execute();
        return $st->rowCount() > 0;
    }

    public function softDelete(string $idParte, ?string $atualizadoPor): bool
    {
        $sql = sprintf('UPDATE `%s` SET `ativo`=0,`atualizado_por`=:atualizado WHERE `id_parte`=:id AND `ativo`=1 LIMIT 1', $this->table);
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $idParte);
        $st->bindValue(':atualizado', $atualizadoPor);
        $st->execute();
        return $st->rowCount() > 0;
    }

    public function findById(string $idParte): ?array
    {
        $sql = sprintf(
            'SELECT p.*, a.`id_pauta`, a.`processo`, pr.`numero_processo`, a.`tipo_audiencia`
             FROM `%s` p
             INNER JOIN `sala_audiencias_pauta` a ON a.`id_pauta` = p.`pauta`
             LEFT JOIN `processos` pr ON pr.`id` = a.`processo`
             WHERE p.`id_parte`=:id AND p.`ativo`=1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $idParte);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listByPauta(string $idPauta): array
    {
        $sql = sprintf('SELECT * FROM `%s` WHERE `pauta`=:pauta AND `ativo`=1 ORDER BY `id_parte` ASC', $this->table);
        $st = $this->connection->prepare($sql);
        $st->bindValue(':pauta', $idPauta);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Quantidade de partes ativas com maioridade "menor" em qualquer audiência do mesmo processo.
     */
    public function countPartesMenoresAtivasPorProcesso(string $processoId): int
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return 0;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` pp
             INNER JOIN `sala_audiencias_pauta` pa ON pa.`id_pauta` = pp.`pauta` AND pa.`ativo` = 1
             WHERE pa.`processo` = :processo
               AND pp.`ativo` = 1
               AND pp.`maioridade` = \'menor\'',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':processo', $processoId);
        $st->execute();

        return (int) $st->fetchColumn();
    }

    /**
     * @param list<string> $pautaIds
     * @return array<string, list<array{nome_parte: string, tipo_parte: string, expedicao: string, cumprimento: string}>> id_pauta => partes com status
     */
    public function listCumprimentosByPautaIds(array $pautaIds): array
    {
        $pautaIds = array_values(array_filter(array_map(static function ($id): string {
            return trim((string) $id);
        }, $pautaIds), static function ($id): bool {
            return $id !== '';
        }));

        if ($pautaIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($pautaIds as $idx => $id) {
            $key = ':p' . $idx;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = sprintf(
            'SELECT `pauta`, `nome_parte`, `tipo_parte`, `expedicao`, `cumprimento`
             FROM `%s`
             WHERE `ativo` = 1
               AND `pauta` IN (%s)
             ORDER BY `id_parte` ASC',
            $this->table,
            implode(',', $placeholders)
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
            $pautaId = trim((string) ($row['pauta'] ?? ''));
            if ($pautaId === '') {
                continue;
            }
            if (!isset($out[$pautaId])) {
                $out[$pautaId] = [];
            }
            $out[$pautaId][] = [
                'nome_parte' => trim((string) ($row['nome_parte'] ?? '')),
                'tipo_parte' => trim((string) ($row['tipo_parte'] ?? '')),
                'expedicao' => trim((string) ($row['expedicao'] ?? 'Não expedido')),
                'cumprimento' => trim((string) ($row['cumprimento'] ?? '')),
            ];
        }

        return $out;
    }
}

