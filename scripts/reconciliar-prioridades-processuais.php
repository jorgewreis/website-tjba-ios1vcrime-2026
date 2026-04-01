<?php
declare(strict_types=1);

/**
 * Job de rede de segurança: reavalia regras automáticas (menor, réu preso) em todos
 * os processos que possuem linhas em `processo_prioridades`. Violência doméstica (MP)
 * não é removida automaticamente.
 *
 * Agendar semanalmente (cron), por exemplo:
 *   0 3 * * 0 php /caminho/scripts/reconciliar-prioridades-processuais.php
 */

$root = dirname(__DIR__);
require_once $root . '/app/helpers/Conection.php';
require_once $root . '/app/services/ProcessoPrioridadesService.php';

try {
    $pdo = Conection::getInstance()->getConnection();
    $n = (new ProcessoPrioridadesService($pdo))->reconciliarTodosProcessosComPrioridade();
    fwrite(STDOUT, 'OK processos reavaliados: ' . $n . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
