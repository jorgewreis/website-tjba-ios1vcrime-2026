<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/IdGenerator.php';

final class AlocacoesRegrasRepository
{
    private PDO $connection;
    private string $table;

    public function __construct(PDO $connection, string $table = 'alocacoes_regras')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function findByRegra(string $regra): ?array
    {
        $regra = trim($regra);
        if ($regra === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `regra`, `descricao`, `usuario_id`, `usuario_atualiza_id`, `created_at`, `updated_at` FROM `%s` WHERE `regra` = :regra LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':regra', $regra);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Retorna todas as regras em que o usuário está definido como alocado (usuario_id = ?).
     *
     * @return array<int, array>
     */
    public function findByUsuarioId(string $usuarioId): array
    {
        $usuarioId = trim($usuarioId);
        if ($usuarioId === '') {
            return [];
        }
        $statement = $this->connection->prepare(
            sprintf(
                'SELECT `id`, `regra`, `descricao`, `usuario_id`, `usuario_atualiza_id`, `created_at`, `updated_at` FROM `%s` WHERE `usuario_id` = :usuario_id ORDER BY `regra`',
                $this->table
            )
        );
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    /**
     * Cria ou atualiza a regra. Retorna a linha atualizada/inserida.
     * id gerado com IdGenerator prefixo ALR, 30 caracteres.
     */
    public function save(string $regra, string $descricao, string $usuarioId, ?string $usuarioAtualizaId = null): array
    {
        $regra = trim($regra);
        $descricao = trim($descricao);
        $usuarioId = trim($usuarioId);
        if ($regra === '' || $usuarioId === '') {
            throw new InvalidArgumentException('Regra e usuário são obrigatórios.');
        }

        $existing = $this->findByRegra($regra);

        if ($existing !== null) {
            $statement = $this->connection->prepare(
                sprintf(
                    'UPDATE `%s` SET `descricao` = :descricao, `usuario_id` = :usuario_id, `usuario_atualiza_id` = :usuario_atualiza_id, `updated_at` = NOW() WHERE `regra` = :regra LIMIT 1',
                    $this->table
                )
            );
            $statement->bindValue(':descricao', $descricao !== '' ? $descricao : $existing['descricao']);
            $statement->bindValue(':usuario_id', $usuarioId);
            $statement->bindValue(':usuario_atualiza_id', $usuarioAtualizaId);
            $statement->bindValue(':regra', $regra);
            $statement->execute();

            $row = $this->findByRegra($regra);
            return $row ?? $existing;
        }

        $id = IdGenerator::generateUniquePrefixedId(
            $this->connection,
            $this->table,
            'id',
            'ALR',
            30
        );

        $statement = $this->connection->prepare(
            sprintf(
                'INSERT INTO `%s` (`id`, `regra`, `descricao`, `usuario_id`, `usuario_atualiza_id`, `created_at`, `updated_at`) VALUES (:id, :regra, :descricao, :usuario_id, :usuario_atualiza_id, NOW(), NOW())',
                $this->table
            )
        );
        $statement->bindValue(':id', $id);
        $statement->bindValue(':regra', $regra);
        $statement->bindValue(':descricao', $descricao !== '' ? $descricao : $regra);
        $statement->bindValue(':usuario_id', $usuarioId);
        $statement->bindValue(':usuario_atualiza_id', $usuarioAtualizaId);
        $statement->execute();

        $row = $this->findByRegra($regra);
        return $row ?? ['id' => $id, 'regra' => $regra, 'descricao' => $descricao, 'usuario_id' => $usuarioId, 'usuario_atualiza_id' => $usuarioAtualizaId];
    }

    /**
     * Remove a alocação de usuário da regra (mantém a regra existente).
     */
    public function clearAllocation(string $regra, ?string $usuarioAtualizaId = null): array
    {
        $regra = trim($regra);
        if ($regra === '') {
            throw new InvalidArgumentException('Regra é obrigatória.');
        }

        $existing = $this->findByRegra($regra);
        if ($existing === null) {
            throw new RuntimeException('Regra não encontrada para remoção de alocação.');
        }

        $statement = $this->connection->prepare(
            sprintf(
                'UPDATE `%s` SET `usuario_id` = NULL, `usuario_atualiza_id` = :usuario_atualiza_id, `updated_at` = NOW() WHERE `regra` = :regra LIMIT 1',
                $this->table
            )
        );
        $statement->bindValue(':usuario_atualiza_id', $usuarioAtualizaId);
        $statement->bindValue(':regra', $regra);
        $statement->execute();

        $row = $this->findByRegra($regra);
        return $row ?? $existing;
    }
}
