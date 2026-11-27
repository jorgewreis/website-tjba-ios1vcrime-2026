<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__);
require_once $root . '/app/helpers/Conection.php';
require_once $root . '/app/services/ProcessoDataJudBackgroundWorker.php';
$db = Conection::getInstance()->getConnection();
$config = require $root . '/public/api/datajud/config.php';
// A fila recorrente mantém o orçamento anterior; o novo limite atende a consulta interativa.
$config['timeout'] = 30;
$limit = isset($argv[1]) && ctype_digit((string) $argv[1]) ? (int) $argv[1] : 20;
$result = (new ProcessoDataJudBackgroundWorker($db, new DataJudService($config)))->run($limit);
echo json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
