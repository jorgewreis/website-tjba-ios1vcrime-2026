-- ============================================================================
-- SCHEMA.SQL - Sistema de Gestão Criminal de Ilhéus
-- Banco de Dados: common
-- Versão: 1.0.0
-- Data: 2026-02-03
-- ============================================================================
-- REGRA DE ID: Todas as chaves primárias de tabela seguem o padrão de 30
-- caracteres: prefixo de 3 letras + hífen + código de 26 caracteres (ex.: SET-xxx).
-- Documentação: docs/00-foundation/id-padrao-banco-dados.md
-- ============================================================================

USE common;

-- ============================================================================
-- TABELA: setores
-- Descrição: Armazena a estrutura organizacional da instituição
-- Permite controle de acesso estrito por setor (sem permissões transversais)
-- ============================================================================

CREATE TABLE IF NOT EXISTS setores (
    id_setor CHAR(30) NOT NULL COMMENT 'Formato SET- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    codigo_area VARCHAR(20) NULL DEFAULT NULL COMMENT 'CARTORIO | GABINETE | AUDIENCIAS',
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    setor_pai VARCHAR(30) DEFAULT NULL,
    ativo TINYINT(1) DEFAULT 1,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_setor_pai (setor_pai),
    INDEX idx_setores_codigo_area (codigo_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: usuario_setor
-- Descrição: Relacionamento N:N entre usuários e setores
-- Define a qual setor cada usuário pertence para controle de acesso
-- ============================================================================

CREATE TABLE IF NOT EXISTS usuario_setor (
    id_usu_setor CHAR(30) NOT NULL COMMENT 'Formato USO- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    setor CHAR(30) NOT NULL,
    data_alocacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    alocado_por VARCHAR(30) DEFAULT NULL,
    INDEX idx_usuario (usuario),
    INDEX idx_setor (setor),
    INDEX idx_alocado_por (alocado_por),
    UNIQUE KEY unique_usuario_setor (usuario, setor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: logs_acesso
-- Descrição: Registra todos os acessos ao sistema (autenticação)
-- Utilizado para auditoria de segurança e relatórios de acesso
-- ============================================================================

CREATE TABLE IF NOT EXISTS logs_acesso (
    id_log CHAR(30) NOT NULL COMMENT 'Formato LOG- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    usuario CHAR(30) DEFAULT NULL,
    cpf VARCHAR(15) DEFAULT NULL,
    acao VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    sucesso TINYINT(1) DEFAULT 0,
    mensagem TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_cpf (cpf),
    INDEX idx_data (data_hora),
    INDEX idx_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: logs_sistema
-- Descrição: Auditoria de operações críticas (CRUD, exportações, etc)
-- Registra todas as ações realizadas no sistema para rastreabilidade
-- ============================================================================

CREATE TABLE IF NOT EXISTS logs_sistema (
    id_log_sistema CHAR(30) NOT NULL COMMENT 'Formato LGS- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL,
    tabela_afetada VARCHAR(50) DEFAULT NULL,
    registro_id CHAR(30) DEFAULT NULL,
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip VARCHAR(45) NOT NULL,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_modulo (modulo),
    INDEX idx_acao (acao),
    INDEX idx_data (data_hora),
    INDEX idx_tabela (tabela_afetada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: tentativas_login
-- Descrição: Controle de tentativas de login para bloqueio de conta
-- Bloqueia conta após 3 tentativas falhas consecutivas
-- Requer desbloqueio por administrador
-- ============================================================================

CREATE TABLE IF NOT EXISTS tentativas_login (
    id_tentativa CHAR(30) NOT NULL COMMENT 'Formato TNT- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    cpf VARCHAR(15) NOT NULL,
    tentativas INT DEFAULT 0,
    bloqueado TINYINT(1) DEFAULT 0,
    data_bloqueio DATETIME DEFAULT NULL,
    ip VARCHAR(45) NOT NULL,
    ultima_tentativa DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cpf (cpf),
    INDEX idx_bloqueado (bloqueado),
    UNIQUE KEY unique_cpf (cpf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: recuperacao_senha
-- Descrição: Tokens para recuperação de senha via e-mail
-- Token enviado para o e-mail cadastrado do usuário
-- Expira após período definido (recomendado: 1 hora)
-- ============================================================================

CREATE TABLE IF NOT EXISTS recuperacao_senha (
    id_recuperacao CHAR(30) NOT NULL COMMENT 'Formato REC- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    expira_em DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    ip VARCHAR(45) NOT NULL,
    data_solicitacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_token (token),
    INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABELA: historico_senha
-- Descrição: Armazena senhas anteriores para evitar reutilização
-- Garante que a nova senha seja diferente da anterior
-- ============================================================================

CREATE TABLE IF NOT EXISTS historico_senha (
    id_historico CHAR(30) NOT NULL COMMENT 'Formato HIS- + 26 chars (regra padrão 30 chars)' PRIMARY KEY,
    usuario CHAR(30) NOT NULL,
    senha_hash VARCHAR(200) NOT NULL,
    data_alteracao DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario),
    INDEX idx_data (data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADICIONAR ÍNDICES NAS TABELAS EXISTENTES
-- ============================================================================

-- Índices na tabela usuarios para otimização de consultas
ALTER TABLE usuarios ADD INDEX idx_cpf (cpf);
ALTER TABLE usuarios ADD INDEX idx_email (email);
ALTER TABLE usuarios ADD INDEX idx_ultimo_acesso (ultimo_acesso);

-- Índices na tabela processos para otimização de buscas
ALTER TABLE processos ADD INDEX idx_numero (numero);
ALTER TABLE processos ADD INDEX idx_classe (classe);
ALTER TABLE processos ADD INDEX idx_data_cadastro (data_cadastro);

-- ============================================================================
-- VIEWS ÚTEIS
-- ============================================================================

-- View: Usuários com seus perfis e setores
-- Facilita consultas que necessitam de informações completas do usuário
CREATE OR REPLACE VIEW v_usuarios_completo AS
SELECT 
    u.id_usuario,
    u.nome,
    u.sobrenome,
    CONCAT(u.nome, ' ', u.sobrenome) AS nome_completo,
    u.cpf,
    u.email,
    u.cargo,
    u.instituicao,
    u.telefone,
    u.ultimo_acesso,
    GROUP_CONCAT(DISTINCT p.descricao ORDER BY p.descricao SEPARATOR ', ') AS perfis,
    GROUP_CONCAT(DISTINCT s.nome ORDER BY s.nome SEPARATOR ', ') AS setores
FROM usuarios u
LEFT JOIN usuario_perfil up ON u.id_usuario COLLATE utf8mb4_unicode_ci = up.usuario COLLATE utf8mb4_unicode_ci
LEFT JOIN perfis p ON up.perfil COLLATE utf8mb4_unicode_ci = p.id_perfil COLLATE utf8mb4_unicode_ci
LEFT JOIN usuario_setor us ON u.id_usuario COLLATE utf8mb4_unicode_ci = us.usuario COLLATE utf8mb4_unicode_ci
LEFT JOIN setores s ON us.setor COLLATE utf8mb4_unicode_ci = s.id_setor COLLATE utf8mb4_unicode_ci
GROUP BY u.id_usuario;

-- View: Processos com informações da classe
-- Facilita consultas de processos com dados da classe associada
CREATE OR REPLACE VIEW v_processos_completo AS
SELECT 
    p.id_proc,
    p.numero,
    p.data_cadastro,
    c.cod AS classe_codigo,
    c.descricao AS classe_descricao
FROM processos p
INNER JOIN classes c ON p.classe COLLATE utf8mb4_unicode_ci = c.id_classe COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS
-- ============================================================================
-- NOTA: Os triggers devem ser criados através do arquivo separado triggers.sql
-- Isso garante compatibilidade com diferentes ferramentas de execução SQL
-- Consulte: /database/triggers.sql para instruções de criação

-- ============================================================================
-- FIM DO SCHEMA
-- ============================================================================

-- Verificação das tabelas criadas
SHOW TABLES;
