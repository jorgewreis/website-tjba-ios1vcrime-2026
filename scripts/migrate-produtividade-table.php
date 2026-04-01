<?php
/**
 * Migração: criar tabela Produtividade.
 * Executar uma vez a partir da raiz: php scripts/migrate-produtividade-table.php
 */

require_once __DIR__ . '/../app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS `Produtividade` (
    `IdProd` VARCHAR(50) NOT NULL COMMENT 'Identificador único do registro de produtividade',
    `ProcProd` VARCHAR(50) DEFAULT NULL COMMENT 'Número do processo relacionado',
    `DescProd` VARCHAR(255) NOT NULL COMMENT 'Descrição da atividade realizada',
    `PontosProd` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Pontuação da atividade',
    `DataProd` DATETIME NOT NULL COMMENT 'Data e hora da realização da atividade',
    `UserProd` VARCHAR(100) NOT NULL COMMENT 'Usuário que realizou a atividade',
    `Visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se o registro é visível (1=sim, 0=não)',
    PRIMARY KEY (`IdProd`),
    INDEX `idx_produtividade_processo` (`ProcProd`),
    INDEX `idx_produtividade_usuario` (`UserProd`),
    INDEX `idx_produtividade_data` (`DataProd`),
    INDEX `idx_produtividade_visible` (`Visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de registros de produtividade dos usuários';
";

try {
    $connection->exec($sql);
    echo "Migração Produtividade: tabela criada com sucesso.\n";
} catch (Exception $e) {
    echo "Erro ao criar tabela Produtividade: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Migração concluída.\n";