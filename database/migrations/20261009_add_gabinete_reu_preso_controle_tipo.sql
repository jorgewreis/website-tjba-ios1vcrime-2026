ALTER TABLE `gabinete_reu_preso`
    ADD COLUMN `controle_tipo` ENUM('principal', 'substituto') NOT NULL DEFAULT 'principal' AFTER `proxima_revisao`,
    ADD INDEX `idx_gabinete_reu_preso_controle_status` (`controle_tipo`, `status`);
