-- Adiciona contatos locais da parte na pauta de audiencia.
-- Idempotente: pode ser reaplicada sem falhar quando a coluna ja existir.

SET @schema_name := DATABASE();
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'sala_audiencias_pauta_parte'
      AND COLUMN_NAME = 'contatos'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL AFTER `id_documento_cumprido`',
    'SELECT ''Coluna sala_audiencias_pauta_parte.contatos ja existe.'' AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
