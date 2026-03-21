<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class OficiosCartorioSubtarefasRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_ENVIAR = 'enviar_oficio';
    public const TIPO_JUNTAR = 'juntar_termo_envio';

    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_EM_ANDAMENTO = 'em_andamento';
    private const STATUS_CONCLUIDA = 'concluida';

    public function __construct(PDO $connection, string $table = 'oficios_cartorio_subtarefas')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function create(string $oficioId, string $tipo, int $ordem, string $status): string
    {
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'OFS', 30);
        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `oficio_cartorio_id`, `tipo`, `ordem`, `status`, `created_at`, `updated_at`) VALUES (:id, :oid, :tipo, :ordem, :status, NOW(), NOW())',
            $this->table
        );
        $st = $this->connection->prepare($sql);
        $st->bindValue(':id', $id);
        $st->bindValue(':oid', $oficioId);
        $st->bindValue(':tipo', $tipo);
        $st->bindValue(':ordem', $ordem, PDO::PARAM_INT);
        $st->bindValue(':status', $status);
        $st->execute();
        return $id;
    }

    public function findByOficioId(string $oficioId): array
    {
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `oficio_cartorio_id` = :oid ORDER BY `ordem` ASC', $this->table)
        );
        $st->bindValue(':oid', $oficioId);
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

    public function findByOficioIdEOrdem(string $oficioId, int $ordem): ?array
    {
        $st = $this->connection->prepare(
            sprintf('SELECT * FROM `%s` WHERE `oficio_cartorio_id` = :oid AND `ordem` = :ordem LIMIT 1', $this->table)
        );
        $st->bindValue(':oid', $oficioId);
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

    public function deleteByOficioId(string $oficioId): void
    {
        $st = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `oficio_cartorio_id` = :oid', $this->table));
        $st->bindValue(':oid', $oficioId);
        $st->execute();
    }

    public function findPendenteOuEmAndamentoByOficioId(string $oficioId): array
    {
        $all = $this->findByOficioId($oficioId);
        foreach ($all as $row) {
            $s = (string) ($row['status'] ?? '');
            if ($s === self::STATUS_EM_ANDAMENTO || $s === self::STATUS_PENDENTE) {
                return [$row];
            }
        }
        return [];
    }
}
