<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/app/helpers/Conection.php';
$db = Conection::getInstance()->getConnection();
$db->exec("CREATE TABLE IF NOT EXISTS processo_datajud_sync_queue (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 processo_id CHAR(30) NOT NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'pendente',
 tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 proxima_tentativa_em DATETIME NOT NULL,
 solicitado_em DATETIME(6) NOT NULL,
 processando_em DATETIME(6) NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 atualizado_em DATETIME NULL,
 PRIMARY KEY (id), UNIQUE KEY uq_processo_datajud_sync (processo_id),
 KEY idx_processo_datajud_queue (status, proxima_tentativa_em, id),
 CONSTRAINT fk_processo_datajud_sync_process FOREIGN KEY (processo_id) REFERENCES processos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$column = $db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'processo_datajud_sync_queue' AND COLUMN_NAME = 'requested_by'")->fetchColumn();
if ($column === false) {
    $db->exec('ALTER TABLE processo_datajud_sync_queue ADD COLUMN requested_by CHAR(30) NULL AFTER processo_id');
}
echo "Fila automática DataJud pronta.\n";
