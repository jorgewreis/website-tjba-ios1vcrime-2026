-- Aplicação somente após backup e restauração isolada confirmados.
-- Usar DDL preparado: MySQL não aceita ADD COLUMN IF NOT EXISTS.
SET @ficha_vara_sql := IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND COLUMN_NAME = 'vara_nome'),
    'SELECT 1',
    'ALTER TABLE processos ADD COLUMN vara_nome VARCHAR(180) NULL DEFAULT NULL'
);
PREPARE ficha_vara_stmt FROM @ficha_vara_sql;
EXECUTE ficha_vara_stmt;
DEALLOCATE PREPARE ficha_vara_stmt;
