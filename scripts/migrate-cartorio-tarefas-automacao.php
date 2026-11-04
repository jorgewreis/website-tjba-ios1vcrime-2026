<?php
declare(strict_types=1);

if (!isset($db) || !$db instanceof PDO) {
    require_once __DIR__ . '/../app/helpers/Conection.php';
    $db = Conection::getInstance()->getConnection();
}

// As tabelas são aditivas; nenhuma regra é ativada pela migração.
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_lock (
    id TINYINT NOT NULL PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec('INSERT IGNORE INTO cartorio_tarefas_automacao_lock (id) VALUES (1)');
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_monitor (
    id TINYINT NOT NULL PRIMARY KEY,
    ultimo_sucesso_em DATETIME DEFAULT NULL,
    ultima_falha_em DATETIME DEFAULT NULL,
    ultima_falha TEXT DEFAULT NULL,
    codigo_hash CHAR(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec('INSERT IGNORE INTO cartorio_tarefas_automacao_monitor (id) VALUES (1)');
$st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
$st->execute(['cartorio_tarefas_automacao_monitor', 'codigo_hash']);
if (!(int) $st->fetchColumn()) $db->exec('ALTER TABLE cartorio_tarefas_automacao_monitor ADD COLUMN codigo_hash CHAR(64) DEFAULT NULL');
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
    evento_apos_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tarefas_regras_evento (ativa, evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
$st->execute(['cartorio_tarefas_regras', 'evento_apos_id']);
if (!(int) $st->fetchColumn()) $db->exec('ALTER TABLE cartorio_tarefas_regras ADD COLUMN evento_apos_id BIGINT UNSIGNED NOT NULL DEFAULT 0');
$st->execute(['cartorio_tarefas_regras', 'excluida_em']);
if (!(int) $st->fetchColumn()) $db->exec('ALTER TABLE cartorio_tarefas_regras ADD COLUMN excluida_em DATETIME DEFAULT NULL');
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
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_regra_lote_itens (
    lote_id BIGINT UNSIGNED NOT NULL,
    tarefa_id CHAR(30) NOT NULL,
    PRIMARY KEY (lote_id, tarefa_id),
    CONSTRAINT fk_tarefas_lote_item_lote FOREIGN KEY (lote_id) REFERENCES cartorio_tarefas_regra_lotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("DELETE i FROM cartorio_tarefas_regra_lote_itens i JOIN cartorio_tarefas_regra_lotes l ON l.id = i.lote_id WHERE l.estado IN ('concluido', 'cancelado') OR (l.cursor_tarefa IS NOT NULL AND i.tarefa_id <= l.cursor_tarefa)");
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
$colunas = [
    'sequencia_id' => 'CHAR(30) DEFAULT NULL',
    'origem_id' => 'CHAR(30) DEFAULT NULL',
    'primeiro_andamento_em' => 'DATETIME DEFAULT NULL',
    'alocado_em' => 'DATETIME DEFAULT NULL',
    'prazo_em' => 'DATETIME DEFAULT NULL',
    'prazo_regra_id' => 'BIGINT UNSIGNED DEFAULT NULL',
    'prazo_inicio' => 'VARCHAR(30) DEFAULT NULL',
    'prazo_dias' => 'INT UNSIGNED DEFAULT NULL',
    'prazo_alerta_dias' => 'INT UNSIGNED DEFAULT NULL',
    'prazo_evento_em' => 'DATETIME DEFAULT NULL',
];
foreach ($colunas as $nome => $definicao) {
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute(['cartorio_tarefas', $nome]);
    if (!(int) $st->fetchColumn()) $db->exec("ALTER TABLE cartorio_tarefas ADD COLUMN `$nome` $definicao");
}
$st = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
$st->execute(['cartorio_tarefas', 'idx_tarefas_sequencia']);
if (!(int) $st->fetchColumn()) $db->exec('CREATE INDEX idx_tarefas_sequencia ON cartorio_tarefas (sequencia_id)');
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tarefa_id CHAR(30) NOT NULL,
    evento VARCHAR(30) NOT NULL,
    origem_id CHAR(30) DEFAULT NULL,
    status_evento VARCHAR(30) DEFAULT NULL,
    regra_alvo_id BIGINT UNSIGNED DEFAULT NULL,
    regra_versao INT UNSIGNED DEFAULT NULL,
    retroativo TINYINT(1) NOT NULL DEFAULT 0,
    caminho LONGTEXT DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tarefas_eventos_fila (id),
    KEY idx_tarefas_eventos_tarefa (tarefa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
foreach (['regra_alvo_id' => 'BIGINT UNSIGNED DEFAULT NULL', 'regra_versao' => 'INT UNSIGNED DEFAULT NULL', 'retroativo' => 'TINYINT(1) NOT NULL DEFAULT 0'] as $nome => $definicao) {
    $st = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute(['cartorio_tarefas_automacao_eventos', $nome]);
    if (!(int) $st->fetchColumn()) $db->exec("ALTER TABLE cartorio_tarefas_automacao_eventos ADD COLUMN `$nome` $definicao");
}
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_criacoes (
    tarefa_origem_id CHAR(30) NOT NULL,
    regra_id BIGINT UNSIGNED NOT NULL,
    acao_id VARCHAR(40) NOT NULL,
    tarefa_criada_id CHAR(30) NOT NULL,
    PRIMARY KEY (tarefa_origem_id, regra_id, acao_id),
    KEY idx_tarefas_criadas (tarefa_criada_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_automacao_eventos_regras (
    evento_id BIGINT UNSIGNED NOT NULL,
    regra_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (evento_id, regra_id),
    CONSTRAINT fk_tarefas_evento_regra_evento FOREIGN KEY (evento_id) REFERENCES cartorio_tarefas_automacao_eventos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
fwrite(STDOUT, "Migração de regras de tarefas concluída.\n");
