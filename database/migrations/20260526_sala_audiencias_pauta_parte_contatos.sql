-- Adiciona contatos locais da parte da audiência.
-- Idempotente para permitir reaplicação em ambientes já corrigidos manualmente.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sala_audiencias_pauta_parte'
      AND COLUMN_NAME = 'contatos'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL AFTER `id_documento_cumprido`',
    'SELECT ''sala_audiencias_pauta_parte.contatos already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
