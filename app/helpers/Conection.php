<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

final class Conection
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Erro de conexão com o banco: ' . $e->getMessage());
            $msg = 'Erro interno de conexão com o banco de dados.';
            if (PHP_SAPI === 'cli') {
                $msg .= ' ' . $e->getMessage() . ' (host=' . DB_HOST . ':' . DB_PORT . ', db=' . DB_NAME . ')';
            }
            throw new RuntimeException($msg);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    private function __clone(): void {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Não é permitido desserializar esta classe.');
    }
}
