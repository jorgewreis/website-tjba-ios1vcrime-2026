<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__).'/app/helpers/Conection.php';
$pdo=Conection::getInstance()->getConnection();
$pdo->exec("CREATE TABLE IF NOT EXISTS dashboard_conclusoes (
  id CHAR(36) NOT NULL,
  usuario_id VARCHAR(64) NOT NULL,
  modulo VARCHAR(40) NOT NULL,
  area VARCHAR(24) NOT NULL,
  referencia_interna VARCHAR(64) NOT NULL,
  concluido_em DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_dashboard_user_date (usuario_id, concluido_em),
  KEY idx_dashboard_area_date (area, concluido_em),
  KEY idx_dashboard_module_date (modulo, concluido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Trilha de conclusões do dashboard disponível (sem carga retroativa).\n";
