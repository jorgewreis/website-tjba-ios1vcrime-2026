<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

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
     * Gera 2 iniciais: primeira letra do nome + primeira do sobrenome;
     * se não houver sobrenome, as duas primeiras letras do nome.
     */
    public static function iniciais(string $nome, string $sobrenome): string
    {
        $nome = trim($nome);
        $sobrenome = trim($sobrenome);
        if ($sobrenome !== '') {
            $a = mb_substr($nome, 0, 1);
            $b = mb_substr($sobrenome, 0, 1);
            return mb_strtoupper($a . $b);
        }
        if ($nome === '') {
            return '—';
        }
        $two = mb_substr($nome, 0, 2);
        return mb_strtoupper(mb_strlen($two) === 2 ? $two : $nome);
    }

    /**
     * Adiciona um alocado à carta. Evita duplicata (mesmo usuario na mesma carta).
     * Limite de 5 por carta deve ser garantido pelo chamador.
     */
    public function addAlocado(string $cartaId, string $usuarioId): string
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

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `carta_expedida_id`, `usuario_id`, `created_at`) VALUES (:id, :carta_expedida_id, :usuario_id, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':carta_expedida_id', $cartaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
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
            'SELECT a.`id`, a.`carta_expedida_id`, a.`usuario_id`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`carta_expedida_id` = :carta_id ORDER BY a.`created_at` ASC',
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
}
