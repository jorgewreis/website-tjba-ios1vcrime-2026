<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';

final class EditaisAlocadosRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    public const TIPO_RULE = 'rule';
    public const TIPO_SPECIFIC = 'specific';

    public function __construct(PDO $connection, string $table = 'editais_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public function addAlocado(string $editalId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $editalId = trim($editalId);
        $usuarioId = trim($usuarioId);
        if ($editalId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('edital_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `edital_id` = :edital_id AND `usuario_id` = :usuario_id LIMIT 1',
                $this->table
            )
        );
        $existing->bindValue(':edital_id', $editalId);
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
            'EDA',
            30
        );

        $tipoVal = $tipo === self::TIPO_RULE ? self::TIPO_RULE : self::TIPO_SPECIFIC;

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `edital_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :edital_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':edital_id', $editalId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    public function findByEditalId(string $editalId): array
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql = sprintf(
            'SELECT a.`id`, a.`edital_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`edital_id` = :edital_id ORDER BY a.`created_at` ASC',
            $this->table,
            $coll,
            $coll
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':edital_id', $editalId);
        $stmt->execute();

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
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

    public function deleteByEditalId(string $editalId): void
    {
        $editalId = trim($editalId);
        if ($editalId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `edital_id` = :edital_id', $this->table)
        );
        $stmt->bindValue(':edital_id', $editalId);
        $stmt->execute();
    }
}
