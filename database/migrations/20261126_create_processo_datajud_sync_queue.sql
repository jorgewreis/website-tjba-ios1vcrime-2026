CREATE TABLE IF NOT EXISTS processo_datajud_sync_queue (
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
  PRIMARY KEY (id),
  UNIQUE KEY uq_processo_datajud_sync (processo_id),
  KEY idx_processo_datajud_queue (status, proxima_tentativa_em, id),
  CONSTRAINT fk_processo_datajud_sync_process FOREIGN KEY (processo_id) REFERENCES processos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
