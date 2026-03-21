<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class GabineteMonitoramentoEletronicoSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_INICIAR = 'iniciar_monitoramento';
    public const TIPO_ENCERRAR = 'encerrar_monitoramento';

    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'gabinete_monitoramento_eletronico_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $monitoramentoId, string $tipo, int $ordem, string $status): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'GMS', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `monitoramento_id`, `tipo`, `ordem`, `status`, `created_at`, `updated_at`) VALUES (:id, :mid, :tipo, :ordem, :status, NOW(), NOW())',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':mid', $monitoramentoId);
        $st->bindValue(':tipo', $tipo);
        $st->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $st->bindValue(':status', $status);
        $st->execute();

        return $id;
    }

    public function findByMonitoramentoId(string $monitoramentoId): array
    {
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `monitoramento_id` = :mid ORDER BY `ordem` ASC', $this->table)
        );
        $st->bindValue(':mid', $monitoramentoId);
        $st->execute();

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(string $id): ?array
    {
        $st = $this->connection->prepare(sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->table));
        $st->bindValue(':id', $id);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findByMonitoramentoIdEOrdem(string $monitoramentoId, int $ordem): ?array
    {
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `monitoramento_id` = :mid AND `ordem` = :ordem LIMIT 1', $this->table)
        );
        $st->bindValue(':mid', $monitoramentoId);
        $st->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function atualizarStatus(string $id, string $status): void
    {
        $st = $this->connection->prepare(
            sprintf('UPDATE `%s` SET `status` = :st, `updated_at` = NOW() WHERE `id` = :id LIMIT 1', $this->table)
        );
        $st->bindValue(':st', $status);
        $st->bindValue(':id', $id);
        $st->execute();
    }

    public function marcarConcluida(string $id): void
    {
        $this->atualizarStatus($id, self::STATUS_CONCLUIDA);
    }

    public function deleteByMonitoramentoId(string $monitoramentoId): void
    {
        $st = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `monitoramento_id` = :mid', $this->table));
        $st->bindValue(':mid', $monitoramentoId);
        $st->execute();
    }
}
