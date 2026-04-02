<?php
declare(strict_types=1);

/**
 * CSV/PDF comuns para exportação de listagens internas.
 */
final class ListExportHelper
{
    /**
     * @param list<array{key:string,label:string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    public static function streamCsv(string $filenameBase, array $columns, array $rows): void
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
        $labels = array_map(static fn (array $c): string => $c['label'], $columns);
        fputcsv($output, $labels, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $line[] = self::cell($row[$col['key']] ?? null);
            }
            fputcsv($output, $line, ';');
        }

        fclose($output);
    }

    /**
     * @param list<array{key:string,label:string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    public static function streamPdf(string $filenameBase, string $title, array $columns, array $rows): void
    {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $filenameBase);
        if ($filename === '') {
            $filename = 'export';
        }
        $filename .= '-' . date('Ymd-His') . '.pdf';

        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;font-size:10px;color:#1a1a1a;}h1{font-size:16px;margin:0 0 4px;}p{margin:0 0 8px;}table{width:100%;border-collapse:collapse;margin-top:8px;}th,td{border:1px solid #ddd;padding:3px 5px;text-align:left;vertical-align:top;}th{background:#f5f5f5;} .meta{font-size:9px;color:#555;}</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1>';
        $html .= '<p class="meta">Gerado em ' . htmlspecialchars(date('d/m/Y H:i:s'), ENT_QUOTES) . ' | Registros: ' . count($rows) . '</p>';
        $html .= '<table><thead><tr>';
        foreach ($columns as $col) {
            $html .= '<th>' . htmlspecialchars($col['label'], ENT_QUOTES) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td>' . htmlspecialchars(self::cell($row[$col['key']] ?? null), ENT_QUOTES) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        $basePath = dirname(__DIR__, 2);
        $autoloadPath = $basePath . '/vendor/autoload.php';
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }

        if (class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            header_remove('Content-Type');
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo $dompdf->output();

            return;
        }

        throw new RuntimeException('Gerador de PDF não encontrado. Instale Dompdf para habilitar a exportação.');
    }

    private static function cell(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_array($v)) {
            return (string) json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return trim((string) $v);
    }
}
