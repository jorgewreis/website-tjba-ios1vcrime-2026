<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/error_detail_defaults.php';

$pageTitle = 'Erro 401 — Primeira Vara Crime de Ilhéus';
$pageHeading = '401 — Acesso não autorizado';
$description = 'A página que você está tentando acessar requer autenticação.';
$error_code = '401';

$error_messages = include __DIR__ . '/error_messages.php';
$info_text = $error_messages[$error_code] ?? '';

$icons = include __DIR__ . '/error_icons.php';
$iconCfg = $icons[$error_code] ?? [];
$icon_path = $iconCfg['path'] ?? '/assets/icons/Alert-Triangle--Streamline-Ultimate.png';
$icon_alt = $iconCfg['alt'] ?? 'Erro';
$variant = $iconCfg['variant'] ?? 'warning';

$details = error_page_merge_details($error_code, $details_override ?? []);

include __DIR__ . '/_error_head.php';
include __DIR__ . '/_error_body.php';
include __DIR__ . '/_error_footer.php';
