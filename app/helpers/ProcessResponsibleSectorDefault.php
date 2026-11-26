<?php
declare(strict_types=1);

final class ProcessResponsibleSectorDefault
{
    /** NULL at registration means the operational Cartório, not a stored transfer. */
    public static function resolve(PDO $db): ?array
    {
        // Use the area code, not the user's role, the id, or the display label.
        // Legacy schemas without codigo_area have no configured default.
        $rows = $db->query('SELECT * FROM setores WHERE ativo = 1')->fetchAll(PDO::FETCH_ASSOC);
        $candidates = array_values(array_filter($rows, static fn (array $row): bool =>
            strtoupper(trim((string) ($row['codigo_area'] ?? ''))) === 'CARTORIO'
            && trim((string) ($row['id_setor'] ?? '')) !== ''));
        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
