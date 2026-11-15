<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/config.php';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = (string) $pdo->query('SELECT @@default_storage_engine')->fetchColumn();
if (strtoupper($engine) !== 'INNODB') {
    throw new RuntimeException('O sandbox deve usar InnoDB; engine recebido: ' . $engine);
}

$pdo->exec('CREATE TABLE IF NOT EXISTS auth_test_runtime_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('DROP TABLE auth_test_runtime_probe');

echo "PASS sandbox-connectivity\n";
