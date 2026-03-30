<?php
declare(strict_types=1);

/**
 * Catálogo de prioridades processuais (arquivo em app/config).
 */
final class PrioridadesProcessuaisCatalog
{
    /** Mesmo texto usado pela regra automática de parte menor e em processoPrioridades. */
    public static function prioridadeAutomaticaParteMenor(): string
    {
        return 'Criança ou adolescente';
    }

    /** Mesmo texto usado pela regra automática de réu preso (lista gabinete) e em processoPrioridades. */
    public static function prioridadeAutomaticaReuPreso(): string
    {
        return 'Réu preso';
    }

    /** @return list<string> */
    public static function labels(): array
    {
        $path = __DIR__ . '/../config/prioridades_processuais_catalog.php';
        if (!is_file($path)) {
            return [self::prioridadeAutomaticaParteMenor()];
        }
        $data = require $path;
        if (!is_array($data)) {
            return [self::prioridadeAutomaticaParteMenor()];
        }
        $out = [];
        foreach ($data as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        $out = array_values(array_unique($out, SORT_STRING));
        if (!in_array(self::prioridadeAutomaticaParteMenor(), $out, true)) {
            $out[] = self::prioridadeAutomaticaParteMenor();
        }
        if (!in_array(self::prioridadeAutomaticaReuPreso(), $out, true)) {
            $out[] = self::prioridadeAutomaticaReuPreso();
        }

        return $out;
    }

    /** @return list<string> ordenado alfabeticamente (pt-BR). */
    public static function sortedLabels(): array
    {
        $labels = self::labels();
        sort($labels, SORT_LOCALE_STRING);

        return $labels;
    }

    public static function isValid(string $label): bool
    {
        return in_array($label, self::labels(), true);
    }
}
