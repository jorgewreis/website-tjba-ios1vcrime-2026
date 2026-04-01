<?php
/**
 * Migração: adicionar identificador_arquivo na tabela sala_audiencias_ata.
 * Executar uma vez a partir da raiz: php scripts/migrate-sala-audiencias-ata-identificador-arquivo.php
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sql = "ALTER TABLE `sala_audiencias_ata` ADD COLUMN `identificador_arquivo` VARCHAR(120) NULL COMMENT 'Identificador único lógico do arquivo da ata para rastreio' AFTER `arquivo_relativo`";

try {
    $connection->exec($sql);
    echo "OK: coluna identificador_arquivo adicionada em sala_audiencias_ata.\n";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Coluna identificador_arquivo já existe, ignorando.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração sala_audiencias_ata (identificador_arquivo) concluída.\n";
