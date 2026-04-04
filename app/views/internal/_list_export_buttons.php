<?php
declare(strict_types=1);
/**
 * Select de exportação CSV/PDF (listagens internas).
 *
 * Modo A — URLs explícitas (ex.: produtividade):
 *   - string $listExportCsvUrl, $listExportPdfUrl
 *   - string $listExportControlId (recomendado) — sufixo estável para id do <select>
 *
 * Modo B — internal-list-export:
 *   - string $listExportPage — chave frame.php (ex.: feriadoslist)
 *   - array<string, string|int|float> $listExportQuery — filtros (mesclados com page)
 */
$__csv = null;
$__pdf = null;
if (
    isset($listExportCsvUrl, $listExportPdfUrl)
    && is_string($listExportCsvUrl)
    && is_string($listExportPdfUrl)
    && $listExportCsvUrl !== ''
    && $listExportPdfUrl !== ''
) {
    $__csv = $listExportCsvUrl;
    $__pdf = $listExportPdfUrl;
} else {
    if (!isset($listExportPage) || $listExportPage === '') {
        return;
    }
    $__eq = isset($listExportQuery) && is_array($listExportQuery) ? $listExportQuery : [];
    $__exportParams = $__eq;
    $__exportParams['page'] = (string) $listExportPage;
    unset($__exportParams['page_number'], $__exportParams['items_per_page']);
    $__csv = '/api/internal-list-export/csv.php?' . http_build_query($__exportParams);
    $__pdf = '/api/internal-list-export/pdf.php?' . http_build_query($__exportParams);
}

$__idSuffix = '';
if (isset($listExportControlId) && is_string($listExportControlId) && $listExportControlId !== '') {
    $__idSuffix = $listExportControlId;
} elseif (isset($listExportPage) && is_string($listExportPage) && $listExportPage !== '') {
    $__idSuffix = $listExportPage;
}
if ($__idSuffix === '') {
    $__idSuffix = substr(sha1($__csv . "\0" . $__pdf), 0, 10);
}
$__selectId = 'list-export-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $__idSuffix);
?>
<div class="col">
    <select
        id="<?php echo htmlspecialchars($__selectId, ENT_QUOTES, 'UTF-8'); ?>"
        class="list-export-select width-300 height-md white"
        aria-label="Exportar lista"
        data-csv-href="<?php echo htmlspecialchars($__csv, ENT_QUOTES, 'UTF-8'); ?>"
        data-pdf-href="<?php echo htmlspecialchars($__pdf, ENT_QUOTES, 'UTF-8'); ?>"
    >
        <option value="" selected disabled>Exportar lista</option>
        <option value="csv">Exportar CSV</option>
        <option value="pdf">Exportar PDF</option>
    </select>
</div>
