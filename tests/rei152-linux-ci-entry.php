<?php
declare(strict_types=1);

$expected = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4';
$marker = (string) getenv('REI152_CI_MARKER');
if (!is_file('/.dockerenv') || realpath(__DIR__) !== '/repo/tests'
    || getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql'
    || getenv('REI152_CI_REQUIRED') !== '1'
    || getenv('REI152_MYSQL_DSN') !== $expected || getenv('REI152_MYSQL_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)) {
    throw new RuntimeException('Identidade da fixture recusada.');
}
$db = new PDO($expected, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$tokens = $db->query('SELECT token FROM `_sandbox_proof`')->fetchAll(PDO::FETCH_COLUMN);
if ($tables !== ['_sandbox_proof'] || count($tokens) !== 1 || !hash_equals($marker, (string) $tokens[0])) {
    throw new RuntimeException('Marcador ou estado inicial do banco recusado.');
}
$db = null;
require __DIR__ . '/rei152-documentos-mysql.test.php';
require __DIR__ . '/rei152-gate-alternate-mysql.test.php';
require __DIR__ . '/rei152-create-mysql.test.php';
