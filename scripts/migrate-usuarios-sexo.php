<?php
/**
 * Migração: adicionar coluna sexo na tabela usuarios.
 * Executar uma vez a partir da raiz: php scripts/migrate-usuarios-sexo.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}


$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sql = "ALTER TABLE `usuarios` ADD COLUMN `sexo` VARCHAR(20) NULL COMMENT 'Sexo do usuário (Feminino, Masculino, Outro)' AFTER `sobrenome`";

try {
    $connection->exec($sql);
    echo "OK: coluna sexo adicionada em usuarios.\n";
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Coluna sexo já existe, ignorando.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração usuarios (sexo) concluída.\n";
