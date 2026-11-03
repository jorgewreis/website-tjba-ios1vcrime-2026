<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/Conection.php';

$db = Conection::getInstance()->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas (
    id CHAR(30) NOT NULL PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'a_fazer',
    tipo VARCHAR(30) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME DEFAULT NULL,
    CONSTRAINT chk_cartorio_tarefas_finalizacao CHECK (status <> 'finalizada' OR finalizado_em IS NOT NULL),
    KEY idx_cartorio_tarefas_status_tipo (status, tipo),
    KEY idx_cartorio_tarefas_retencao (status, finalizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS cartorio_tarefas_servidores (
    tarefa_id CHAR(30) NOT NULL,
    usuario_id VARCHAR(80) NOT NULL,
    PRIMARY KEY (tarefa_id, usuario_id),
    KEY idx_cartorio_tarefas_servidor (usuario_id, tarefa_id),
    CONSTRAINT fk_cartorio_tarefas_servidor_tarefa FOREIGN KEY (tarefa_id) REFERENCES cartorio_tarefas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
fwrite(STDOUT, "Migração de tarefas do Cartório concluída.\n");
