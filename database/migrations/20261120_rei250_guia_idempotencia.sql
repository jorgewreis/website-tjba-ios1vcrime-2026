-- Não associa por FK à guia: excluir uma guia não libera uma chave ainda válida.
-- A expiração lógica é de 30 dias; esta migração não instala limpeza física.
CREATE TABLE IF NOT EXISTS guia_criacao_idempotencia (
    ator_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    chave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    hash_versao TINYINT UNSIGNED NOT NULL,
    guia_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    criado_utc DATETIME(6) NOT NULL,
    PRIMARY KEY (ator_hash, chave),
    KEY idx_guia_idempotencia_expira (criado_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
