<?php
declare(strict_types=1);

/** Serializes child-row creation with deletion of its owning Cartório record. */
final class ParentRecordLock
{
    private const CARTORIO_PARENT_TABLES = [
        'cartas_expedidas',
        'cartas_recebidas',
        'editais',
        'guias',
    ];

    public static function run(PDO $connection, string $parentTable, string $parentId, callable $operation): mixed
    {
        if (!in_array($parentTable, self::CARTORIO_PARENT_TABLES, true)) {
            throw new InvalidArgumentException('Tabela de registro principal inválida.');
        }

        $parentId = trim($parentId);
        if ($parentId === '') {
            throw new InvalidArgumentException('Identificador do registro principal é obrigatório.');
        }

        $ownsTransaction = !$connection->inTransaction();
        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $statement = $connection->prepare(sprintf(
                'SELECT `id` FROM `%s` WHERE `id` = :id FOR UPDATE',
                $parentTable
            ));
            $statement->execute([':id' => $parentId]);
            $exists = $statement->fetchColumn() !== false;
            $statement->closeCursor();

            if (!$exists) {
                throw new RuntimeException('Registro principal não encontrado; gravação relacionada recusada.');
            }

            $result = $operation();
            if ($ownsTransaction) {
                $connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
