<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';
require_once __DIR__ . '/../helpers/UserInitialsHelper.php';

final class SalaMidiasAlocadosRepository
{
    private PDO $connection;
    private string $table;

    public const TIPO_RULE = 'rule';
    public const TIPO_SPECIFIC = 'specific';
    public const TIPO_ALL = 'all';

    public function __construct(PDO $connection, string $table = 'sala_midias_alocados')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function addAlocado(string $midiaId, string $usuarioId, string $tipo = self::TIPO_SPECIFIC): string
    {
        $midiaId = trim($midiaId);
        $usuarioId = trim($usuarioId);
        if ($midiaId === '' || $usuarioId === '') {
            throw new InvalidArgumentException('midia_id e usuario_id são obrigatórios.');
        }

        $existing = $this->connection->prepare(
            sprintf('SELECT `id` FROM `%s` WHERE `midia_id` = :midia_id AND `usuario_id` = :usuario_id LIMIT 1', $this->table)
        );
        $existing->bindValue(':midia_id', $midiaId);
        $existing->bindValue(':usuario_id', $usuarioId);
        $existing->execute();
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return (string) $row['id'];
        }

        $id = IdGenerator::generateUniquePrefixedId($this->connection, $this->table, 'id', 'SMA', 30);
        $tipoVal = self::TIPO_SPECIFIC;
        if ($tipo === self::TIPO_RULE) {
            $tipoVal = self::TIPO_RULE;
        } elseif ($tipo === self::TIPO_ALL) {
            $tipoVal = self::TIPO_ALL;
        }

        $insert = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`,`midia_id`,`usuario_id`,`tipo`,`created_at`) VALUES (:id,:midia_id,:usuario_id,:tipo,NOW())',
                $this->table
            )
        );
        $insert->bindValue(':id', $id);
        $insert->bindValue(':midia_id', $midiaId);
        $insert->bindValue(':usuario_id', $usuarioId);
        $insert->bindValue(':tipo', $tipoVal);
        $insert->execute();

        return $id;
    }

    public function findByMidiaIds(array $midiaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static function ($v): string {
            return trim((string) $v);
        }, $midiaIds), static function (string $id): bool {
            return $id !== '';
        })));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT a.`id`, a.`midia_id`, a.`usuario_id`, a.`tipo`, u.`nome`, u.`sobrenome`
             FROM `%s` a
             LEFT JOIN `usuarios` u ON u.`id_usuario` = a.`usuario_id`
             WHERE a.`midia_id` IN (%s)
             ORDER BY a.`created_at` ASC',
            $this->table,
            $placeholders
        );
        $statement = $this->connection->prepare($sql);
        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id);
        }
        $statement->execute();

        $out = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $midiaId = trim((string) ($row['midia_id'] ?? ''));
            if ($midiaId === '') {
                continue;
            }
            $nome = trim((string) ($row['nome'] ?? ''));
            $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
            $row['iniciais'] = UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
            $row['nome_completo'] = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            if (!isset($out[$midiaId])) {
                $out[$midiaId] = [];
            }
            $out[$midiaId][] = $row;
        }

        return $out;
    }

    public function deleteById(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id', $this->table));
        $statement->bindValue(':id', $id);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    public function deleteByMidiaId(string $midiaId): void
    {
        $midiaId = trim($midiaId);
        if ($midiaId === '') {
            return;
        }
        $statement = $this->connection->prepare(sprintf('DELETE FROM `%s` WHERE `midia_id` = :midia_id', $this->table));
        $statement->bindValue(':midia_id', $midiaId);
        $statement->execute();
    }
}
