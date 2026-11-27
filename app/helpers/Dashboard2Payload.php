<?php
declare(strict_types=1);

/** Dashboard 2 only: narrows legacy summary fields to metrics with a verified contract. */
final class Dashboard2Payload
{
    /** @param array<string, mixed> $summary @return array<string, mixed> */
    public static function fromResumo(array $summary): array
    {
        // The legacy count covers D0 through D+7 and uses SQL CURDATE(). The
        // Dashboard 2 seven-day card requires D0..D+6 in America/Bahia and a
        // matching detail list; until that source contract exists, fail closed.
        if (isset($summary['agenda']) && is_array($summary['agenda'])) {
            $summary['agenda']['audiencias_7d'] = null;
        }

        return $summary;
    }
}