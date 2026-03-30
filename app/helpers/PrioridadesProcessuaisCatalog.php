<?php
declare(strict_types=1);

/**
 * Catálogo de prioridades processuais (código estável + rótulo pt-BR).
 */
final class PrioridadesProcessuaisCatalog
{
    /** @return array<string, string> codigo => label */
    public static function codigoParaLabel(): array
    {
        $path = __DIR__ . '/../config/prioridades_processuais_catalog.php';
        if (!is_file($path)) {
            return [
                'crianca_adolescente' => 'Criança ou adolescente',
                'reu_preso' => 'Réu preso',
            ];
        }
        $data = require $path;
        if (!is_array($data)) {
            return [
                'crianca_adolescente' => 'Criança ou adolescente',
                'reu_preso' => 'Réu preso',
            ];
        }
        $out = [];
        foreach ($data as $codigo => $label) {
            $c = trim((string) $codigo);
            $l = trim((string) $label);
            if ($c !== '' && $l !== '') {
                $out[$c] = $l;
            }
        }

        return $out;
    }

    public static function codigoAutomaticaParteMenor(): string
    {
        return 'crianca_adolescente';
    }

    public static function codigoAutomaticaReuPreso(): string
    {
        return 'reu_preso';
    }

    public static function codigoViolenciaDomestica(): string
    {
        return 'violencia_domestica';
    }

    /** @deprecated use codigoAutomaticaParteMenor + labelForCodigo */
    public static function prioridadeAutomaticaParteMenor(): string
    {
        return self::labelForCodigo(self::codigoAutomaticaParteMenor());
    }

    /** @deprecated use codigoAutomaticaReuPreso + labelForCodigo */
    public static function prioridadeAutomaticaReuPreso(): string
    {
        return self::labelForCodigo(self::codigoAutomaticaReuPreso());
    }

    public static function labelForCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        $map = self::codigoParaLabel();

        return $map[$codigo] ?? $codigo;
    }

    public static function codigoForLabel(string $label): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        foreach (self::codigoParaLabel() as $codigo => $l) {
            if ($l === $label) {
                return $codigo;
            }
        }

        return null;
    }

    /**
     * Aceita código canónico ou rótulo legado (pt-BR) ou slugs antigos (guias, MP).
     */
    public static function normalizeToCodigo(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        $map = self::codigoParaLabel();
        if (isset($map[$input])) {
            return $input;
        }
        $byLabel = self::codigoForLabel($input);
        if ($byLabel !== null) {
            return $byLabel;
        }

        return self::legacyTokenToCodigo($input);
    }

    public static function legacyTokenToCodigo(string $token): ?string
    {
        $t = strtolower(trim(str_replace([' ', '_'], '-', $token)));

        $aliases = [
            'reu-preso' => 'reu_preso',
            'reupreso' => 'reu_preso',
            'mulher' => 'violencia_domestica',
            'crianca-adolescente' => 'crianca_adolescente',
            'crianca_adolescente' => 'crianca_adolescente',
            'idoso' => 'idoso',
            'pessoa-deficiencia' => 'pessoa_deficiencia',
            'pessoa_deficiencia' => 'pessoa_deficiencia',
            'doenca-grave' => 'pessoa_doenca_grave',
            'doença-grave' => 'pessoa_doenca_grave',
        ];

        return $aliases[$t] ?? null;
    }

    /** Mapeia valor antigo da coluna `prioridade` (slug) antes da unificação. */
    public static function slugLegadoParaCodigo(string $slug): ?string
    {
        $slug = trim($slug);
        $map = [
            'mulher' => 'violencia_domestica',
            'crianca_adolescente' => 'crianca_adolescente',
            'idoso' => 'idoso',
            'pessoa_deficiencia' => 'pessoa_deficiencia',
        ];

        return $map[$slug] ?? self::normalizeToCodigo($slug);
    }

    /** @return list<string> códigos válidos */
    public static function codigos(): array
    {
        return array_keys(self::codigoParaLabel());
    }

    /** @return list<string> rótulos (compat UI legada) */
    public static function labels(): array
    {
        return array_values(self::codigoParaLabel());
    }

    /** @return list<string> rótulos ordenados pt-BR */
    public static function sortedLabels(): array
    {
        $labels = self::labels();
        sort($labels, SORT_LOCALE_STRING);

        return $labels;
    }

    /** @return list<array{codigo: string, label: string}> ordenado por rótulo */
    public static function sortedCatalogoItens(): array
    {
        $map = self::codigoParaLabel();
        $rows = [];
        foreach ($map as $codigo => $label) {
            $rows[] = ['codigo' => $codigo, 'label' => $label];
        }
        usort($rows, static function (array $a, array $b): int {
            return strcoll($a['label'], $b['label']);
        });

        return $rows;
    }

    public static function isValidCodigo(string $codigo): bool
    {
        return isset(self::codigoParaLabel()[trim($codigo)]);
    }

    /** @deprecated use isValidCodigo(normalizeToCodigo) */
    public static function isValid(string $label): bool
    {
        return self::codigoForLabel($label) !== null || self::normalizeToCodigo($label) !== null;
    }
}
