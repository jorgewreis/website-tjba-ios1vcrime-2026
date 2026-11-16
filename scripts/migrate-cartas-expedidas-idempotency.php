<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/helpers/Conection.php';

$pdo = Conection::getInstance()->getConnection();
$table = $pdo->prepare(
    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
);
$table->execute([':table_name' => 'cartas_expedidas']);
$engine = $table->fetchColumn();
if (!is_string($engine) || strtoupper($engine) !== 'INNODB') {
    throw new RuntimeException('Migração recusada: cartas_expedidas precisa existir em InnoDB.');
}

$columnsQuery = $pdo->prepare(
    'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
);
$columnsQuery->execute([':table_name' => 'cartas_expedidas']);
$columns = [];
foreach ($columnsQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[strtolower((string) $column['COLUMN_NAME'])] = $column;
}
$indexQuery = $pdo->prepare(
    'SELECT NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name ORDER BY SEQ_IN_INDEX'
);
$indexQuery->execute([':table_name' => 'cartas_expedidas', ':index_name' => 'uq_cartas_expedidas_idempotency']);
$index = $indexQuery->fetchAll(PDO::FETCH_ASSOC);

$hasKey = isset($columns['idempotency_key']);
$hasHash = isset($columns['idempotency_payload_hash']);
if ($hasKey && $hasHash && count($index) === 2) {
    $valid = strtolower((string) $columns['idempotency_key']['COLUMN_TYPE']) === 'char(64)'
        && strtolower((string) $columns['idempotency_payload_hash']['COLUMN_TYPE']) === 'char(64)'
        && $columns['idempotency_key']['IS_NULLABLE'] === 'YES'
        && $columns['idempotency_payload_hash']['IS_NULLABLE'] === 'YES'
        && (int) $index[0]['NON_UNIQUE'] === 0
        && (int) $index[0]['SEQ_IN_INDEX'] === 1
        && strtolower((string) $index[0]['COLUMN_NAME']) === 'criado_por'
        && $index[0]['SUB_PART'] === null
        && (int) $index[1]['NON_UNIQUE'] === 0
        && (int) $index[1]['SEQ_IN_INDEX'] === 2
        && strtolower((string) $index[1]['COLUMN_NAME']) === 'idempotency_key'
        && $index[1]['SUB_PART'] === null;
    if (!$valid) {
        throw new RuntimeException('Migração recusada: definição existente da chave idempotente não corresponde ao contrato.');
    }
    fwrite(STDOUT, "OK cartas_expedidas idempotency schema já aplicado.\n");
    exit(0);
}

if ($hasKey || $hasHash || $index !== []) {
    throw new RuntimeException('Migração recusada: schema parcialmente aplicado; verifique a estrutura antes de corrigir.');
}

$sqlPath = $root . '/database/migrations/20261116_add_cartas_expedidas_idempotency.sql';
$sql = file_get_contents($sqlPath);
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('Migração SQL não encontrada ou vazia.');
}
$pdo->exec($sql);
$columnsQuery->execute([':table_name' => 'cartas_expedidas']);
$verified = array_map('strtolower', $columnsQuery->fetchAll(PDO::FETCH_COLUMN));
if (!in_array('idempotency_key', $verified, true) || !in_array('idempotency_payload_hash', $verified, true)) {
    throw new RuntimeException('Migração não pôde ser confirmada após execução.');
}
$indexQuery->execute([':table_name' => 'cartas_expedidas', ':index_name' => 'uq_cartas_expedidas_idempotency']);
$verifiedIndex = $indexQuery->fetchAll(PDO::FETCH_ASSOC);
$indexIsValid = count($verifiedIndex) === 2
    && (int) $verifiedIndex[0]['NON_UNIQUE'] === 0
    && (int) $verifiedIndex[0]['SEQ_IN_INDEX'] === 1
    && strtolower((string) $verifiedIndex[0]['COLUMN_NAME']) === 'criado_por'
    && $verifiedIndex[0]['SUB_PART'] === null
    && (int) $verifiedIndex[1]['NON_UNIQUE'] === 0
    && (int) $verifiedIndex[1]['SEQ_IN_INDEX'] === 2
    && strtolower((string) $verifiedIndex[1]['COLUMN_NAME']) === 'idempotency_key'
    && $verifiedIndex[1]['SUB_PART'] === null;
if (!$indexIsValid) {
    throw new RuntimeException('Migração não pôde confirmar o índice único após execução.');
}
fwrite(STDOUT, "OK cartas_expedidas idempotency schema aplicado e verificado.\n");
