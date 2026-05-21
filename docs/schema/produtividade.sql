-- =============================================================================
-- PRODUTIVIDADE INTERNA
-- Schema base da Fase 1 do módulo de produtividade.
-- Convenções:
--   - MySQL / InnoDB / utf8mb4 / utf8mb4_unicode_ci
--   - IDs semânticos com 30 caracteres: prefixo de 3 letras + hífen + 26 chars
--   - Compatível com geração por trigger e futura geração via PHP com IdGenerator
-- Referências:
--   - docs/00-foundation/id-padrao-banco-dados.md
--   - docs/10-architecture/database-schema-base.md
-- =============================================================================

-- Prefixos adotados neste módulo:
--   PRG = produtividade_regras
--   PLC = produtividade_lancamentos
--   PLG = produtividade_log
--   PBG = produtividade_badges
--   PBC = produtividade_badges_concedidos

CREATE TABLE IF NOT EXISTS produtividade_regras (
    id                    CHAR(30)     NOT NULL COMMENT 'Formato PRG- + 26 chars (regra padrão 30 chars)',
    tipo_ato              VARCHAR(120) NOT NULL COMMENT 'Ex.: concluir tarefa, movimentar processo, cadastrar ata',
    tipo_documento        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Ex.: oficio, audiencia, guia, edital',
    sistema_modulo        VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Ex.: cartorio, gabinete, audiencias, manual',
    pontos_por_ato        DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Pontuação base por unidade realizada',
    pontos_multiplicador  DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Multiplicador adicional configurável',
    ativo                 TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=ativa, 0=inativa',
    observacoes           TEXT         NULL COMMENT 'Observações internas sobre a regra',
    criado_por            CHAR(30)     NULL COMMENT 'FK para usuarios.id_usuario',
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_produtividade_regras_tipo_ato (tipo_ato),
    KEY ix_produtividade_regras_modulo (sistema_modulo),
    KEY ix_produtividade_regras_ativo (ativo),
    CONSTRAINT fk_produtividade_regras_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo configurável das regras de pontuação do módulo de produtividade.';

CREATE TABLE IF NOT EXISTS produtividade_lancamentos (
    id                    CHAR(30)     NOT NULL COMMENT 'Formato PLC- + 26 chars (regra padrão 30 chars)',
    usuario_id            CHAR(30)     NOT NULL COMMENT 'FK para usuarios.id_usuario',
    regra_id              CHAR(30)     NULL COMMENT 'FK para produtividade_regras.id; pode ser null em cenários legados ou excepcionais',
    tipo_lancamento       ENUM('automatico', 'manual') NOT NULL DEFAULT 'manual',
    status                ENUM('ativo', 'anulado') NOT NULL DEFAULT 'ativo',
    quantidade            INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantidade de atos/unidades realizados',
    pontos_calculados     DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Pontuação final registrada no lançamento',
    contexto_hash         CHAR(64)     NULL COMMENT 'SHA-256 para prevenção de duplicidade do mesmo contexto',
    referencia_item_id    VARCHAR(100) NULL COMMENT 'ID/identificador do item de origem no módulo de origem',
    referencia_sistema    VARCHAR(120) NULL COMMENT 'Sistema ou módulo responsável pela origem do lançamento',
    numero_processo       VARCHAR(50)  NULL COMMENT 'Número do processo associado, quando houver',
    classe_processo       VARCHAR(200) NULL COMMENT 'Classe processual resolvida na criação do lançamento',
    observacoes           TEXT         NULL COMMENT 'Observações livres do usuário ou do sistema',
    data_hora_ato         DATETIME     NOT NULL COMMENT 'Momento real do ato computado',
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulado_por           CHAR(30)     NULL COMMENT 'FK para usuarios.id_usuario',
    anulado_em            DATETIME     NULL,
    motivo_anulacao       TEXT         NULL COMMENT 'Justificativa obrigatória da anulação',
    PRIMARY KEY (id),
    UNIQUE KEY uk_produtividade_lancamentos_contexto_hash (contexto_hash),
    KEY ix_produtividade_lancamentos_usuario_data (usuario_id, data_hora_ato),
    KEY ix_produtividade_lancamentos_status (status),
    KEY ix_produtividade_lancamentos_regra (regra_id),
    KEY ix_produtividade_lancamentos_numero_processo (numero_processo),
    CONSTRAINT fk_produtividade_lancamentos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_produtividade_lancamentos_regra
        FOREIGN KEY (regra_id) REFERENCES produtividade_regras (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_produtividade_lancamentos_anulado_por
        FOREIGN KEY (anulado_por) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registros de produtividade manuais e automáticos, com proteção anti-duplicidade por contexto.';

CREATE TABLE IF NOT EXISTS produtividade_log (
    id                    CHAR(30)     NOT NULL COMMENT 'Formato PLG- + 26 chars (regra padrão 30 chars)',
    lancamento_id         CHAR(30)     NOT NULL COMMENT 'FK para produtividade_lancamentos.id',
    usuario_id            CHAR(30)     NOT NULL COMMENT 'Usuário responsável pela ação de log',
    acao                  VARCHAR(50)  NOT NULL COMMENT 'Ex.: criar, anular, corrigir, recalcular',
    detalhes              JSON         NULL COMMENT 'Payload adicional de auditoria',
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_produtividade_log_lancamento (lancamento_id),
    KEY ix_produtividade_log_usuario (usuario_id),
    KEY ix_produtividade_log_acao (acao),
    KEY ix_produtividade_log_criado_em (criado_em),
    CONSTRAINT fk_produtividade_log_lancamento
        FOREIGN KEY (lancamento_id) REFERENCES produtividade_lancamentos (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_produtividade_log_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Trilha de auditoria de eventos do módulo de produtividade.';

CREATE TABLE IF NOT EXISTS produtividade_badges (
    id                    CHAR(30)     NOT NULL COMMENT 'Formato PBG- + 26 chars (regra padrão 30 chars)',
    nome                  VARCHAR(100) NOT NULL COMMENT 'Nome exibido da conquista',
    descricao             TEXT         NULL COMMENT 'Descrição funcional do badge',
    icone                 VARCHAR(200) NULL COMMENT 'Slug/caminho futuro do ícone associado',
    tipo                  ENUM('pontuacao_cumulativa', 'ato_especifico') NOT NULL COMMENT 'Critério principal de concessão',
    regra_id              CHAR(30)     NULL COMMENT 'FK opcional para produtividade_regras.id quando o badge depender de ato específico',
    valor_threshold       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor mínimo para conquista',
    periodo               ENUM('total', 'mensal', 'semanal', 'diario') NOT NULL DEFAULT 'total' COMMENT 'Janela de apuração da conquista',
    ativo                 TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=inativo',
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_produtividade_badges_tipo (tipo),
    KEY ix_produtividade_badges_regra (regra_id),
    KEY ix_produtividade_badges_ativo (ativo),
    CONSTRAINT fk_produtividade_badges_regra
        FOREIGN KEY (regra_id) REFERENCES produtividade_regras (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Definição de badges e conquistas do módulo de produtividade.';

CREATE TABLE IF NOT EXISTS produtividade_badges_concedidos (
    id                    CHAR(30)     NOT NULL COMMENT 'Formato PBC- + 26 chars (regra padrão 30 chars)',
    badge_id              CHAR(30)     NOT NULL COMMENT 'FK para produtividade_badges.id',
    usuario_id            CHAR(30)     NOT NULL COMMENT 'FK para usuarios.id_usuario',
    concedido_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notificado            TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=usuário já notificado da conquista, 0=pendente',
    PRIMARY KEY (id),
    UNIQUE KEY uk_produtividade_badges_concedidos_badge_usuario (badge_id, usuario_id),
    KEY ix_produtividade_badges_concedidos_usuario (usuario_id),
    KEY ix_produtividade_badges_concedidos_notificado (notificado),
    KEY ix_produtividade_badges_concedidos_concedido_em (concedido_em),
    CONSTRAINT fk_produtividade_badges_concedidos_badge
        FOREIGN KEY (badge_id) REFERENCES produtividade_badges (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_produtividade_badges_concedidos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id_usuario)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de badges já conquistados por usuário.';

DROP TRIGGER IF EXISTS tr_produtividade_regras_before_insert;
DROP TRIGGER IF EXISTS tr_produtividade_lancamentos_before_insert;
DROP TRIGGER IF EXISTS tr_produtividade_log_before_insert;
DROP TRIGGER IF EXISTS tr_produtividade_badges_before_insert;
DROP TRIGGER IF EXISTS tr_produtividade_badges_concedidos_before_insert;

DELIMITER //

CREATE TRIGGER tr_produtividade_regras_before_insert
BEFORE INSERT ON produtividade_regras
FOR EACH ROW
BEGIN
    IF (NEW.id IS NULL OR NEW.id = '') THEN
        SET NEW.id = CONCAT('PRG-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
    END IF;
END//

CREATE TRIGGER tr_produtividade_lancamentos_before_insert
BEFORE INSERT ON produtividade_lancamentos
FOR EACH ROW
BEGIN
    IF (NEW.id IS NULL OR NEW.id = '') THEN
        SET NEW.id = CONCAT('PLC-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
    END IF;
END//

CREATE TRIGGER tr_produtividade_log_before_insert
BEFORE INSERT ON produtividade_log
FOR EACH ROW
BEGIN
    IF (NEW.id IS NULL OR NEW.id = '') THEN
        SET NEW.id = CONCAT('PLG-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
    END IF;
END//

CREATE TRIGGER tr_produtividade_badges_before_insert
BEFORE INSERT ON produtividade_badges
FOR EACH ROW
BEGIN
    IF (NEW.id IS NULL OR NEW.id = '') THEN
        SET NEW.id = CONCAT('PBG-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
    END IF;
END//

CREATE TRIGGER tr_produtividade_badges_concedidos_before_insert
BEFORE INSERT ON produtividade_badges_concedidos
FOR EACH ROW
BEGIN
    IF (NEW.id IS NULL OR NEW.id = '') THEN
        SET NEW.id = CONCAT('PBC-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26)));
    END IF;
END//

DELIMITER ;
