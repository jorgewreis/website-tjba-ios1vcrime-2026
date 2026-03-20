<?php
declare(strict_types=1);

/**
 * Formatação única de nomes próprios e topônimos (cidades) no sistema.
 * Artigos/preposições permanecem em minúsculas em qualquer posição (ex.: "Maria da Silva", sobrenome "da Costa").
 *
 * Alinhado a {@see /public/assets/js/shared/text-formatter.js}.
 */
final class TextFormatter
{
    /** Mesma lista do JS (TextFormatter.EXCEPTIONS em text-formatter.js). */
    public const DEFAULT_PROPER_NAME_EXCEPTIONS = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na'];

    /**
     * Formata um nome próprio ou nome de cidade aplicando capitalização e limite de tamanho.
     *
     * @param string   $value      Texto bruto
     * @param int      $maxLength  Tamanho máximo em caracteres (UTF-8)
     * @param string[]|null $exceptions Palavras sempre em minúsculas; null = padrão do sistema
     */
    public static function formatProperName(string $value, int $maxLength = 50, ?array $exceptions = null): string
    {
        if ($exceptions === null) {
            $exceptions = self::DEFAULT_PROPER_NAME_EXCEPTIONS;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        if ($normalized === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $formatted = [];

        foreach ($words as $token) {
            $lower = mb_strtolower($token, 'UTF-8');
            if (in_array($lower, $exceptions, true)) {
                $formatted[] = $lower;
                continue;
            }
            $formatted[] = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
        }

        $result = implode(' ', $formatted);
        if (mb_strlen($result, 'UTF-8') > $maxLength) {
            $result = mb_substr($result, 0, $maxLength, 'UTF-8');
            $result = rtrim($result);
        }

        return $result;
    }
}
