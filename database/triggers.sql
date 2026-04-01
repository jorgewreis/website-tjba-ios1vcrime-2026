-- ============================================================================
-- TRIGGERS.SQL - Sistema de Gestão Criminal de Ilhéus
-- Banco de Dados: common
-- Versão: 1.1.0
-- Data: 2026-04-01
-- ============================================================================
-- ID: Gerar sempre no padrão 30 caracteres (prefixo 3 letras + hífen + 26 chars).
-- Documentação: docs/00-foundation/id-padrao-banco-dados.md
-- IMPORTANTE: Execute este arquivo via terminal MySQL, não pelo SQLTools
-- Comando: mysql -u usuario -p common < /caminho/triggers.sql
-- Observação: o controle oficial de rate limit de login é feito na aplicação,
-- pela tabela login_rate_limits. Não há trigger canônico para esse fluxo.
-- ============================================================================

USE common;

-- ============================================================================
-- REMOVER TRIGGERS EXISTENTES
-- ============================================================================

DROP TRIGGER IF EXISTS trg_historico_senha_update;

-- ============================================================================
-- TRIGGER: trg_historico_senha_update
-- Descrição: Registra histórico de senha ao alterar
-- Objetivo: Evitar reutilização da senha anterior
-- Acionamento: AFTER UPDATE na tabela usuarios
-- ============================================================================

DELIMITER //
CREATE TRIGGER trg_historico_senha_update
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    -- Verifica se a senha foi alterada
    IF OLD.senha_hash != NEW.senha_hash THEN
        -- Insere a senha anterior no histórico
        INSERT INTO historico_senha (id_historico, usuario, senha_hash, data_alteracao)
        VALUES (CONCAT('HIS-', UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 26))), NEW.id_usuario, OLD.senha_hash, NOW());
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- VERIFICAÇÃO DOS TRIGGERS CRIADOS
-- ============================================================================

SHOW TRIGGERS FROM common;

-- ============================================================================
-- FIM DO ARQUIVO TRIGGERS
-- ============================================================================
