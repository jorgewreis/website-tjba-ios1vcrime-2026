<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

// Evita HTML em cache com token CSRF antigo após logout (login falhava até F5).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

if (Security::isAuthenticated()) {
    header('Location: /home.php');
    exit;
}

$iframeSrc = '/views/public/login-form.php';
$pageTitle  = 'Primeira Vara Crime de Ilhéus - Sistema de Gestão';

include __DIR__ . '/_layout_login.php';
