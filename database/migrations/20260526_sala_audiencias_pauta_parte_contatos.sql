-- ============================================================================
-- Migration: sala_audiencias_pauta_parte.contatos
-- Date: 2026-05-26
-- Description:
--   Adds the optional contact field used by audience-part create/edit flows and
--   by the audience summary TXT/PDF builder.
--
-- Safe to run more than once.
-- ============================================================================

SET @database_name = DATABASE();

SET @column_exists = (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @database_name
       AND TABLE_NAME = 'sala_audiencias_pauta_parte'
       AND COLUMN_NAME = 'contatos'
);

SET @migration_sql = IF(
    @column_exists = 0,
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL AFTER `id_documento_cumprido`',
    'SELECT 1'
);

PREPARE migration_stmt FROM @migration_sql;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
