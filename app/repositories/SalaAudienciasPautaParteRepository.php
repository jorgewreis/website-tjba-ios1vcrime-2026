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
            'INSERT INTO `%s` (`id_parte`,`pauta`,`nome_parte`,`tipo_parte`,`maioridade`,`expedicao`,`cumprimento`,`id_documento_expedido`,`id_documento_cumprido`,`criado_por`,`atualizado_por`,`ativo`) VALUES (:id,:pauta,:nome,:tipo,:maioridade,:expedicao,:cumprimento,:idexp,:idcum,:criado,:atualizado,1)',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':pauta', $row['pauta']);
        $st->bindValue(':nome', $row['nome_parte']);
        $st->bindValue(':tipo', $row['tipo_parte']);
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
            'UPDATE `%s` SET `nome_parte`=:nome,`tipo_parte`=:tipo,`maioridade`=:maioridade,`expedicao`=:expedicao,`cumprimento`=:cumprimento,`id_documento_expedido`=:idexp,`id_documento_cumprido`=:idcum,`atualizado_por`=:atualizado WHERE `id_parte`=:id AND `ativo`=1 LIMIT 1',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $idParte);
        $st->bindValue(':nome', $row['nome_parte']);
        $st->bindValue(':tipo', $row['tipo_parte']);
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
            'SELECT p.*, a.`id_pauta`, pr.`numero_processo`, a.`tipo_audiencia`
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
}

