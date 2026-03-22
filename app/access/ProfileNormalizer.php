<?php
declare(strict_types=1);

final class ProfileNormalizer
{
    /**
     * @param array<int, mixed> $userPerfils
     * @return list<string>
     */
    public static function normalize(array $userPerfils): array
    {
        $out = [];
        $seen = [];
        foreach ($userPerfils as $p) {
            $u = strtoupper(trim((string) $p));
            if ($u === 'DIRETORA' || $u === 'MAGISTRADA') {
                $u = 'CHEFIA';
            }
            if ($u === '' || isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $out[] = $u;
        }
        return $out;
    }

    public static function isElevated(array $normalizedPerfils): bool
    {
        return in_array('ADMIN', $normalizedPerfils, true)
            || in_array('CHEFIA', $normalizedPerfils, true);
    }

    /**
     * Qualquer perfil ESTAGIARIO-* ou SERVIDOR-* (área operacional).
     */
    public static function hasAnyCargoAreaComposite(array $normalizedPerfils): bool
    {
        foreach ($normalizedPerfils as $p) {
            if (preg_match('/^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $p) === 1) {
                return true;
            }
        }
        return false;
    }

    public static function hasCompositeForArea(array $normalizedPerfils, string $area): bool
    {
        $suffix = '-' . $area;
        $len = strlen($suffix);
        foreach ($normalizedPerfils as $p) {
            if ($len > 0 && substr($p, -$len) === $suffix
                && (strpos($p, 'ESTAGIARIO-') === 0 || strpos($p, 'SERVIDOR-') === 0)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string> $requiredAnyOf perfis exatos (modo B)
     */
    public static function hasAnyExact(array $normalizedPerfils, array $requiredAnyOf): bool
    {
        foreach ($requiredAnyOf as $req) {
            $r = strtoupper(trim($req));
            if ($r !== '' && in_array($r, $normalizedPerfils, true)) {
                return true;
            }
        }
        return false;
    }

    public static function hasEstudante(array $normalizedPerfils): bool
    {
        return in_array('ESTUDANTE', $normalizedPerfils, true);
    }

    public static function hasConsultor(array $normalizedPerfils): bool
    {
        return in_array('CONSULTOR', $normalizedPerfils, true);
    }
}
