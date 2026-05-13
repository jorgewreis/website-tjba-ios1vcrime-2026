<?php
declare(strict_types=1);

/**
 * Agendamento padrão desejado: toda quinta-feira às 18:00.
 *
 * Instalação automática do agendamento no SO:
 * - Windows (PowerShell como administrador, uma vez):
 *   scripts\register-gabinete-reus-presos-revisao-email-schedule.ps1
 * - Linux (na raiz do repo, NAO em public/) — recomendado (evita problema de CRLF no .sh):
 *   php scripts/install-cron-gabinete-reus-presos-revisao-email.php
 *   Alternativa: bash scripts/install-cron-gabinete-reus-presos-revisao-email.sh
 *   (se o Bash disser "No such file": dos2unix no .sh ou use o instalador .php acima)
 *
 * Manual (referência):
 * - Linux cron: 0 18 * * 4 cd /caminho/absoluto/do/repo && /usr/bin/php scripts/send-gabinete-reus-presos-revisao-email.php
 * - Windows: Agendador de Tarefas — semanal, quinta, 18:00 — php com argumento = caminho absoluto deste arquivo.
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
