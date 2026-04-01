<?php
/**
 * Migração: adiciona processo_id em gabinete_reu_preso_movimentacao e preenche legado.
 * Executar uma vez na raiz: php scripts/migrate-grp-movimentacao-processo-id.php
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

function columnExists(PDO $connection, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column';
    $st = $connection->prepare($sql);
    $st->bindValue(':table', $table);
    $st->bindValue(':column', $column);
    $st->execute();
    return (int) $st->fetchColumn() > 0;
}

function indexExists(PDO $connection, string $table, string $index): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index';
    $st = $connection->prepare($sql);
    $st->bindValue(':table', $table);
    $st->bindValue(':index', $index);
    $st->execute();
    return (int) $st->fetchColumn() > 0;
}

try {
    if (!columnExists($connection, 'gabinete_reu_preso_movimentacao', 'processo_id')) {
        $connection->exec(
            "ALTER TABLE `gabinete_reu_preso_movimentacao`
             ADD COLUMN `processo_id` VARCHAR(30) NULL AFTER `reu_preso_processo_id`"
        );
        echo "OK: coluna processo_id adicionada.\n";
    } else {
        echo "Coluna processo_id já existe, ignorando.\n";
    }

    $connection->exec(
        "UPDATE `gabinete_reu_preso_movimentacao` m
         INNER JOIN `gabinete_reu_preso_processo` gpp ON gpp.`id` = m.`reu_preso_processo_id`
         SET m.`processo_id` = gpp.`processo_id`
         WHERE m.`processo_id` IS NULL OR m.`processo_id` = ''"
    );
    echo "OK: processo_id legado preenchido a partir de reu_preso_processo_id.\n";

    if (!indexExists($connection, 'gabinete_reu_preso_movimentacao', 'idx_grp_mov_processo_data')) {
        $connection->exec(
            "ALTER TABLE `gabinete_reu_preso_movimentacao`
             ADD INDEX `idx_grp_mov_processo_data` (`processo_id`, `data_movimentacao`)"
        );
        echo "OK: índice idx_grp_mov_processo_data criado.\n";
    } else {
        echo "Índice idx_grp_mov_processo_data já existe, ignorando.\n";
    }

    if (!indexExists($connection, 'gabinete_reu_preso_movimentacao', 'idx_grp_mov_processo_tipo_data')) {
        $connection->exec(
            "ALTER TABLE `gabinete_reu_preso_movimentacao`
             ADD INDEX `idx_grp_mov_processo_tipo_data` (`processo_id`, `tipo_codigo`, `data_movimentacao`)"
        );
        echo "OK: índice idx_grp_mov_processo_tipo_data criado.\n";
    } else {
        echo "Índice idx_grp_mov_processo_tipo_data já existe, ignorando.\n";
    }

    echo "Migração GRP movimentação (processo_id) concluída.\n";
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
