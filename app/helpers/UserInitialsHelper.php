<?php
declare(strict_types=1);

/**
 * Iniciais de exibição (avatar, alocações): duas letras a partir do nome completo,
 * ignorando partículas comuns do português (ex.: "Ana da Silva" → AS, não AD).
 */
final class UserInitialsHelper
{
    /** Palavras ignoradas ao extrair a primeira e a última palavra significativa. */
    private const PARTICULAS = [
        'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'na', 'no', 'nas', 'nos',
        'ao', 'aos', 'à', 'às',
    ];

    /**
     * Iniciais a partir dos campos nome e sobrenome (como em `usuarios`).
     */
    public static function fromNomeSobrenome(string $nome, string $sobrenome): string
    {
        $full = trim(trim($nome) . ' ' . trim($sobrenome));
        if ($full === '') {
            return '—';
        }
        return self::fromFullName($full);
    }

    /**
     * Iniciais a partir de um nome completo em uma única string (ex.: Helper legado).
     */
    public static function fromFullName(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return '—';
        }

        $tokens = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            return '—';
        }

        $meaningful = self::filterParticles($tokens);
        if ($meaningful === []) {
            $meaningful = $tokens;
        }

        $enc = 'UTF-8';
        if (count($meaningful) === 1) {
            $w = $meaningful[0];
            $len = mb_strlen($w, $enc);
            if ($len >= 2) {
                return mb_strtoupper(mb_substr($w, 0, 2, $enc), $enc);
            }
            return mb_strtoupper(mb_substr($w, 0, 1, $enc), $enc);
        }

        $first = mb_substr($meaningful[0], 0, 1, $enc);
        $last = mb_substr($meaningful[count($meaningful) - 1], 0, 1, $enc);

        return mb_strtoupper($first . $last, $enc);
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private static function filterParticles(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            $trimmed = trim($t, " \t\n\r\0\x0B.,;");
            if ($trimmed === '') {
                continue;
            }
            $norm = mb_strtolower($trimmed, 'UTF-8');
            if (in_array($norm, self::PARTICULAS, true)) {
                continue;
            }
            $out[] = $trimmed;
        }
        return $out;
    }
}
