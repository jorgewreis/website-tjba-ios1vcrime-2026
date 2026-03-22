<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/error_detail_defaults.php';

$pageTitle = 'Erro 403 — Primeira Vara Crime de Ilhéus';
$pageHeading = '403 — Acesso negado';
$description = 'Seu perfil não tem permissão para acessar este recurso. Solicite autorização à chefia se necessário.';
$error_code = '403';

$error_messages = include __DIR__ . '/error_messages.php';
$info_text = $error_messages[$error_code] ?? '';

$icons = include __DIR__ . '/error_icons.php';
$iconCfg = $icons[$error_code] ?? [];
$icon_path = $iconCfg['path'] ?? '/assets/icons/Alert-Triangle--Streamline-Ultimate.png';
$icon_alt = $iconCfg['alt'] ?? 'Erro';
$variant = $iconCfg['variant'] ?? 'danger';

$details = error_page_merge_details($error_code, $details_override ?? []);

include __DIR__ . '/_error_head.php';
include __DIR__ . '/_error_body.php';
include __DIR__ . '/_error_footer.php';
