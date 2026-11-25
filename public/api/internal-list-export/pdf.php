<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/internal-list-export/pdf.php');
require_once __DIR__ . '/_common.php';
require_once dirname(__DIR__, 3) . '/app/helpers/ListExportFilterSummary.php';

try {
    $ctx = internal_list_export_bootstrap();
    $spec = InternalListExportHandlers::dispatch($ctx['listPage'], $ctx['pdo'], $ctx['userId'], $_GET);
    $filterSummary = ListExportFilterSummary::forInternalList($ctx['listPage'], $_GET);
    ListExportHelper::streamPdf($spec['slug'], $spec['title'], $filterSummary, $spec['columns'], $spec['rows']);
} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    $code = str_contains($msg, 'gestão') ? 403 : 400;
    internal_list_export_http_error($code, $msg);
} catch (RuntimeException $e) {
    internal_list_export_http_error(500, ApiErrorReporter::genericMessage($e, 'public/api/internal-list-export/pdf.php'));
} catch (Throwable $e) {
    error_log('internal-list-export pdf: ' . ApiErrorReporter::genericMessage($e, 'public/api/internal-list-export/pdf.php'));
    internal_list_export_http_error(500, 'Erro ao exportar PDF.');
}
