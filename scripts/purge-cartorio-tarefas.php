<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/Conection.php';

$db = Conection::getInstance()->getConnection();
$deleted = $db->exec("DELETE FROM cartorio_tarefas WHERE status = 'finalizada' AND finalizado_em IS NOT NULL AND finalizado_em <= DATE_SUB(NOW(), INTERVAL 1 YEAR)");
fwrite(STDOUT, "Tarefas finalizadas removidas após um ano: " . (int) $deleted . "\n");
