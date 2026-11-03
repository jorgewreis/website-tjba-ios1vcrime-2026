<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/services/CartorioTarefasAutomacaoService.php';
try {
    $resultado = (new CartorioTarefasAutomacaoService(Conection::getInstance()->getConnection()))->executarFila();
    fwrite(STDOUT, json_encode($resultado, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
} catch (Throwable $e) {
    error_log('Falha no processamento de automação de tarefas: ' . $e->getMessage());
    exit(1);
}
