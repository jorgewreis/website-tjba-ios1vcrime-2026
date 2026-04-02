<?php
declare(strict_types=1);
/**
 * Botões Exportar CSV / PDF (listagens internas).
 *
 * Espera:
 * - string $listExportPage — chave frame.php (ex.: feriadoslist, guias)
 * - array<string, string|int|float> $listExportQuery — filtros atuais (serão mesclados com page)
 */
if (!isset($listExportPage) || $listExportPage === '') {
    return;
}
$__eq = isset($listExportQuery) && is_array($listExportQuery) ? $listExportQuery : [];
$__exportParams = $__eq;
$__exportParams['page'] = (string) $listExportPage;
unset($__exportParams['page_number'], $__exportParams['items_per_page']);
$__csv = '/api/internal-list-export/csv.php?' . http_build_query($__exportParams);
$__pdf = '/api/internal-list-export/pdf.php?' . http_build_query($__exportParams);
?>
<button type="button" class="btn btn-secondary-outline height-md" data-nav-href="<?php echo htmlspecialchars($__csv, ENT_QUOTES, 'UTF-8'); ?>" data-nav-target="_blank">Exportar CSV</button>
<button type="button" class="btn btn-secondary-outline height-md" data-nav-href="<?php echo htmlspecialchars($__pdf, ENT_QUOTES, 'UTF-8'); ?>" data-nav-target="_blank">Exportar PDF</button>
