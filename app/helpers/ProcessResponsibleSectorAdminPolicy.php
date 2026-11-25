<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/ProfileNormalizer.php';

/** Atribuição de unidade responsável é exclusiva de ADMIN efetivo, não de chefia em geral. */
final class ProcessResponsibleSectorAdminPolicy
{
    /** @param list<string> $effectiveProfiles */
    public static function allows(array $effectiveProfiles): bool
    {
        return in_array('ADMIN', ProfileNormalizer::normalize($effectiveProfiles), true);
    }
}
