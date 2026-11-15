CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario CHAR(30) NOT NULL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    cpf VARCHAR(15) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    token_acesso VARCHAR(100) NULL,
    token_expira DATETIME NULL,
    ultimo_acesso DATETIME NULL,
    cadastro_ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_perfil (
    id_usu_perfil CHAR(30) NOT NULL PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    perfil VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_usuario_perfil (usuario, perfil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recuperacao_senha (
    id_recuperacao CHAR(30) NOT NULL PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NOT NULL,
    data_solicitacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuario (usuario),
    KEY idx_token (token),
    KEY idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico_senha (
    id_historico CHAR(30) NOT NULL PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    data_alteracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuario (usuario),
    KEY idx_data (data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_rate_limits (
    id_rate_limit CHAR(30) NOT NULL PRIMARY KEY,
    scope_type VARCHAR(20) NOT NULL,
    scope_value VARCHAR(191) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    lock_level INT NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scope (scope_type, scope_value),
    KEY idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
