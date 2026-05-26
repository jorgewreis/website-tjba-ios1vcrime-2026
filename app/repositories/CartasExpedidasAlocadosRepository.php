<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';
require_once __DIR__ . '/../helpers/CartorioAlocacaoTipo.php';

final class CartasExpedidasAlocadosRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOIN com usuarios (ex.: common). */
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function __construct(PDO $connection, string $table = 'cartas_expedidas_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Duas iniciais (ignora partículas como de/da/do — ver UserInitialsHelper).
     */
    public static function iniciais(string $nome, string $sobrenome): string
    {
        return UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
    }

    /** Tipo: alocação por regra definida no banco (auto na criação). */
    public const TIPO_RULE = CartorioAlocacaoTipo::RULE;
    /** Tipo: alocação por alteração de subtarefas ou dados da tarefa principal. */
    public const TIPO_SPECIFIC = CartorioAlocacaoTipo::SPECIFIC;
    /** Tipo: alocação definida no cadastro como usuário principal da tarefa e subtarefas. */
    public const TIPO_ALL = CartorioAlocacaoTipo::ALL;

    /**
     * Adiciona um alocado à carta. Evita duplicata (mesmo usuario na mesma carta).
     * Limite de 5 por carta deve ser garantido pelo chamador.
     * @param string $tipo 'rule' | 'specific' | 'all'
     */
    public function addAlocado(string $cartaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $cartaId = trim($cartaId);
        $usuarioId = trim($usuarioId);
        if ($cartaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('carta_expedida_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `carta_expedida_id` = :carta_id AND `usuario_id` = :usuario_id LIMIT 1',
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
            'CEA',
            30
        );

        $tipoVal = CartorioAlocacaoTipo::normalizar($tipo, [self::TIPO_RULE, self::TIPO_ALL, self::TIPO_SPECIFIC], self::TIPO_SPECIFIC);

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `carta_expedida_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :carta_expedida_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':carta_expedida_id', $cartaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    /**
     * Retorna os alocados da carta com nome e sobrenome (JOIN usuarios) e iniciais.
     * Ordenado por created_at. Máximo 5 na aplicação ao exibir.
     */
    public function findByCartaId(string $cartaId): array
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql = sprintf(
            'SELECT a.`id`, a.`carta_expedida_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`carta_expedida_id` = :carta_id ORDER BY a.`created_at` ASC',
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

    /**
     * Remove todos os alocados da carta (usado ao excluir a tarefa principal).
     */
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

    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $stmt = $this->connection->prepare(
            sprintf('SELECT `id`, `carta_expedida_id`, `usuario_id`, `tipo`, `created_at` FROM `%s` WHERE `id` = :id LIMIT 1', $this->table)
        );
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function deleteByCartaId(string $cartaId): void
    {
        $cartaId = trim($cartaId);
        if ($cartaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `carta_expedida_id` = :carta_id', $this->table)
        );
        $stmt->bindValue(':carta_id', $cartaId);
        $stmt->execute();
    }
}
