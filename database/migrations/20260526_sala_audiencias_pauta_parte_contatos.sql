-- Adiciona contatos específicos da parte na pauta de audiências.
-- Idempotente para permitir reaplicação segura em bases já atualizadas.

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
    'SELECT ''Coluna sala_audiencias_pauta_parte.contatos ja existe'' AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
