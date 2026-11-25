<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$expectedArgs = ['--execute', '--confirm=purge-expired-atas'];
if (array_slice($argv, 1) !== $expectedArgs) {
    fwrite(STDERR, "Uso: php scripts/purge-expired-ata-pdfs.php --execute --confirm=purge-expired-atas\n");
    exit(64);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/helpers/AuditLoggerHelper.php';
require_once dirname(__DIR__) . '/app/services/SalaAudienciasAtaService.php';

$runId = bin2hex(random_bytes(16));
$startedAt = gmdate(DATE_ATOM);
$writeAudit = static function (PDO $pdo, string $action, array $context): void {
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (!error_log('ATA_RETENTION_AUDIT ' . $action . ' ' . $json)) {
        throw new RuntimeException('Não foi possível registrar o evento no error_log operacional.');
    }
    AuditLogger::log($pdo, 'system:ata-retention', $action, $context);
};
try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $writeAudit($pdo, 'ata_retention_started', ['run_id' => $runId, 'started_at' => $startedAt]);
    $result = (new SalaAudienciasAtaService($pdo))->purgeAtasExpiradas();
    $action = $result['failed'] === 0 ? 'ata_retention_completed' : 'ata_retention_partial';
    $writeAudit($pdo, $action, [
        'run_id' => $runId,
        'started_at' => $startedAt,
        'completed_at' => gmdate(DATE_ATOM),
        'processed_count' => $result['processed'],
        'failed_count' => $result['failed'],
    ]);
    fwrite(STDOUT, json_encode([
        'run_id' => $runId,
        'processed_count' => $result['processed'],
        'failed_count' => $result['failed'],
    ], JSON_THROW_ON_ERROR) . "\n");
    if ($result['failed'] > 0) {
        exit(2);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $writeAudit($pdo, 'ata_retention_failed', [
                'run_id' => $runId,
                'started_at' => $startedAt,
                'failed_at' => gmdate(DATE_ATOM),
                'exception_type' => get_class($e),
            ]);
        } catch (Throwable $auditError) {
            error_log('[ata-retention] Não foi possível persistir auditoria da falha; run_id=' . $runId . ' tipo=' . get_class($auditError));
        }
    }
    fwrite(STDERR, "A retenção de atas falhou; verifique o log de auditoria e tente novamente.\n");
    exit(1);
}
