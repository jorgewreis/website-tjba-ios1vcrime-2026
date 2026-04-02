<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

$token     = trim((string) ($_GET['token'] ?? ''));
$iframeSrc = '/views/public/redefinir-senha-form.php' . ($token !== '' ? '?token=' . urlencode($token) : '');
$pageTitle  = 'Redefinir senha - Primeira Vara Crime de Ilhéus';

include __DIR__ . '/_layout_login.php';
