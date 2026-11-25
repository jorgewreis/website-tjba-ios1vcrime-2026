<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}


require_once __DIR__ . '/../app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$connection->exec(
    'CREATE TABLE IF NOT EXISTS `cejusc_registros` (
        `id` CHAR(30) NOT NULL PRIMARY KEY,
        `numero_processo` VARCHAR(40) NOT NULL,
        `classe_codigo` VARCHAR(30) DEFAULT NULL,
        `classe_descricao` VARCHAR(255) DEFAULT NULL,
        `nome_parte` VARCHAR(180) NOT NULL,
        `data_envio_cejusc` DATE NOT NULL,
        `criado_por` CHAR(30) DEFAULT NULL,
        `atualizado_por` CHAR(30) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY `idx_cejusc_registros_processo` (`numero_processo`),
        KEY `idx_cejusc_registros_parte` (`nome_parte`),
        KEY `idx_cejusc_registros_data_envio` (`data_envio_cejusc`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$columns = [];
$statement = $connection->query('SHOW COLUMNS FROM `cejusc_registros`');
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[(string) ($column['Field'] ?? '')] = true;
}

$missing = [
    'classe_codigo' => 'ALTER TABLE `cejusc_registros` ADD COLUMN `classe_codigo` VARCHAR(30) DEFAULT NULL AFTER `numero_processo`',
    'classe_descricao' => 'ALTER TABLE `cejusc_registros` ADD COLUMN `classe_descricao` VARCHAR(255) DEFAULT NULL AFTER `classe_codigo`',
];

foreach ($missing as $name => $sql) {
    if (!isset($columns[$name])) {
        $connection->exec($sql);
    }
}

fwrite(STDOUT, "Migração CEJUSC concluída.\n");
