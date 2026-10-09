<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

function gabineteReuPresoColumnExists(PDO $connection, string $column): bool
{
    $st = $connection->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $st->execute([':table' => 'gabinete_reu_preso', ':column' => $column]);
    return (int) $st->fetchColumn() > 0;
}

function gabineteReuPresoIndexExists(PDO $connection, string $index): bool
{
    $st = $connection->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index'
    );
    $st->execute([':table' => 'gabinete_reu_preso', ':index' => $index]);
    return (int) $st->fetchColumn() > 0;
}

try {
    if (!gabineteReuPresoColumnExists($connection, 'controle_tipo')) {
        $connection->exec(
            "ALTER TABLE `gabinete_reu_preso`
             ADD COLUMN `controle_tipo` ENUM('principal', 'substituto') NOT NULL DEFAULT 'principal' AFTER `proxima_revisao`"
        );
        echo "Coluna controle_tipo adicionada; registros existentes ficam no controle principal.\n";
    }

    if (!gabineteReuPresoIndexExists($connection, 'idx_gabinete_reu_preso_controle_status')) {
        $connection->exec(
            'ALTER TABLE `gabinete_reu_preso`
             ADD INDEX `idx_gabinete_reu_preso_controle_status` (`controle_tipo`, `status`)'
        );
        echo "Índice idx_gabinete_reu_preso_controle_status criado.\n";
    }

    echo "Migração do controle de réus presos concluída.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro ao migrar controle de réus presos: ' . $exception->getMessage() . "\n");
    exit(1);
}
