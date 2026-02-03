<?php
class Conexao {
    private static $instance = null;

    public static function getConn() {
        if (self::$instance === null) {
            // Carrega as variáveis do .env (você precisará de uma função para ler o .env)
            $host = getenv('DB_HOST');
            $name = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');

            try {
                self::$instance = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Em produção, grave em log e não exiba o erro na tela
                die(json_encode(["status" => "error", "message" => "Erro de conexão."]));
            }
        }
        return self::$instance;
    }
}
