<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';

final class MedidasProtetivasAlocadosRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    public const TIPO_RULE = 'rule';
    public const TIPO_SPECIFIC = 'specific';
    public const TIPO_ALL = 'all';

    public function __construct(PDO $connection, string $table = 'medidas_protetivas_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public function addAlocado(string $medidaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $medidaId = trim($medidaId);
        $usuarioId = trim($usuarioId);
        if ($medidaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('medida_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf('SELECT `id` FROM `%s` WHERE `medida_id` = :medida_id AND `usuario_id` = :usuario_id LIMIT 1', $this->table)
        );
        $existing->bindValue(':medida_id', $medidaId);
        $existing->bindValue(':usuario_id', $usuarioId);
        $existing->execute();
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return (string) $row['id'];
        }

        $tipoVal = in_array($tipo, [self::TIPO_RULE, self::TIPO_SPECIFIC, self::TIPO_ALL], true) ? $tipo : self::TIPO_SPECIFIC;
        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'MPA', 30);

        $statement = $this->connection->prepare(
            sprintf('INSERT INTO `%s` (`id`, `medida_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :medida_id, :usuario_id, :tipo, NOW())', $this->table)
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':medida_id', $medidaId);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->bindValue(':tipo', $tipoVal);
        $statement->execute();

        return $id;
    }

    public function findByMedidaId(string $medidaId): array
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            return [];
        }

        $sql = sprintf(
            'SELECT a.`id`, a.`medida_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome`
             FROM `%s` a
             LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s
             WHERE a.`medida_id` = :medida_id
             ORDER BY a.`created_at` ASC',
            $this->table,
            self::COLLATION,
            self::COLLATION
        );
        $statement = $this->connection->prepare($sql);
        $statement->bindValue(':medida_id', $medidaId);
        $statement->execute();

        $rows = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $nome = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $row['iniciais'] = self::iniciais($nome, $sobrenome);
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

    public function deleteByMedidaId(string $medidaId): void
    {
        $medidaId = trim($medidaId);
        if ($medidaId === '') {
            return;
        }

        $statement = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `medida_id` = :medida_id', $this->table)
        );
        $statement->bindValue(':medida_id', $medidaId);
        $statement->execute();
    }
}
