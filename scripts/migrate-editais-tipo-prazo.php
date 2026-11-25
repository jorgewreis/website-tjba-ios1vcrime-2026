<?php
/**
 * Migração: adicionar tipo_edital e prazo_dias à tabela editais.
 * Executar uma vez a partir da raiz: php scripts/migrate-editais-tipo-prazo.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}


$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$alter = [
    "ALTER TABLE `editais` ADD COLUMN `tipo_edital` VARCHAR(120) DEFAULT NULL COMMENT 'Ex.: Citação, Intimação, Notificação' AFTER `status`",
    "ALTER TABLE `editais` ADD COLUMN `prazo_dias` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Prazo em dias a partir da data de publicação' AFTER `tipo_edital`",
];

foreach ($alter as $sql) {
    try {
        $connection->exec($sql);
        echo "OK: coluna adicionada.\n";
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Coluna já existe, ignorando.\n";
        } else {
            echo "Erro: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

echo "Migração editais (tipo_edital, prazo_dias) concluída.\n";
