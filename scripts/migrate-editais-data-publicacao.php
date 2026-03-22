<?php
/**
 * Migração: adicionar data_publicacao à tabela editais.
 * Executar uma vez a partir da raiz: php scripts/migrate-editais-data-publicacao.php
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sql = "ALTER TABLE `editais` ADD COLUMN `data_publicacao` DATE DEFAULT NULL COMMENT 'Data da publicação do edital (YYYY-MM-DD)' AFTER `prazo_dias`";

try {
    $connection->exec($sql);
    echo "OK: coluna data_publicacao adicionada em editais.\n";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Coluna data_publicacao já existe, ignorando.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração editais (data_publicacao) concluída.\n";

