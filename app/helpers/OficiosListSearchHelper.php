<?php
declare(strict_types=1);

/**
 * Filtro de listagem de ofícios (cartório/gabinete) por número do ofício (sequencial/ano).
 *
 * Aceita vários formatos (só sequencial, sequencial+ano, barra, traço, espaço, ano/sequencial,
 * dígitos colados tipo 422026 → 42/2026). Combina interpretações com OR para listar tudo que
 * corresponder. Não aplica status: quem chama deve omitir filtro de status quando há busca
 * por ofício (já feito nos repositórios).
 */
final class OficiosListSearchHelper
{
    private const COLLATION = 'utf8mb4_unicode_ci';

    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2100;

    /**
     * @param array<string, mixed> $filters `oficio` (texto) e opcionalmente `ano` (ano do select da lista)
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    public static function buildOficioConditions(string $tableAlias, array $filters): array
    {
        $oficio = trim((string) ($filters['oficio'] ?? ''));
        if ($oficio === '') {
            return [[], []];
        }

        $variants = self::collectMatchVariants($oficio, $filters);
        if ($variants !== []) {
            return self::variantsToOrClause($tableAlias, $variants);
        }

        $compact = preg_replace('/\s+/', '', $oficio);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $compact);

        return [
            [
                sprintf(
                    'CONCAT(CAST(%s.`numero_sequencial` AS CHAR), \'/\', CAST(%s.`ano` AS CHAR)) LIKE :f_oficio_like COLLATE %s',
                    $tableAlias,
                    $tableAlias,
                    self::COLLATION
                ),
            ],
            [':f_oficio_like' => '%' . $escaped . '%'],
        ];
    }

    /**
     * @return list<array{type:'exact',seq:int,year:int}|array{type:'seq',seq:int}|array{type:'year',year:int}>
     */
    private static function collectMatchVariants(string $oficio, array $filters): array
    {
        $out = [];

        $addExact = static function (int $seq, int $year) use (&$out): void {
            if ($seq < 1 || $year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                return;
            }
            $out['e:' . $seq . ':' . $year] = ['type' => 'exact', 'seq' => $seq, 'year' => $year];
        };
        $addSeq = static function (int $seq) use (&$out): void {
            if ($seq < 1) {
                return;
            }
            $out['s:' . $seq] = ['type' => 'seq', 'seq' => $seq];
        };
        $addYear = static function (int $year) use (&$out): void {
            if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                return;
            }
            $out['y:' . $year] = ['type' => 'year', 'year' => $year];
        };

        $t = trim($oficio);

        // Explícito: sequencial / ano (barra, traço, ponto)
        if (preg_match('/^(\d+)\s*[/\-.]\s*(\d{4})$/u', $t, $m)) {
            $addExact((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{4})\s*[/\-.]\s*(\d+)$/u', $t, $m)) {
            $addExact((int) $m[2], (int) $m[1]);
        }

        // Espaço entre sequencial e ano
        if (preg_match('/^(\d+)\s+(\d{4})$/u', $t, $m)) {
            $addExact((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{4})\s+(\d+)$/u', $t, $m)) {
            $addExact((int) $m[2], (int) $m[1]);
        }

        $digits = preg_replace('/\D/', '', $t);
        $hasStructuralSep = (bool) preg_match('/[/\-.]/', $t) || (bool) preg_match('/^\d+\s+\d{4}$/u', $t) || (bool) preg_match('/^\d{4}\s+\d+$/u', $t);

        if ($digits !== '' && ctype_digit($digits)) {
            $len = strlen($digits);
            $splitExact = false;

            if ($len >= 5) {
                $y = (int) substr($digits, -4);
                if ($y >= self::MIN_YEAR && $y <= self::MAX_YEAR) {
                    $seqStr = substr($digits, 0, -4);
                    if ($seqStr !== '' && ctype_digit($seqStr)) {
                        $seqVal = (int) $seqStr;
                        if ($seqVal >= 1) {
                            $addExact($seqVal, $y);
                            $splitExact = true;
                        }
                    }
                }
            }

            if ($len === 4) {
                $n = (int) $digits;
                if ($n >= self::MIN_YEAR && $n <= self::MAX_YEAR) {
                    $addYear($n);
                }
                $addSeq($n);
            } elseif (!$splitExact || $len <= 4) {
                $addSeq((int) $digits);
            }
        }

        // Select "Ano do ofício" + só sequencial na caixa (sem / - .)
        $anoF = trim((string) ($filters['ano'] ?? ''));
        $cleanNoSpace = preg_replace('/\s+/', '', $t);
        if (
            $anoF !== ''
            && ctype_digit($anoF)
            && preg_match('/^\d+$/', $cleanNoSpace)
            && !strpbrk($t, '/-.')
        ) {
            $yl = (int) $anoF;
            if ($yl >= self::MIN_YEAR && $yl <= self::MAX_YEAR && strlen($cleanNoSpace) <= 4 && !$hasStructuralSep) {
                $addExact((int) $cleanNoSpace, $yl);
            }
        }

        return array_values($out);
    }

    /**
     * @param list<array{type:'exact',seq:int,year:int}|array{type:'seq',seq:int}|array{type:'year',year:int}> $variants
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private static function variantsToOrClause(string $tableAlias, array $variants): array
    {
        $parts = [];
        $params = [];
        $i = 0;

        foreach ($variants as $v) {
            if ($v['type'] === 'exact') {
                $parts[] = sprintf(
                    '(%s.`numero_sequencial` = :ofs_%d_s AND %s.`ano` = :ofs_%d_y)',
                    $tableAlias,
                    $i,
                    $tableAlias,
                    $i
                );
                $params[':ofs_' . $i . '_s'] = $v['seq'];
                $params[':ofs_' . $i . '_y'] = $v['year'];
                $i++;
            } elseif ($v['type'] === 'seq') {
                $parts[] = sprintf('(%s.`numero_sequencial` = :ofs_%d_s)', $tableAlias, $i);
                $params[':ofs_' . $i . '_s'] = $v['seq'];
                $i++;
            } elseif ($v['type'] === 'year') {
                $parts[] = sprintf('(%s.`ano` = :ofs_%d_y)', $tableAlias, $i);
                $params[':ofs_' . $i . '_y'] = $v['year'];
                $i++;
            }
        }

        if ($parts === []) {
            return [[], []];
        }

        return [['(' . implode(' OR ', $parts) . ')'], $params];
    }
}
