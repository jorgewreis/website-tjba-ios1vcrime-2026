<?php
declare(strict_types=1);

require_once __DIR__ . '/ListExportPresentation.php';
require_once __DIR__ . '/PdfStreamHelper.php';

/**
 * CSV/PDF comuns para exportação de listagens internas.
 */
final class ListExportHelper
{
    /**
     * @param list<array{key:string,label:string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    public static function streamCsv(string $filenameBase, string $sessionTitle, string $filterSummary, array $columns, array $rows): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase);
        if ($filename === '') {
            $filename = 'export';
        }
        $filename .= '-' . date('Ymd-His') . '.csv';

        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new RuntimeException('Não foi possível gerar o arquivo CSV.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        ListExportPresentation::writeCsvPreamble($output, count($columns), $sessionTitle, $filterSummary, count($rows));
        $labels = array_map(static fn (array $c): string => ListExportPresentation::csvText($c['label']), $columns);
        fputcsv($output, $labels, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $key = $col['key'];
                $value = $row[$key] ?? null;
                $formatted = ListExportPresentation::formatCell($key, $value);
                // Apenas números tipados dispensam a proteção de texto.
                $line[] = is_int($value) || is_float($value)
                    ? $formatted : ListExportPresentation::csvText($formatted);
            }
            fputcsv($output, $line, ';');
        }

        fclose($output);
    }

    /**
     * @param list<array{key:string,label:string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    public static function streamPdf(string $filenameBase, string $sessionTitle, string $filterSummary, array $columns, array $rows): void
    {
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . htmlspecialchars($sessionTitle, ENT_QUOTES, 'UTF-8') . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:10px;color:#1a1a1a;}table{width:100%;border-collapse:collapse;margin-top:4px;}th,td{border:1px solid #ddd;padding:3px 5px;text-align:left;vertical-align:top;white-space:pre-line;word-break:break-word;}th{background:#f5f5f5;}</style>';
        $html .= '</head><body>';
        $html .= ListExportPresentation::htmlInstitutionalBlock();
        $html .= ListExportPresentation::htmlReportIntro($sessionTitle, $filterSummary, count($rows));
        $html .= '<table><thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $key = $col['key'];
                $cell = ListExportPresentation::formatCell($key, $row[$key] ?? null);
                $html .= '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        PdfStreamHelper::streamHtml($filenameBase, $html, [
            'paper' => 'A4',
            'orientation' => 'landscape',
            'isRemoteEnabled' => false,
        ]);
    }
}
