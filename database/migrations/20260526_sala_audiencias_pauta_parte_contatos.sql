-- ============================================================================
-- Migração: contatos da parte da pauta de audiências
-- Data: 2026-05-26
-- Descrição: adiciona a coluna usada para contatos específicos da audiência.
-- ============================================================================

USE common;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'sala_audiencias_pauta_parte'
      AND COLUMN_NAME = 'contatos'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE sala_audiencias_pauta_parte ADD COLUMN contatos VARCHAR(200) NULL AFTER id_documento_cumprido',
    'SELECT ''Coluna sala_audiencias_pauta_parte.contatos já existe.'' AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

