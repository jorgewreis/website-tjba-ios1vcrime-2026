<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/error_detail_defaults.php';

$pageTitle = 'Erro 404 — Primeira Vara Crime de Ilhéus';
$pageHeading = '404 — Página não encontrada';
$description = 'Desculpe, a página que você está procurando não existe ou foi movida.';
$error_code = '404';

$error_messages = include __DIR__ . '/error_messages.php';
$info_text = $error_messages[$error_code] ?? '';

$icons = include __DIR__ . '/error_icons.php';
$iconCfg = $icons[$error_code] ?? [];
$icon_path = $iconCfg['path'] ?? '/assets/icons/Link-Broken--Streamline-Ultimate.png';
$icon_alt = $iconCfg['alt'] ?? 'Erro';
$variant = $iconCfg['variant'] ?? 'info';

$details = error_page_merge_details($error_code, $details_override ?? []);

include __DIR__ . '/_error_head.php';
include __DIR__ . '/_error_body.php';
include __DIR__ . '/_error_footer.php';
