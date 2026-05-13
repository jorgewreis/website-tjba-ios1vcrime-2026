<?php
declare(strict_types=1);

/**
 * Agendamento padrão desejado: toda quinta-feira às 18:00.
 *
 * Instalação automática do agendamento no SO:
 * - Windows (PowerShell como administrador, uma vez):
 *   scripts\register-gabinete-reus-presos-revisao-email-schedule.ps1
 * - Linux: use o instalador .php (nao rode `php` em cima do arquivo .sh — isso nao executa o shell):
 *   php scripts/install-cron-gabinete-reus-presos-revisao-email.php
 *   Se o deploy colocou os scripts em public/scripts:
 *   cd .../apps/public/scripts && php install-cron-gabinete-reus-presos-revisao-email.php
 *   Alternativa: bash install-cron-gabinete-reus-presos-revisao-email.sh (apos dos2unix se precisar)
 *
 * Manual (referência):
 * - Linux cron: 0 18 * * 4 cd /caminho/absoluto/do/repo && /usr/bin/php /caminho/para/send-gabinete-reus-presos-revisao-email.php
 * - Windows: Agendador de Tarefas — semanal, quinta, 18:00 — php com argumento = caminho absoluto deste arquivo.
 */

$repoRoot = __DIR__;
if (basename($repoRoot) === 'scripts' && basename(dirname($repoRoot)) === 'public') {
    $repoRoot = dirname(dirname($repoRoot));
} elseif (basename($repoRoot) === 'scripts') {
    $repoRoot = dirname($repoRoot);
}

require_once $repoRoot . '/app/helpers/Conection.php';
require_once $repoRoot . '/app/services/GabineteReusPresosRevisaoEmailService.php';

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
