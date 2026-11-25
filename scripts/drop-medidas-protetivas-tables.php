<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Remove tabelas do submódulo Medidas Protetivas (cartório) e a regra ALLOCATE-MEDIDASPROTETIVAS.
 *
 * Uso: php scripts/drop-medidas-protetivas-tables.php
 */

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$pdo = Conection::getInstance()->getConnection();

$tables = [
    'medidas_protetivas_subtarefas',
    'medidas_protetivas_alocados',
    'medidas_protetivas',
];

foreach ($tables as $table) {
    $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    echo "Tabela removida (se existia): {$table}\n";
}

$stmt = $pdo->prepare('DELETE FROM `alocacoes_regras` WHERE `regra` = :regra');
$stmt->execute(['regra' => 'ALLOCATE-MEDIDASPROTETIVAS']);
echo 'Regras ALLOCATE-MEDIDASPROTETIVAS removidas: ' . $stmt->rowCount() . "\n";

echo "Concluído.\n";
