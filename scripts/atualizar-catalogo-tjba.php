<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/app/services/TjbaCatalogo.php';
try {
    echo TjbaCatalogo::atualizar(TjbaCatalogo::caminho()), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Catálogo TJBA: atualização falhou; nenhum registro processual foi alterado.\n");
    exit(1);
}
