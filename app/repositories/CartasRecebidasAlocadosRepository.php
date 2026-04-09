<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';
require_once __DIR__ . '/../helpers/CartorioAlocacaoTipo.php';

final class CartasRecebidasAlocadosRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOIN com usuarios. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function __construct(PDO $connection, string $table = 'cartas_recebidas_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    public const TIPO_RULE = CartorioAlocacaoTipo::RULE;
    public const TIPO_SPECIFIC = CartorioAlocacaoTipo::SPECIFIC;
    public const TIPO_ALL = CartorioAlocacaoTipo::ALL;

    public function addAlocado(string $cartaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $cartaId = trim($cartaId);
        $usuarioId = trim($usuarioId);
        if ($cartaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('carta_recebida_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `carta_recebida_id` = :carta_id AND `usuario_id` = :usuario_id LIMIT 1',
                $this->table
            )
        );
        $existing->bindValue(':carta_id', $cartaId);
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
            'CRA',
            30
        );

        $tipoVal = CartorioAlocacaoTipo::normalizar($tipo, [self::TIPO_RULE, self::TIPO_ALL, self::TIPO_SPECIFIC], self::TIPO_SPECIFIC);

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `carta_recebida_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :carta_recebida_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':carta_recebida_id', $cartaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    public function findByCartaId(string $cartaId): array
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql = sprintf(
            'SELECT a.`id`, a.`carta_recebida_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`carta_recebida_id` = :carta_id ORDER BY a.`created_at` ASC',
            $this->table,
            $coll,
            $coll
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':carta_id', $cartaId);
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

    public function deleteByCartaId(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `carta_recebida_id` = :carta_id', $this->table)
        );
        $stmt->bindValue(':carta_id', $cartaId);
        $stmt->execute();
    }
}
