<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/error_detail_defaults.php';

$pageTitle = 'Erro 503 — Primeira Vara Crime de Ilhéus';
$pageHeading = '503 — Manutenção programada';
$description = 'O sistema encontra-se em manutenção programada. Estamos trabalhando para restabelecer o serviço o quanto antes.';
$error_code = '503';

$error_messages = include __DIR__ . '/error_messages.php';
$info_text = $error_messages[$error_code] ?? '';

$icons = include __DIR__ . '/error_icons.php';
$iconCfg = $icons[$error_code] ?? [];
$icon_path = $iconCfg['path'] ?? '/assets/icons/Maintenance-Wrench--Streamline-Ultimate.png';
$icon_alt = $iconCfg['alt'] ?? 'Erro';
$variant = $iconCfg['variant'] ?? 'maintenance';

$details_override = [];
$until = getenv('MAINTENANCE_UNTIL');
if ($until !== false && $until !== '') {
    $details_override['Previsão'] = $until;
}
$details = error_page_merge_details($error_code, $details_override);

include __DIR__ . '/_error_head.php';
include __DIR__ . '/_error_body.php';
include __DIR__ . '/_error_footer.php';
