<?php
declare(strict_types=1);

require_once __DIR__ . '/ProcessResponsibleSectorDefault.php';

/** Autorização por operação já aprovada e lotação atual do processo. */
final class ProcessoSetorAuthorization
{
    /**
     * Confere o escopo de acesso ao registro. A permissão da operação deve ser
     * validada separadamente pelo guard do endpoint.
     */
    public static function canAccessProcess(PDO $connection, string $userId, string $processId, bool $lock = true): bool
    {
        return self::canModifyProcess($connection, $userId, $processId, $lock);
    }

    /**
     * Exige transação ativa quando $lock=true para manter o setor responsável
     * estável entre a autorização e a mutação que o chamador executará.
     */
    public static function canModifyProcess(PDO $connection, string $userId, string $processId, bool $lock = true): bool
    {
        $userId = trim($userId);
        $processId = trim($processId);
        if ($userId === '' || $processId === '') {
            return false;
        }
        if ($lock && !$connection->inTransaction()) {
            throw new LogicException('A verificação de lotação exige transação.');
        }

        $sql = 'SELECT proc.`id`, proc.`id_setor_responsavel`, setor.`id_setor` AS `setor_id`, setor.`ativo` AS `setor_ativo`
                FROM `processos` proc
                LEFT JOIN `setores` setor ON setor.`id_setor` = proc.`id_setor_responsavel`
                WHERE proc.`id` = :processo_id LIMIT 1';
        if ($lock && $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $connection->prepare($sql);
        $statement->execute([':processo_id' => $processId]);
        $process = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        if ($process === false) {
            return false;
        }

        $responsibleSector = trim((string) ($process['id_setor_responsavel'] ?? ''));
        $joinedSector = trim((string) ($process['setor_id'] ?? ''));
        $usesDefault = $responsibleSector === '';
        if ($usesDefault) {
            $default = ProcessResponsibleSectorDefault::resolve($connection);
            if ($default === null) return false;
            $responsibleSector = (string) $default['id_setor'];
        } elseif ($joinedSector === '' || !hash_equals($responsibleSector, $joinedSector)
            || (int) ($process['setor_ativo'] ?? 0) !== 1) {
            return false;
        }

        $sectorSql = 'SELECT us.`setor` FROM `usuario_setor` us
             INNER JOIN `setores` setor ON setor.`id_setor` = us.`setor` AND setor.`ativo` = 1
             WHERE us.`usuario` = :usuario AND us.`setor` = :setor'
            . ($usesDefault ? " AND UPPER(TRIM(setor.`codigo_area`)) = 'CARTORIO'" : '')
            . ' LIMIT 1';
        if ($lock && $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sectorSql .= ' FOR UPDATE';
        }
        $sectors = $connection->prepare($sectorSql);
        $sectors->execute([':usuario' => $userId, ':setor' => $responsibleSector]);
        $allowed = $sectors->fetchColumn() !== false;
        $sectors->closeCursor();
        return $allowed;
    }

    /** Resolve o processo vinculado sem devolver dados do caso ao cliente. */
    public static function findProcessIdByLink(PDO $connection, string $linkId): ?string
    {
        if (!$connection->inTransaction()) {
            throw new LogicException('A resolução do vínculo exige transação.');
        }
        $sql = 'SELECT `processo_id` FROM `processos_partes` WHERE `id` = :id LIMIT 1';
        $statement = $connection->prepare($sql);
        $statement->execute([':id' => trim($linkId)]);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        if ($value === false) {
            return null;
        }
        $processId = trim((string) $value);
        return $processId === '' ? null : $processId;
    }

    /** Bloqueia o vínculo depois do processo e confirma que o destino não mudou. */
    public static function linkBelongsToProcess(PDO $connection, string $linkId, string $processId): bool
    {
        if (!$connection->inTransaction()) {
            throw new LogicException('A validação do vínculo exige transação.');
        }
        $sql = 'SELECT `processo_id` FROM `processos_partes` WHERE `id` = :id LIMIT 1';
        if ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $statement = $connection->prepare($sql);
        $statement->execute([':id' => trim($linkId)]);
        $value = $statement->fetchColumn();
        $statement->closeCursor();
        if ($value === false) {
            return false;
        }
        return hash_equals(trim($processId), trim((string) $value));
    }
}
