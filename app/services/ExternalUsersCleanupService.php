<?php
declare(strict_types=1);

/** A exclusão automática permanece suspensa até definição da política de retenção. */
final class ExternalUsersCleanupService
{
    public function __construct(PDO $connection)
    {
    }

    public function purgeInactiveStudents(int $daysWithoutAccess = 30): array
    {
        throw new LogicException('Limpeza automática de usuários desativada; nenhuma conta foi excluída.');
    }
}
