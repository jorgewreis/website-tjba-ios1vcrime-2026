<?php
declare(strict_types=1);

$configuredRoot = getenv('GUIAS_PROJECT_ROOT');
$root = is_string($configuredRoot) && trim($configuredRoot) !== ''
    ? realpath($configuredRoot)
    : dirname(__DIR__);
if (!is_string($root) || !is_file($root . '/app/helpers/Conection.php')) {
    throw new RuntimeException('Raiz da aplicação inválida para a migration de guias.');
}
$configuredHelper = getenv('GUIAS_MIGRATION_HELPER');
$helper = is_string($configuredHelper) && trim($configuredHelper) !== ''
    ? realpath($configuredHelper)
    : realpath($root . '/app/helpers/GuiasIdempotenciaMigration.php');
if (!is_string($helper) || !is_file($helper)) {
    throw new RuntimeException('Validador da migration de guias ausente.');
}
$configuredSql = getenv('GUIAS_MIGRATION_SQL');
$sql = is_string($configuredSql) && trim($configuredSql) !== ''
    ? $configuredSql
    : $root . '/database/migrations/20261120_rei250_guia_idempotencia.sql';
if (!is_file($sql)) {
    throw new RuntimeException('SQL da migration de guias ausente.');
}

require_once $root . '/app/helpers/Conection.php';
require_once $helper;
$created = GuiasIdempotenciaMigration::apply(Conection::getInstance()->getConnection(), $sql);
fwrite(STDOUT, $created
    ? "OK tabela guia_criacao_idempotencia criada e verificada.\n"
    : "OK tabela guia_criacao_idempotencia já estava válida.\n");
