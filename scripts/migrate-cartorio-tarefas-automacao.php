<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/Conection.php';
$db = Conection::getInstance()->getConnection();

// As tabelas são aditivas; nenhuma regra é ativada pela migração.
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_lock (
    id TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec('INSERT IGNORE INTO cartorio_tarefas_automacao_lock (id) VALUES (1)');
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_regras (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    evento VARCHAR(30) NOT NULL,
    evento_status VARCHAR(30) DEFAULT NULL,
    prioridade INT NOT NULL DEFAULT 0,
    condicoes LONGTEXT NOT NULL,
    acoes LONGTEXT NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 0,
    versao INT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tarefas_regras_evento (ativa, evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_regra_lotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    regra_id BIGINT UNSIGNED NOT NULL,
    versao INT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
    cursor_tarefa CHAR(30) DEFAULT NULL,
    total INT UNSIGNED NOT NULL DEFAULT 0,
    processadas INT UNSIGNED NOT NULL DEFAULT 0,
    falhas INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tarefas_lote_fila (estado, id),
    CONSTRAINT fk_tarefas_lote_regra FOREIGN KEY (regra_id) REFERENCES cartorio_tarefas_regras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_falhas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    regra_id BIGINT UNSIGNED DEFAULT NULL,
    tarefa_id CHAR(30) DEFAULT NULL,
    evento VARCHAR(30) NOT NULL,
    detalhes TEXT NOT NULL,
    contexto LONGTEXT DEFAULT NULL,
    pode_tentar TINYINT(1) NOT NULL DEFAULT 0,
    resolvida_em DATETIME DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tarefas_falha_regra (regra_id, criado_em),
    KEY idx_tarefas_falha_tarefa (tarefa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
fwrite(STDOUT, "Migração de regras de tarefas concluída.\n");
