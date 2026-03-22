<?php
/**
 * Migração: estrutura de decurso em editais + tabela de feriados.
 * Executar uma vez na raiz: php scripts/migrate-editais-decurso-feriados.php
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$commands = [
    "ALTER TABLE `editais` ADD COLUMN `data_inicio_contagem` DATE DEFAULT NULL COMMENT 'Primeiro dia útil de início da contagem do prazo' AFTER `data_publicacao`",
    "ALTER TABLE `editais` ADD COLUMN `data_decurso_prazo` DATE DEFAULT NULL COMMENT 'Data final do decurso do prazo (ajustada para dia útil)' AFTER `data_inicio_contagem`",
    "CREATE TABLE IF NOT EXISTS `feriados` (
        `id` CHAR(30) NOT NULL COMMENT 'Formato FER- + 26 chars' PRIMARY KEY,
        `tipo_feriado` VARCHAR(30) NOT NULL COMMENT 'estadual, municipal, federal, religioso, compensacao, recesso',
        `data_inicio` DATE NOT NULL,
        `data_fim` DATE NOT NULL,
        `regra_data` VARCHAR(15) NOT NULL COMMENT 'fixa, unica, movel',
        `descricao` VARCHAR(255) DEFAULT NULL,
        `ativo` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_feriados_intervalo` (`data_inicio`, `data_fim`),
        INDEX `idx_feriados_tipo` (`tipo_feriado`),
        INDEX `idx_feriados_regra` (`regra_data`),
        INDEX `idx_feriados_ativo` (`ativo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($commands as $sql) {
    try {
        $connection->exec($sql);
        echo "OK\n";
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Coluna já existe, ignorando.\n";
            continue;
        }
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração editais/decurso/feriados concluída.\n";

