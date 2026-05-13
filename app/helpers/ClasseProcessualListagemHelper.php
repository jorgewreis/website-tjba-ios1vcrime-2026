<?php
declare(strict_types=1);

/**
 * Classe processual em listagens: destaque (classe-info) apenas quando há nome real vinculado.
 */
final class ClasseProcessualListagemHelper
{
    public const TEXTO_AUSENTE = 'Classe não informada';

    private static function fold(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        try {
            if (class_exists(Normalizer::class)) {
                $n = Normalizer::normalize($s, Normalizer::FORM_D);
                if ($n !== false) {
                    $s = $n;
                }
                $s = preg_replace('/\p{Mn}/u', '', $s) ?: $s;
            }
        } catch (Throwable $e) {
            // mantém $s
        }
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
        } else {
            $s = strtolower($s);
        }
        $s = preg_replace('/\s+/u', ' ', $s) ?: $s;

        return trim($s);
    }

    /** Indica se a linha deve usar estilo de destaque (.classe-info). */
    public static function temDestaqueListagem(?string $classeNome): bool
    {
        $t = trim((string) ($classeNome ?? ''));
        if ($t === '') {
            return false;
        }
        $f = self::fold($t);
        if ($f === '' || $f === 'classe nao informada') {
            return false;
        }
        if (in_array($f, ['-', '—', '–', '.', 'n/a', 'na', 's/n', 'sn', '?', '...', '…'], true)) {
            return false;
        }

        return true;
    }

    public static function textoListagem(?string $classeNome): string
    {
        return self::temDestaqueListagem($classeNome) ? trim((string) $classeNome) : self::TEXTO_AUSENTE;
    }

    /** Classes do <span> da primeira linha do bloco processo (classe). */
    public static function classesSpanMinor(?string $classeNome): string
    {
        return 'minor-info text-ellipsis' . (self::temDestaqueListagem($classeNome) ? ' classe-info' : '');
    }
}
