<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/**
 * Remove tabelas de endereços de partes e instituições públicas (cadastro descontinuado).
 *
 * Uso: php scripts/drop-enderecos-partes-instituicoes-tables.php
 */

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$pdo = Conection::getInstance()->getConnection();

$tables = [
    'partes_enderecos',
    'instituicoes_enderecos',
];

foreach ($tables as $table) {
    $pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', $table));
    echo "Tabela removida (se existia): {$table}\n";
}

echo "Concluído.\n";
