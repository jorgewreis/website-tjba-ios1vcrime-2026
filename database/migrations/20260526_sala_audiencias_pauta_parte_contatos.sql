-- ============================================================================
-- Migration: 20260526_sala_audiencias_pauta_parte_contatos.sql
-- Módulo: Sala de Audiências
-- Descrição: adiciona contatos específicos da parte na pauta de audiência.
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
    'ALTER TABLE `sala_audiencias_pauta_parte` ADD COLUMN `contatos` VARCHAR(200) NULL DEFAULT NULL AFTER `id_documento_cumprido`',
    'SELECT ''Coluna sala_audiencias_pauta_parte.contatos já existe'' AS info'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
