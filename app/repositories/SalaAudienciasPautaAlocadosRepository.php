<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';

final class SalaAudienciasPautaAlocadosRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    public function __construct(PDO $connection, string $table = 'sala_audiencias_pauta_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public const MAX_ALOCADOS = 5;
    public const TIPO_RULE = 'rule';
    public const TIPO_SPECIFIC = 'specific';
    public const TIPO_ALL = 'all';

    public function countByPautaId(string $pautaId): int
    {
        $pautaId = trim($pautaId);
        if ($pautaId === '') {
            return 0;
        }
        $stmt = $this->connection->prepare(
            sprintf('SELECT COUNT(*) AS c FROM `%s` WHERE `pauta_id` = :p', $this->table)
        );
        $stmt->bindValue(':p', $pautaId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    public function addAlocado(string $pautaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $pautaId = trim($pautaId);
        $usuarioId = trim($usuarioId);
        if ($pautaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('pauta_id e usuario_id são obrigatórios.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }
        try {
            $parent = $this->connection->prepare('SELECT 1 FROM `sala_audiencias_pauta` WHERE `id_pauta` = :id AND `ativo` = 1 FOR UPDATE');
            $parent->execute([':id' => $pautaId]);
            if ($parent->fetchColumn() === false) {
                throw new InvalidArgumentException('Audiência não encontrada.');
            }
            $read = $this->connection->prepare(sprintf(
                'SELECT `id`, `usuario_id` FROM `%s` WHERE `pauta_id` = :p FOR UPDATE',
                $this->table
            ));
            $read->execute([':p' => $pautaId]);
            $rows = $read->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ((string) $row['usuario_id'] === $usuarioId) {
                    if ($ownsTransaction) {
                        $this->connection->commit();
                    }
                    return (string) $row['id'];
                }
            }
            if (count($rows) >= self::MAX_ALOCADOS) {
                throw new InvalidArgumentException('Limite de 5 servidores alocados por audiência.');
            }
            $id = IdGenerator::generateUniquePrefixedId(
                $this->connection,
                $this->table,
                'id',
                'SPA',
                30
            );

            $tipoVal = $tipo === self::TIPO_RULE ? self::TIPO_RULE : ($tipo === self::TIPO_ALL ? self::TIPO_ALL : self::TIPO_SPECIFIC);

            $sql = sprintf(
                'INSERT INTO `%s` (`id`, `pauta_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :pauta_id, :usuario_id, :tipo, NOW())',
                $this->table
            );
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':pauta_id', $pautaId);
            $stmt->bindValue(':usuario_id', $usuarioId);
            $stmt->bindValue(':tipo', $tipoVal);
            $stmt->execute();
            if ($ownsTransaction) {
                $this->connection->commit();
            }
            return $id;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function findByPautaIds(array $pautaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($v): string {
            return trim((string) $v);
        }, $pautaIds), static function (string $id): bool {
            return $id !== '';
        })));
        if ($ids === []) {
            return [];
        }

        $coll = self::COLLATION;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT a.`id`, a.`pauta_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`pauta_id` IN (%s) ORDER BY a.`pauta_id` ASC, a.`created_at` ASC',
            $this->table,
            $coll,
            $coll,
            $placeholders
        );
        $stmt = $this->connection->prepare($sql);
        foreach ($ids as $i => $pid) {
            $stmt->bindValue($i + 1, $pid);
        }
        $stmt->execute();

        $byPauta = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $pid = (string) ($row['pauta_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $row['iniciais'] = self::iniciais($nome, $sobrenome);
            $row['nome_completo'] = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            if (!isset($byPauta[$pid])) {
                $byPauta[$pid] = [];
            }
            $byPauta[$pid][] = $row;
        }

        return $byPauta;
    }

    public function deleteByIdForActor(string $id, string $actorId, bool $isManager): bool
    {
        $id = trim($id);
        $actorId = trim($actorId);
        if ($id === '' || $actorId === '') {
            return false;
        }
        // A autorização e a exclusão avaliam a mesma versão da linha.
        $predicate = $isManager
            ? "`tipo` IN ('rule', 'all', 'specific')"
            : "`tipo` = 'specific' AND `usuario_id` = :actor";
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id AND (%s)', $this->table, $predicate)
        );
        $stmt->bindValue(':id', $id);
        if (!$isManager) {
            $stmt->bindValue(':actor', $actorId);
        }
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function deleteByPautaId(string $pautaId): void
    {
        $pautaId = trim($pautaId);
        if ($pautaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `pauta_id` = :pauta_id', $this->table)
        );
        $stmt->bindValue(':pauta_id', $pautaId);
        $stmt->execute();
    }
}
