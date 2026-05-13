<?php
declare(strict_types=1);

/**
 * Exemplo de agendamento (sexta às 17:00):
 * - Linux cron: 0 17 * * 5 php /caminho/absoluto/scripts/send-gabinete-reus-presos-revisao-email.php
 * - Windows Task Scheduler: weekly Friday 17:00, action:
 *   Program/script: php
 *   Add arguments: C:\caminho\absoluto\scripts\send-gabinete-reus-presos-revisao-email.php
 */

require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/services/GabineteReusPresosRevisaoEmailService.php';

$connection = Conection::getInstance()->getConnection();
$service = new GabineteReusPresosRevisaoEmailService($connection);
$result = $service->sendResumo();

$success = (bool) ($result['success'] ?? false);
$httpLikeCode = $success ? 0 : 1;

echo json_encode([
    'success' => $success,
    'sent' => (bool) ($result['sent'] ?? false),
    'count' => (int) ($result['count'] ?? 0),
    'message' => (string) ($result['message'] ?? ''),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($httpLikeCode);
