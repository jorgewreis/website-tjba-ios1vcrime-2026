<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!isset($db) || !$db instanceof PDO) {
    require_once __DIR__ . '/../app/helpers/Conection.php';
    $db = Conection::getInstance()->getConnection();
}
$db->exec("SET time_zone = '-03:00'");
$valor = $db->query('SELECT ultimo_sucesso_em FROM cartorio_tarefas_automacao_monitor WHERE id = 1')->fetchColumn();
$fuso = new DateTimeZone('America/Bahia');
if (!$valor || (new DateTimeImmutable('now', $fuso))->getTimestamp() - (new DateTimeImmutable((string) $valor, $fuso))->getTimestamp() > 180) {
    fwrite(STDERR, "Ainda não há execução recente confirmada da automação.\n");
    exit(1);
}
fwrite(STDOUT, "Processamento de tarefas confirmado: $valor (Bahia).\n");
