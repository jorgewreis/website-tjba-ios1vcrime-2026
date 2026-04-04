<?php
$base = dirname(__DIR__);
$r = require $base . '/app/config/cartorio-produtividade-registry.php';
unset($r['cartorio-interno.estorno-saldo']);
file_put_contents($base . '/app/config/cartorio-produtividade-registry.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($r, true) . ";\n");
echo count($r);
