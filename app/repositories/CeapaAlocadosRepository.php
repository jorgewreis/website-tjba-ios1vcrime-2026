<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';

final class CeapaAlocadosRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOIN com usuarios. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    public const TIPO_RULE     = 'rule';
    public const TIPO_SPECIFIC = 'specific';
    public const TIPO_ALL      = 'all';

    public function __construct(PDO $connection, string $table = 'ceapa_alocados')
    {
        $this->connection = $connection;
        $this->table      = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public function addAlocado(string $ceapaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $ceapaId   = trim($ceapaId);
        $usuarioId = trim($usuarioId);
        if ($ceapaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('ceapa_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `ceapa_id` = :ceapa_id AND `usuario_id` = :usuario_id LIMIT 1',
                $this->table
            )
        );
        $existing->bindValue(':ceapa_id', $ceapaId);
        $existing->bindValue(':usuario_id', $usuarioId);
        $existing->execute();
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return (string) $row['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'CEA',
            30
        );

        $tipoVal = $tipo === self::TIPO_RULE ? self::TIPO_RULE : ($tipo === self::TIPO_ALL ? self::TIPO_ALL : self::TIPO_SPECIFIC);

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `ceapa_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :ceapa_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':ceapa_id', $ceapaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    public function findByCeapaId(string $ceapaId): array
    {
        $ceapaId = trim($ceapaId);
        if ($ceapaId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql  = sprintf(
            'SELECT a.`id`, a.`ceapa_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome`'
            . ' FROM `%s` a'
            . ' LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s'
            . ' WHERE a.`ceapa_id` = :ceapa_id'
            . ' ORDER BY a.`created_at` ASC',
            $this->table,
            $coll,
            $coll
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':ceapa_id', $ceapaId);
        $stmt->execute();

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $nome      = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $row['iniciais']      = self::iniciais($nome, $sobrenome);
            $row['nome_completo'] = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            $rows[] = $row;
        }

        return $rows;
    }

    public function deleteById(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->table)
        );
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function deleteByCeapaId(string $ceapaId): void
    {
        $ceapaId = trim($ceapaId);
        if ($ceapaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `ceapa_id` = :ceapa_id', $this->table)
        );
        $stmt->bindValue(':ceapa_id', $ceapaId);
        $stmt->execute();
    }
}
