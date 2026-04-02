<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

Security::requireAuthentication();

$iframeSrc = '/views/public/alterar-senha-form.php';
$pageTitle  = 'Alterar senha - Primeira Vara Crime de Ilhéus';

include __DIR__ . '/_layout_login.php';
