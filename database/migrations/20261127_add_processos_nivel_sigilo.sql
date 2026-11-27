-- Aplicação manual em conexão dedicada após backup e restauração verificados.
-- NULL conserva os dados legados. A coluna sigiloso continua controlando o acesso.
-- O padrão exige INSTANT e falha se o motor não conseguir adicionar o CHECK sem rebuild.
-- Somente após avaliar janela e impacto, o operador pode definir
-- @ficha_nivel_permitir_copia = 1 na MESMA conexão, antes deste arquivo.
-- A modalidade COPY bloqueia escritas na tabela durante a cópia (LOCK=SHARED).
SET @ficha_nivel_lock_anterior := @@SESSION.lock_wait_timeout;
SET SESSION lock_wait_timeout = 5;
SET @ficha_nivel_tem_coluna := EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND COLUMN_NAME = 'nivel_sigilo'
);
SET @ficha_nivel_tem_check := EXISTS(
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS tc
    JOIN information_schema.CHECK_CONSTRAINTS cc
      ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
    WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = 'processos' AND tc.CONSTRAINT_TYPE = 'CHECK'
      AND REGEXP_REPLACE(LOWER(cc.CHECK_CLAUSE), '[[:space:]()`]+', '') = 'nivel_sigilobetween1and5'
);
SET @ficha_nivel_sql := IF(
    @ficha_nivel_tem_coluna AND @ficha_nivel_tem_check, 'SELECT 1',
    CONCAT('ALTER TABLE processos ',
        IF(@ficha_nivel_tem_coluna, '', 'ADD COLUMN nivel_sigilo TINYINT UNSIGNED NULL DEFAULT NULL, '),
        'ADD CONSTRAINT ck_processos_nivel_sigilo_1a5 CHECK (nivel_sigilo BETWEEN 1 AND 5), ',
        IF(COALESCE(@ficha_nivel_permitir_copia, 0) = 1, 'ALGORITHM=COPY, LOCK=SHARED', 'ALGORITHM=INSTANT'))
);
PREPARE ficha_nivel_stmt FROM @ficha_nivel_sql;
EXECUTE ficha_nivel_stmt;
DEALLOCATE PREPARE ficha_nivel_stmt;
SET SESSION lock_wait_timeout = @ficha_nivel_lock_anterior;
