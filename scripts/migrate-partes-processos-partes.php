<?php
/**
 * Migracao: criar tabelas partes, processos_partes e processo_prioridades.
 * Executar uma vez a partir da raiz do projeto: php scripts/migrate-partes-processos-partes.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}


$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sqlFile = $basePath . '/database/partes-processos-partes.sql';
if (!is_readable($sqlFile)) {
    echo "Arquivo não encontrado: {$sqlFile}\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(
    array_map('trim', explode(';', preg_replace('/--.*$/m', '', (string) $sql))),
    static fn (string $statement): bool => $statement !== ''
);

foreach ($statements as $statement) {
    try {
        $connection->exec($statement);
        echo "OK: " . substr($statement, 0, 60) . "...\n";
    } catch (Throwable $exception) {
        echo "Erro: " . $exception->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração partes/processos_partes concluída com sucesso.\n";
