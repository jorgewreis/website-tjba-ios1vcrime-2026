<?php
declare(strict_types=1);

/**
 * Resolve o alias api_publica_* a partir dos dígitos do número CNJ (Resolução CNJ 65/2008 — FAQ numeração única).
 * Segmento J no índice 13; TR no índice 14–15 (20 dígitos).
 */
final class DataJudTribunalResolver
{
    /** @var array<string, string> código TR (2 dígitos) → slug após api_publica_ */
    private const JUSTICA_ESTADUAL = [
        '01' => 'tjac',
        '02' => 'tjal',
        '03' => 'tjam',
        '04' => 'tjap',
        '05' => 'tjba',
        '06' => 'tjce',
        '07' => 'tjdft',
        '08' => 'tjes',
        '09' => 'tjgo',
        '10' => 'tjma',
        '11' => 'tjmt',
        '12' => 'tjms',
        '13' => 'tjmg',
        '14' => 'tjpa',
        '15' => 'tjpb',
        '16' => 'tjpr',
        '17' => 'tjpe',
        '18' => 'tjpi',
        '19' => 'tjrj',
        '20' => 'tjrn',
        '21' => 'tjrs',
        '22' => 'tjro',
        '23' => 'tjrr',
        '24' => 'tjsc',
        '25' => 'tjse',
        '26' => 'tjsp',
        '27' => 'tjto',
    ];

    /** Mesma ordem dos códigos de Justiça Estadual (Justiça Eleitoral regional). */
    private const JUSTICA_ELEITORAL = [
        '01' => 'tre-ac',
        '02' => 'tre-al',
        '03' => 'tre-ap',
        '04' => 'tre-am',
        '05' => 'tre-ba',
        '06' => 'tre-ce',
        '07' => 'tre-dft',
        '08' => 'tre-es',
        '09' => 'tre-go',
        '10' => 'tre-ma',
        '11' => 'tre-mt',
        '12' => 'tre-ms',
        '13' => 'tre-mg',
        '14' => 'tre-pa',
        '15' => 'tre-pb',
        '16' => 'tre-pr',
        '17' => 'tre-pe',
        '18' => 'tre-pi',
        '19' => 'tre-rj',
        '20' => 'tre-rn',
        '21' => 'tre-rs',
        '22' => 'tre-ro',
        '23' => 'tre-rr',
        '24' => 'tre-sc',
        '25' => 'tre-se',
        '26' => 'tre-sp',
        '27' => 'tre-to',
    ];

    /** State military courts use TR codes 13 (MG), 21 (RS), and 26 (SP). */
    private const MILITAR_ESTADUAL = [
        '13' => 'tjmmg',
        '21' => 'tjmrs',
        '26' => 'tjmsp',
    ];

    /**
     * Retorna o slug (ex.: tjba) ou null se não houver mapeamento seguro.
     */
    public static function resolveAliasFromDigits(string $twentyDigits): ?string
    {
        if (strlen($twentyDigits) !== 20 || !ctype_digit($twentyDigits)) {
            return null;
        }

        $j = $twentyDigits[13];
        $tr = substr($twentyDigits, 14, 2);

        switch ($j) {
            // A API Pública do DataJud não cobre o STF nem o CNJ.
            case '1':
                return null;
            case '2':
                return null;
            case '3':
                return $tr === '00' ? 'stj' : null;
            case '4':
                if ($tr === '01') {
                    return 'trf1';
                }
                if ($tr === '02') {
                    return 'trf2';
                }
                if ($tr === '03') {
                    return 'trf3';
                }
                if ($tr === '04') {
                    return 'trf4';
                }
                if ($tr === '05') {
                    return 'trf5';
                }
                if ($tr === '06') {
                    return 'trf6';
                }

                return null;
            case '5':
                if ($tr === '00') {
                    return 'tst';
                }
                $n = (int) $tr;
                if ($n >= 1 && $n <= 24) {
                    return 'trt' . $n;
                }

                return null;
            case '6':
                if ($tr === '00') {
                    return 'tse';
                }

                return self::JUSTICA_ELEITORAL[$tr] ?? null;
            case '7':
                return $tr === '00' ? 'stm' : null;
            case '8':
                return self::JUSTICA_ESTADUAL[$tr] ?? null;
            case '9':
                return self::MILITAR_ESTADUAL[$tr] ?? null;
            default:
                return null;
        }
    }

    public static function buildSearchUrl(string $baseUrl, string $alias): string
    {
        $base = rtrim($baseUrl, '/');

        return $base . '/api_publica_' . $alias . '/_search';
    }
}
