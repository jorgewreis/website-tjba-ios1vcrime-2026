<?php
declare(strict_types=1);

require_once __DIR__ . '/AutoloadHelper.php';
require_once __DIR__ . '/Conection.php';

/**
 * Retorna a conexão compartilhada com o banco (PDO).
 * @deprecated Use Conection::getInstance()->getConnection() diretamente
 */
function getDatabaseConnection(): PDO
{
    return Conection::getInstance()->getConnection();
}

/**
 * Inicia variáveis e sessões compartilhadas entre publicações.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
