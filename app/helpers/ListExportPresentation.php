<?php
declare(strict_types=1);

/**
 * Cabeçalho institucional e formatação de células para exportações CSV/PDF.
 */
final class ListExportPresentation
{
    public const HEADER_LINE_1 = 'Poder Judiciário do Estado da Bahia';
    public const HEADER_LINE_2 = 'Primeira Vara Crime da Comarca de Ilhéus';
    public const HEADER_LINE_3 = 'Sistema de Gestão do Cartório - WebApp';

    /**
     * @return list<string>
     */
    public static function institutionalHeaderLines(): array
    {
        return [
            self::HEADER_LINE_1,
            self::HEADER_LINE_2,
            self::HEADER_LINE_3,
        ];
    }

    public static function buildResultsMetaLine(int $resultCount): string
    {
        $dateBr = date('d/m/Y');
        $timeBr = date('H:i:s');
        $n = max(0, $resultCount);
        $word = $n === 1 ? 'resultado' : 'resultados';

        return sprintf(
            'Documento gerado em %s às %s horas, retornando %d %s de acordo com os filtros acima.',
            $dateBr,
            $timeBr,
            $n,
            $word
        );
    }

    /**
     * Linhas 1–3: timbre; 4: em branco; 5: título da sessão; 6: filtros; 7: data/hora e quantidade; 8: em branco.
     *
     * @param resource $output
     */
    public static function writeCsvPreamble(
        $output,
        int $columnCount,
        string $sessionTitle,
        string $filterSummary,
        int $resultCount
    ): void {
        $n = max(1, $columnCount);
        foreach (self::institutionalHeaderLines() as $line) {
            $row = array_fill(0, $n, '');
            $row[0] = $line;
            fputcsv($output, $row, ';');
        }
        fputcsv($output, array_fill(0, $n, ''), ';');

        $titleRow = array_fill(0, $n, '');
        $titleRow[0] = $sessionTitle;
        fputcsv($output, $titleRow, ';');

        $filterRow = array_fill(0, $n, '');
        $filterRow[0] = $filterSummary;
        fputcsv($output, $filterRow, ';');

        $metaRow = array_fill(0, $n, '');
        $metaRow[0] = self::buildResultsMetaLine($resultCount);
        fputcsv($output, $metaRow, ';');

        fputcsv($output, array_fill(0, $n, ''), ';');
    }

    public static function htmlInstitutionalBlock(): string
    {
        $lines = self::institutionalHeaderLines();
        $out = '<div class="export-inst" style="text-align:center;margin:0 0 10px;font-weight:bold;font-size:11px;line-height:1.35;">';
        foreach ($lines as $line) {
            $out .= '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Após o timbre: espaço em branco, título da sessão, linha de filtros, linha de data/quantidade, espaço antes da tabela.
     */
    public static function htmlReportIntro(string $sessionTitle, string $filterSummary, int $resultCount): string
    {
        $meta = htmlspecialchars(self::buildResultsMetaLine($resultCount), ENT_QUOTES, 'UTF-8');

        return '<div style="height:10px;"></div>'
            . '<h1 style="text-align:center;margin:0 0 6px;font-size:16px;">'
            . htmlspecialchars($sessionTitle, ENT_QUOTES, 'UTF-8')
            . '</h1>'
            . '<p class="export-filters" style="text-align:center;margin:0 0 6px;font-size:10px;line-height:1.35;">'
            . htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8')
            . '</p>'
            . '<p class="export-meta-line" style="text-align:center;margin:0 0 12px;font-size:10px;color:#333;line-height:1.35;">'
            . $meta
            . '</p>'
            . '<div style="height:6px;"></div>';
    }

    public static function formatCell(string $columnKey, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($columnKey === 'Alocados' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $ini = trim((string) ($item['iniciais'] ?? ''));
                if ($ini !== '') {
                    $lines[] = $ini;
                }
            }

            return implode('; ', $lines);
        }

        if ($columnKey === 'subtarefas_processos' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $num = trim((string) ($item['numero_processo'] ?? ''));
                if ($num !== '' && $num !== '—') {
                    $lines[] = $num;
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'partes_cumprimento' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nome = trim((string) ($item['nome_parte'] ?? ''));
                if ($nome !== '') {
                    $lines[] = $nome;
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'telefones' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $num = trim((string) ($item['numero'] ?? ''));
                    if ($num !== '') {
                        $lines[] = $num;
                    }
                } else {
                    $t = trim((string) $item);
                    if ($t !== '') {
                        $lines[] = $t;
                    }
                }
            }

            return implode("\n", $lines);
        }

        if ($columnKey === 'instituicoes' && is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nome = trim((string) ($item['nome'] ?? ''));
                $sigla = trim((string) ($item['sigla'] ?? ''));
                if ($nome !== '' && $sigla !== '') {
                    $lines[] = $sigla . ' — ' . $nome;
                } elseif ($nome !== '') {
                    $lines[] = $nome;
                } elseif ($sigla !== '') {
                    $lines[] = $sigla;
                }
            }

            return implode("\n", $lines);
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    public static function formatCsvCell(string $columnKey, mixed $value): string
    {
        return self::neutralizeCsvFormula(self::formatCell($columnKey, $value));
    }

    private static function neutralizeCsvFormula(string $cell): string
    {
        if ($cell === '') {
            return '';
        }

        return preg_match('/^[=+\-@]/', $cell) === 1 ? "'" . $cell : $cell;
    }
}
