CREATE TABLE IF NOT EXISTS login_rate_limits (
    id_rate_limit CHAR(30) NOT NULL PRIMARY KEY,
    scope_type VARCHAR(20) NOT NULL,
    scope_value VARCHAR(191) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    lock_level INT NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scope (scope_type, scope_value),
    KEY idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
