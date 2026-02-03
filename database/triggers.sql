-- ============================================================================
-- TRIGGERS.SQL - Sistema de Gestão Criminal de Ilhéus
-- Banco de Dados: common
-- Versão: 1.0.0
-- Data: 2026-02-03
-- ============================================================================
-- IMPORTANTE: Execute este arquivo via terminal MySQL, não pelo SQLTools
-- Comando: mysql -u usuario -p common < /caminho/triggers.sql
-- ============================================================================

USE common;

-- ============================================================================
-- REMOVER TRIGGERS EXISTENTES
-- ============================================================================

DROP TRIGGER IF EXISTS trg_historico_senha_update;
DROP TRIGGER IF EXISTS trg_limpar_tentativas_login;

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
        VALUES (UUID(), NEW.id_usuario, OLD.senha_hash, NOW());
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- TRIGGER: trg_limpar_tentativas_login
-- Descrição: Limpa tentativas de login após login bem-sucedido
-- Objetivo: Resetar contador de bloqueio após acesso autorizado
-- Acionamento: AFTER UPDATE na tabela usuarios
-- ============================================================================

DELIMITER //
CREATE TRIGGER trg_limpar_tentativas_login
AFTER UPDATE ON usuarios
FOR EACH ROW
BEGIN
    -- Verifica se houve atualização de último_acesso (login realizado)
    IF NEW.ultimo_acesso IS NOT NULL AND 
       (OLD.ultimo_acesso IS NULL OR NEW.ultimo_acesso != OLD.ultimo_acesso) THEN
        -- Remove registro de tentativas do CPF
        DELETE FROM tentativas_login WHERE cpf = NEW.cpf;
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
