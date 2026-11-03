<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../app/services/CartorioTarefasAutomacaoService.php';
$db = isset($db) && $db instanceof PDO ? $db : null;
try {
    if ($db === null) {
        require_once __DIR__ . '/../app/helpers/Conection.php';
        $db = Conection::getInstance()->getConnection();
    }
    $resultado = (new CartorioTarefasAutomacaoService($db))->executarFila();
    if (empty($resultado['ocupado'])) $db->exec('UPDATE cartorio_tarefas_automacao_monitor SET ultimo_sucesso_em = CURRENT_TIMESTAMP, ultima_falha = NULL WHERE id = 1');
    fwrite(STDOUT, json_encode($resultado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
} catch (Throwable $e) {
    if ($db instanceof PDO) {
        try {
            $st = $db->prepare('UPDATE cartorio_tarefas_automacao_monitor SET ultima_falha_em = CURRENT_TIMESTAMP, ultima_falha = ? WHERE id = 1');
            $st->execute([mb_substr($e->getMessage(), 0, 2000)]);
        } catch (Throwable $ignored) { /* O registro será retomado quando o banco estiver disponível. */ }
    }
    error_log('Falha no processamento de automação de tarefas: ' . $e->getMessage());
    exit(1);
}
