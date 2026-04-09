<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';
require_once __DIR__ . '/../helpers/CartorioAlocacaoTipo.php';

final class GuiasAlocadosRepository
{
    private PDO $connection;
    private string $table;

    private const COLLATION = 'utf8mb4_unicode_ci';

    public const TIPO_RULE = CartorioAlocacaoTipo::RULE;
    public const TIPO_SPECIFIC = CartorioAlocacaoTipo::SPECIFIC;
    public const TIPO_ALL = CartorioAlocacaoTipo::ALL;

    private const MAX_ALOCADOS = 5;

    public function __construct(PDO $connection, string $table = 'guias_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public function addAlocado(string $guiaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $guiaId = trim($guiaId);
        $usuarioId = trim($usuarioId);
        if ($guiaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('guia_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `guia_id` = :guia_id AND `usuario_id` = :usuario_id LIMIT 1',
                $this->table
            )
        );
        $existing->bindValue(':guia_id', $guiaId);
        $existing->bindValue(':usuario_id', $usuarioId);
        $existing->execute();
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return (string) $row['id'];
        }

        $count = $this->connection->prepare(sprintf('SELECT COUNT(*) FROM `%s` WHERE `guia_id` = :guia_id', $this->table));
        $count->bindValue(':guia_id', $guiaId);
        $count->execute();
        if ((int) $count->fetchColumn() >= self::MAX_ALOCADOS) {
            throw new RuntimeException('Limite de alocados por guia atingido (' . self::MAX_ALOCADOS . ').');
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'GUA',
            30
        );

        $tipoVal = CartorioAlocacaoTipo::normalizar($tipo, [self::TIPO_RULE, self::TIPO_ALL, self::TIPO_SPECIFIC], self::TIPO_SPECIFIC);

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `guia_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :guia_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':guia_id', $guiaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    public function findByGuiaId(string $guiaId): array
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql = sprintf(
            'SELECT a.`id`, a.`guia_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`guia_id` = :guia_id ORDER BY a.`created_at` ASC',
            $this->table,
            $coll,
            $coll
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':guia_id', $guiaId);
        $stmt->execute();

        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $nome = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $row['iniciais'] = self::iniciais($nome, $sobrenome);
            $row['nome_completo'] = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            $row['usuario_id'] = $row['usuario_id'] ?? '';
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

    public function deleteByGuiaId(string $guiaId): void
    {
        $guiaId = trim($guiaId);
        if ($guiaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `guia_id` = :guia_id', $this->table)
        );
        $stmt->bindValue(':guia_id', $guiaId);
        $stmt->execute();
    }
}
