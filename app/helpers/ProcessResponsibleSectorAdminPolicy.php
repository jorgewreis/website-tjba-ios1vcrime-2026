<?php
declare(strict_types=1);

require_once __DIR__ . '/../access/ProfileNormalizer.php';

/** Somente ADMIN efetivo atribui sem lotação de origem ou restaura o padrão. */
final class ProcessResponsibleSectorAdminPolicy
{
    /** @param list<string> $effectiveProfiles */
    public static function allows(array $effectiveProfiles): bool
    {
        return in_array('ADMIN', ProfileNormalizer::normalize($effectiveProfiles), true);
    }
}
