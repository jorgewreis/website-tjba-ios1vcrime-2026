<?php
declare(strict_types=1);

require_once __DIR__ . '/TextFormatter.php';

/**
 * Evita duplicidade e nomes muito parecidos ao cadastrar profissão livre ("Outra").
 */
final class ProfissaoValidator
{
    /** Valor enviado pelo select quando o usuário escolhe cadastrar profissão manualmente. */
    public const SELECT_OUTRA = '__OUTRA__';

    /**
     * @param list<string> $existentes Valores já usados em `partes` / vínculos (lista distinta).
     * @param string|null $ignorarSeIgualA Na edição, permite manter o mesmo texto sem acusar duplicidade.
     */
    public static function validarNovaProfissao(string $nova, array $existentes, ?string $ignorarSeIgualA = null): void
    {
        $nova = trim($nova);
        if ($nova === '') {
            throw new InvalidArgumentException('Informe o nome da profissão.');
        }
        $nCanon = TextFormatter::formatProperName($nova, 120);
        $ignCanon = $ignorarSeIgualA !== null && trim((string) $ignorarSeIgualA) !== ''
            ? TextFormatter::formatProperName(trim((string) $ignorarSeIgualA), 120)
            : '';

        if ($ignCanon !== '' && self::normalizarChave($nCanon) === self::normalizarChave($ignCanon)) {
            return;
        }

        foreach ($existentes as $ex) {
            $ex = trim((string) $ex);
            if ($ex === '') {
                continue;
            }
            if ($ignCanon !== '' && self::normalizarChave($ex) === self::normalizarChave($ignCanon)) {
                continue;
            }
            if (self::ehSimilarOuIgual($nCanon, $ex)) {
                throw new InvalidArgumentException(
                    'Já existe profissão cadastrada igual ou muito semelhante: "' . $ex . '". Escolha na lista ou ajuste o nome.'
                );
            }
        }
    }

    public static function ehSimilarOuIgual(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }
        $ka = self::normalizarChave($a);
        $kb = self::normalizarChave($b);
        if ($ka === $kb) {
            return true;
        }
        $len = max(mb_strlen($ka, 'UTF-8'), mb_strlen($kb, 'UTF-8'));
        if ($len <= 3) {
            return false;
        }
        similar_text($ka, $kb, $pct);
        if ($pct >= 88.0) {
            return true;
        }
        if ($len >= 6 && strlen($ka) < 256 && strlen($kb) < 256 && function_exists('levenshtein')) {
            $d = levenshtein($ka, $kb);
            if ($d <= 2) {
                return true;
            }
        }

        return false;
    }

    public static function normalizarChave(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
        } else {
            $s = strtolower($s);
        }
        if (class_exists(Transliterator::class)) {
            $t = Transliterator::create('Any-Latin; Latin-ASCII');
            $s = $t->transliterate($s);
        } elseif (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($conv !== false) {
                $s = $conv;
            }
        }
        $s = preg_replace('/\s+/u', ' ', $s);

        return trim($s);
    }
}
