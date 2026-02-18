<?php
class Conexao
{
    private static ?mysqli $instance = null;

    public static function getConn(): mysqli
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $name = getenv('DB_NAME') ?: 'db';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $port = getenv('DB_PORT') ?: '3306';

            self::$instance = new mysqli($host, $user, $pass, $name, (int) $port);
            self::$instance->set_charset('utf8mb4');

            if (self::$instance->connect_error) {
                error_log('Conexão MySQL falhou: ' . self::$instance->connect_error);
                throw new RuntimeException('Não foi possível conectar ao banco de dados.');
            }
        }

        return self::$instance;
    }
}
