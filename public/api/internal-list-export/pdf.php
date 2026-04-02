<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

try {
    $ctx = internal_list_export_bootstrap();
    $spec = InternalListExportHandlers::dispatch($ctx['listPage'], $ctx['pdo'], $ctx['userId'], $_GET);
    ListExportHelper::streamPdf($spec['slug'], $spec['title'], $spec['columns'], $spec['rows']);
} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    $code = str_contains($msg, 'gestão') ? 403 : 400;
    internal_list_export_http_error($code, $msg);
} catch (RuntimeException $e) {
    internal_list_export_http_error(500, $e->getMessage());
} catch (Throwable $e) {
    error_log('internal-list-export pdf: ' . $e->getMessage());
    internal_list_export_http_error(500, 'Erro ao exportar PDF.');
}
