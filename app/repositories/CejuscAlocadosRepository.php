<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class CejuscAlocadosRepository
{
    private PDO $connection;
    private string $table;

    /** Collation única para evitar "Illegal mix of collations" em JOIN com usuarios. */
    private const COLLATION = 'utf8mb4_unicode_ci';

    public function __construct(PDO $connection, string $table = 'cejusc_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

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

    public const TIPO_RULE = 'rule';
    public const TIPO_SPECIFIC = 'specific';
    public const TIPO_ALL = 'all';

    public function addAlocado(string $cejuscTarefaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $cejuscTarefaId = trim($cejuscTarefaId);
        $usuarioId = trim($usuarioId);
        if ($cejuscTarefaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('cejusc_tarefa_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf(
                'SELECT `id` FROM `%s` WHERE `cejusc_tarefa_id` = :tarefa_id AND `usuario_id` = :usuario_id LIMIT 1',
                $this->table
            )
        );
        $existing->bindValue(':tarefa_id', $cejuscTarefaId);
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
            'CJA',
            30
        );

        $tipoVal = $tipo === self::TIPO_RULE ? self::TIPO_RULE : ($tipo === self::TIPO_ALL ? self::TIPO_ALL : self::TIPO_SPECIFIC);

        $sql = sprintf(
            'INSERT INTO `%s` (`id`, `cejusc_tarefa_id`, `usuario_id`, `tipo`, `created_at`) VALUES (:id, :cejusc_tarefa_id, :usuario_id, :tipo, NOW())',
            $this->table
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':cejusc_tarefa_id', $cejuscTarefaId);
        $stmt->bindValue(':usuario_id', $usuarioId);
        $stmt->bindValue(':tipo', $tipoVal);
        $stmt->execute();

        return $id;
    }

    public function findByCejuscId(string $cejuscTarefaId): array
    {
        $cejuscTarefaId = trim($cejuscTarefaId);
        if ($cejuscTarefaId === '') {
            return [];
        }

        $coll = self::COLLATION;
        $sql = sprintf(
            'SELECT a.`id`, a.`cejusc_tarefa_id`, a.`usuario_id`, a.`tipo`, a.`created_at`, u.`nome`, u.`sobrenome` FROM `%s` a LEFT JOIN `usuarios` u ON u.`id_usuario` COLLATE %s = a.`usuario_id` COLLATE %s WHERE a.`cejusc_tarefa_id` = :tarefa_id ORDER BY a.`created_at` ASC',
            $this->table,
            $coll,
            $coll
        );
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':tarefa_id', $cejuscTarefaId);
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

    public function deleteByCejuscId(string $cejuscTarefaId): void
    {
        $cejuscTarefaId = trim($cejuscTarefaId);
        if ($cejuscTarefaId === '') {
            return;
        }
        $stmt = $this->connection->prepare(
            sprintf('DELETE FROM `%s` WHERE `cejusc_tarefa_id` = :tarefa_id', $this->table)
        );
        $stmt->bindValue(':tarefa_id', $cejuscTarefaId);
        $stmt->execute();
    }
}
