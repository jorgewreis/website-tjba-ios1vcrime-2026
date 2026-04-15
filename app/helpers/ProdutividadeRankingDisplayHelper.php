<?php
declare(strict_types=1);

/**
 * Formatação de nome para listagens de ranking de produtividade.
 */
final class ProdutividadeRankingDisplayHelper
{
    /**
     * Dois primeiros nomes: primeiro token + segundo token;
     * se o segundo for partícula (de, da, do, das, dos), inclui o terceiro (ex.: "Silvia de Jesus").
     */
    public static function doisPrimeirosNomes(string $full): string
    {
        $full = trim($full);
        if ($full === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $full, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === []) {
            return '';
        }
        $n = count($parts);
        if ($n === 1) {
            return $parts[0];
        }
        $particles = ['de', 'da', 'do', 'das', 'dos'];
        $p1 = mb_strtolower($parts[1], 'UTF-8');
        if (in_array($p1, $particles, true) && $n >= 3) {
            return $parts[0] . ' ' . $parts[1] . ' ' . $parts[2];
        }

        return $parts[0] . ' ' . $parts[1];
    }
}
