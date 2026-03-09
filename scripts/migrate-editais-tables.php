<?php
/**
 * Migração: criar tabelas do módulo Editais (editais, editais_subtarefas, editais_alocados).
 * Executar uma vez a partir da raiz do projeto: php scripts/migrate-editais-tables.php
 */
declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/app/helpers/Conection.php';

$connection = Conection::getInstance()->getConnection();

$sqlFile = $basePath . '/database/editais-tables.sql';
if (!is_readable($sqlFile)) {
    echo "Arquivo não encontrado: {$sqlFile}\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
// Remove comentários de linha e executa cada statement (separados por ;)
$statements = array_filter(
    array_map('trim', explode(';', preg_replace('/--.*$/m', '', $sql))),
    fn (string $s) => $s !== ''
);

foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }
    try {
        $connection->exec($statement);
        echo "OK: " . substr($statement, 0, 60) . "...\n";
    } catch (Throwable $e) {
        echo "Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Migração editais: tabelas criadas com sucesso.\n";
echo "Opcional: inserir regra ALLOCATE-EDITAIS em alocacoes_regras pela tela de Regras de Alocação (Editais).\n";
