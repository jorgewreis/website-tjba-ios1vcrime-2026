<?php
declare(strict_types=1);

if (!isset($db) || !$db instanceof PDO) {
    require_once __DIR__ . '/../app/helpers/Conection.php';
    $db = Conection::getInstance()->getConnection();
}

// Bloqueia a fotografia da sequência durante a seleção e a exclusão, inclusive
// contra reaberturas simultâneas. A execução é diária e fora do horário comercial.
$db->beginTransaction();
try {
    $locked = $db->query('SELECT id FROM cartorio_tarefas ORDER BY id FOR UPDATE');
    while ($locked->fetchColumn() !== false) { /* apenas adquirir os bloqueios */ }
    $db->exec('DROP TEMPORARY TABLE IF EXISTS tarefas_retencao_elegiveis');
    $db->exec("CREATE TEMPORARY TABLE tarefas_retencao_elegiveis AS
        SELECT COALESCE(sequencia_id, id) AS raiz
        FROM cartorio_tarefas
        GROUP BY COALESCE(sequencia_id, id)
        HAVING SUM(CASE WHEN status <> 'finalizada' OR finalizado_em IS NULL THEN 1 ELSE 0 END) = 0
           AND MAX(finalizado_em) <= DATE_SUB(NOW(), INTERVAL 1 YEAR)");
    $db->exec('DELETE f FROM cartorio_tarefas_automacao_falhas f JOIN cartorio_tarefas t ON t.id = f.tarefa_id JOIN tarefas_retencao_elegiveis e ON e.raiz = COALESCE(t.sequencia_id, t.id)');
    $db->exec('DELETE ev FROM cartorio_tarefas_automacao_eventos ev JOIN cartorio_tarefas t ON t.id = ev.tarefa_id JOIN tarefas_retencao_elegiveis e ON e.raiz = COALESCE(t.sequencia_id, t.id)');
    $db->exec('DELETE c FROM cartorio_tarefas_automacao_criacoes c JOIN cartorio_tarefas t ON t.id = c.tarefa_origem_id JOIN tarefas_retencao_elegiveis e ON e.raiz = COALESCE(t.sequencia_id, t.id)');
    $deleted = $db->exec('DELETE t FROM cartorio_tarefas t JOIN tarefas_retencao_elegiveis e ON e.raiz = COALESCE(t.sequencia_id, t.id)');
    $db->commit();
    fwrite(STDOUT, "Tarefas removidas um ano após a última conclusão da sequência: " . (int) $deleted . "\n");
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
