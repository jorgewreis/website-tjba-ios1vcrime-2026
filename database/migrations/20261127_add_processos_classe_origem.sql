-- Migration aditiva para MySQL 8.4/Percona e MariaDB 10.4.
-- Rodar em conexão dedicada, schema já selecionado, sem --force e sem outro DDL paralelo.
-- Em caso de erro feche a conexão. Backup privado e restore isolado precedem aplicação.
-- NULL conserva a origem legada desconhecida. Não há backfill nem reconstrução da tabela.

SET @rei345_lock_name = CONCAT('rei345_classe_origem:', LEFT(SHA2(DATABASE(), 256), 40));
SET @rei345_lock = IF(@rei345_lock_name IS NULL, 0, GET_LOCK(@rei345_lock_name, 5));
SET @rei345_table = 0;
SET @rei345_column = 0;
SET @rei345_compatible = 0;

SELECT COUNT(*) INTO @rei345_table
FROM information_schema.TABLES
WHERE @rei345_lock = 1
  AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB';

SELECT COUNT(*) INTO @rei345_column
FROM information_schema.COLUMNS
WHERE @rei345_table = 1
  AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND COLUMN_NAME = 'classe_origem';

SET @rei345_sql = IF(@rei345_table = 1 AND @rei345_column = 0,
    'ALTER TABLE `processos` ADD COLUMN `classe_origem` ENUM(''manual'',''datajud'') NULL DEFAULT NULL, ALGORITHM=INSTANT',
    'DO 0');
PREPARE rei345_step FROM @rei345_sql;
EXECUTE rei345_step;
DEALLOCATE PREPARE rei345_step;

-- Verifica também reaplicações. O catálogo MariaDB pode representar DEFAULT NULL
-- por NULL textual sem aspas, distinto de um literal string que vem com aspas.
SELECT COUNT(*) INTO @rei345_compatible
FROM information_schema.COLUMNS
WHERE @rei345_table = 1
  AND CAST(TABLE_SCHEMA AS BINARY) = CAST(DATABASE() AS BINARY)
  AND CAST(TABLE_NAME AS BINARY) = CAST('processos' AS BINARY)
  AND COLUMN_NAME = 'classe_origem'
  AND CAST(COLUMN_TYPE AS BINARY) = CAST('enum(''manual'',''datajud'')' AS BINARY)
  AND IS_NULLABLE = 'YES' AND EXTRA = ''
  AND (COLUMN_DEFAULT IS NULL
    OR (LOCATE('MariaDB', VERSION()) > 0 AND CAST(COLUMN_DEFAULT AS BINARY) = CAST('NULL' AS BINARY)));

SET @rei345_released = IF(@rei345_lock = 1, RELEASE_LOCK(@rei345_lock_name), 0);
-- Identificadores deliberadamente inexistentes produzem erro SQL explícito
-- sem usar SIGNAL preparado (não suportado nos dois motores).
SET @rei345_sql = CASE
    WHEN COALESCE(@rei345_lock, 0) <> 1 THEN 'SELECT REI345_MIGRATION_LOCK_NOT_ACQUIRED'
    WHEN COALESCE(@rei345_released, 0) <> 1 THEN 'SELECT REI345_MIGRATION_LOCK_NOT_RELEASED'
    WHEN @rei345_table <> 1 THEN 'SELECT REI345_PROCESSOS_INNODB_TABLE_REQUIRED'
    WHEN @rei345_compatible <> 1 THEN 'SELECT REI345_INCOMPATIBLE_CLASSE_ORIGEM'
    ELSE 'DO 0'
END;
PREPARE rei345_step FROM @rei345_sql;
EXECUTE rei345_step;
DEALLOCATE PREPARE rei345_step;
