-- Colunas aditivas. Sem backfill. Aplicar antes do código, somente após os gates.
-- Conexão dedicada, schema selecionado, sem --force e sem DDL paralelo.
-- Em erro fechar a conexão, inclusive para liberar locks.
SET @odj_previous_mdl = @@SESSION.lock_wait_timeout;
SET SESSION lock_wait_timeout = 5;
SET @odj_lock_name = CONCAT('processos_orgao_datajud:', LEFT(SHA2(DATABASE(), 256), 40));
SET @odj_lock = IF(@odj_lock_name IS NULL, 0, GET_LOCK(@odj_lock_name, 5));
SELECT COUNT(*) INTO @odj_table FROM information_schema.TABLES
  WHERE @odj_lock = 1 AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
    AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
    AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB';

-- A definição ausente pode ser adicionada. Definição existente incompatível
-- bloqueia qualquer ALTER e não é alterada por esta migração.
SELECT COUNT(*), COALESCE(SUM(COLUMN_NAME = 'vara_nome_datajud'),0),
       COALESCE(SUM(COLUMN_NAME = 'orgao_municipio_ibge_datajud'),0)
INTO @odj_count, @odj_has_vara, @odj_has_ibge
FROM information_schema.COLUMNS
WHERE @odj_table = 1 AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND COLUMN_NAME IN ('vara_nome_datajud','orgao_municipio_ibge_datajud');
SELECT COUNT(*) INTO @odj_before_ok
FROM information_schema.COLUMNS
WHERE @odj_table = 1 AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND ((COLUMN_NAME = 'vara_nome_datajud' AND COLUMN_TYPE = 'varchar(180)' AND CHARACTER_SET_NAME = 'utf8mb4')
    OR (COLUMN_NAME = 'orgao_municipio_ibge_datajud' AND COLUMN_TYPE = 'char(7)' AND CHARACTER_SET_NAME = 'ascii'))
  AND IS_NULLABLE = 'YES' AND EXTRA = ''
  AND (COLUMN_DEFAULT IS NULL
    OR (LOCATE('MariaDB', VERSION()) > 0 AND CAST(COLUMN_DEFAULT AS BINARY) = CAST('NULL' AS BINARY)));

SET @odj_add = CONCAT_WS(', ',
    IF(@odj_has_vara = 0, 'ADD COLUMN vara_nome_datajud VARCHAR(180) CHARACTER SET utf8mb4 NULL DEFAULT NULL', NULL),
    IF(@odj_has_ibge = 0, 'ADD COLUMN orgao_municipio_ibge_datajud CHAR(7) CHARACTER SET ascii NULL DEFAULT NULL', NULL));
SET @odj_sql = IF(@odj_table = 1 AND @odj_count = @odj_before_ok AND @odj_count < 2,
    CONCAT('ALTER TABLE processos ', @odj_add, ', ALGORITHM=INSTANT'), 'DO 0');
PREPARE orgao_datajud_stmt FROM @odj_sql;
EXECUTE orgao_datajud_stmt;
DEALLOCATE PREPARE orgao_datajud_stmt;

SELECT COUNT(*) INTO @odj_after_ok
FROM information_schema.COLUMNS
WHERE @odj_table = 1 AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND ((COLUMN_NAME = 'vara_nome_datajud' AND COLUMN_TYPE = 'varchar(180)' AND CHARACTER_SET_NAME = 'utf8mb4')
    OR (COLUMN_NAME = 'orgao_municipio_ibge_datajud' AND COLUMN_TYPE = 'char(7)' AND CHARACTER_SET_NAME = 'ascii'))
  AND IS_NULLABLE = 'YES' AND EXTRA = ''
  AND (COLUMN_DEFAULT IS NULL
    OR (LOCATE('MariaDB', VERSION()) > 0 AND CAST(COLUMN_DEFAULT AS BINARY) = CAST('NULL' AS BINARY)));
SET @odj_released = IF(@odj_lock = 1, RELEASE_LOCK(@odj_lock_name), 0);
SET SESSION lock_wait_timeout = @odj_previous_mdl;
SET @odj_sql = CASE
    WHEN COALESCE(@odj_lock, 0) <> 1 THEN 'SELECT ORGAO_DATAJUD_MIGRATION_LOCK_NOT_ACQUIRED'
    WHEN COALESCE(@odj_released, 0) <> 1 THEN 'SELECT ORGAO_DATAJUD_MIGRATION_LOCK_NOT_RELEASED'
    WHEN @odj_table <> 1 THEN 'SELECT ORGAO_DATAJUD_PROCESSOS_INNODB_REQUIRED'
    WHEN @odj_count <> @odj_before_ok OR @odj_after_ok <> 2 THEN 'SELECT ORGAO_DATAJUD_INCOMPATIBLE_COLUMNS'
    ELSE 'DO 0'
END;
PREPARE orgao_datajud_stmt FROM @odj_sql;
EXECUTE orgao_datajud_stmt;
DEALLOCATE PREPARE orgao_datajud_stmt;
