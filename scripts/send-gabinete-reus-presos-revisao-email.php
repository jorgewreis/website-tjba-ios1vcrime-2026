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
 *   Exemplos: .../public/scripts ou .../public/public/scripts (dois public: docroot = .../public/public).
 *   Alternativa: bash install-cron-gabinete-reus-presos-revisao-email.sh (apos dos2unix se precisar)
 *
 * Manual (referência):
 * - Linux cron: 0 18 * * 4 cd /caminho/absoluto/do/repo && /usr/bin/php /caminho/para/send-gabinete-reus-presos-revisao-email.php
 * - Windows: Agendador de Tarefas — semanal, quinta, 18:00 — php com argumento = caminho absoluto deste arquivo.
 */

require_once __DIR__ . '/resolve_project_root_cli.php';
$repoRoot = resolveProjectRootFromCliScriptsDir(__DIR__);

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
