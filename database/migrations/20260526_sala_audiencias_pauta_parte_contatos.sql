-- Adiciona contatos especificos da parte na pauta de audiencia.
-- Idempotente para permitir reaplicacao segura em bases ja migradas.

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sala_audiencias_pauta_parte'
      AND COLUMN_NAME = 'contatos'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL AFTER `id_documento_cumprido`',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
