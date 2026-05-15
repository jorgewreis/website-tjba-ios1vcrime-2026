-- =============================================================================
-- ASSUNTOS PROCESSUAIS
-- Schema base para catálogo de assuntos e vínculo com processos.
-- Convenções:
--   - MySQL / InnoDB / utf8mb4 / utf8mb4_unicode_ci
--   - IDs semânticos com 30 caracteres: prefixo de 3 letras + hífen + 26 chars
-- Referências:
--   - docs/00-foundation/id-padrao-banco-dados.md
--   - database/migrations/20260515_assuntos_processuais.sql
-- =============================================================================

-- Prefixos adotados neste módulo:
--   ASU = assuntos
--   PAS = processo_assuntos

CREATE TABLE IF NOT EXISTS `assuntos` (
    `id` CHAR(30) NOT NULL COMMENT 'Formato ASU- + 26 chars (regra padrão 30 chars)',
    `codigo` VARCHAR(20) NOT NULL COMMENT 'Código CNJ/Tabela unificada de assuntos processuais',
    `descricao` VARCHAR(500) NOT NULL COMMENT 'Descrição oficial do assunto',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_assuntos_codigo` (`codigo`),
    KEY `ix_assuntos_descricao` (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de assuntos processuais.';

CREATE TABLE IF NOT EXISTS `processo_assuntos` (
    `id` CHAR(30) NOT NULL COMMENT 'Formato PAS- + 26 chars (regra padrão 30 chars)',
    `processo_id` CHAR(30) NOT NULL COMMENT 'FK para processos.id',
    `assunto_id` CHAR(30) NOT NULL COMMENT 'FK para assuntos.id',
    `origem` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual|datajud',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_processo_assuntos_processo_assunto` (`processo_id`, `assunto_id`),
    KEY `ix_processo_assuntos_processo_id` (`processo_id`),
    KEY `ix_processo_assuntos_assunto_id` (`assunto_id`),
    KEY `ix_processo_assuntos_origem` (`origem`),
    CONSTRAINT `fk_processo_assuntos_processo_id`
        FOREIGN KEY (`processo_id`) REFERENCES `processos` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_processo_assuntos_assunto_id`
        FOREIGN KEY (`assunto_id`) REFERENCES `assuntos` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vínculo N:N entre processos e assuntos processuais.';
