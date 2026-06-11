-- Adds the audience-specific contact field used by the pauta partes editor
-- and by AudienciaResumoBuilder.
--
-- Idempotent for MySQL/MariaDB versions that do not support
-- ALTER TABLE ... ADD COLUMN IF NOT EXISTS.

SET @column_exists := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'sala_audiencias_pauta_parte'
       AND COLUMN_NAME = 'contatos'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL AFTER `id_documento_cumprido`',
    'SELECT ''Column sala_audiencias_pauta_parte.contatos already exists'' AS status'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
