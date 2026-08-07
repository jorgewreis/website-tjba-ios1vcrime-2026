-- Registros simples de encaminhamento ao CEJUSC.
CREATE TABLE IF NOT EXISTS `cejusc_registros` (
    `id` CHAR(30) NOT NULL COMMENT 'Formato CJR- + 26 chars' PRIMARY KEY,
    `numero_processo` VARCHAR(40) NOT NULL,
    `nome_parte` VARCHAR(180) NOT NULL,
    `data_envio_cejusc` DATE NOT NULL,
    `criado_por` CHAR(30) DEFAULT NULL,
    `atualizado_por` CHAR(30) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_cejusc_registros_processo` (`numero_processo`),
    KEY `idx_cejusc_registros_parte` (`nome_parte`),
    KEY `idx_cejusc_registros_data_envio` (`data_envio_cejusc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
