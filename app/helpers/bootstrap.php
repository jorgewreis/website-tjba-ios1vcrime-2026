<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/Conexao.php';

/**
 * Retorna a conexão compartilhada com o banco.
 */
function getDatabaseConnection(): mysqli
{
    return Conexao::getConn();
}

/**
 * Inicia variáveis e sessões compartilhadas entre publicações.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
