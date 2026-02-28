<?php
declare(strict_types=1);

final class TextFormatter
{
    /**
     * Formata um nome próprio aplicando capitalização e limitando o tamanho.
     */
    public static function formatProperName(string $value, int $maxLength = 50, array $exceptions = ['de', 'da', 'do', 'das', 'dos', 'e']): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if ($normalized === '') {
            return '';
        }

        $words = array_filter(explode(' ', $normalized), 'strlen');
        $formatted = [];

        foreach ($words as $index => $token) {
            $lower = strtolower($token);
            if ($index > 0 && in_array($lower, $exceptions, true)) {
                $formatted[] = $lower;
                continue;
            }
            $formatted[] = ucfirst($lower);
        }

        $result = implode(' ', $formatted);
        if (strlen($result) > $maxLength) {
            $result = substr($result, 0, $maxLength);
            $result = rtrim($result);
        }

        return $result;
    }
}
