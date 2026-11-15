<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/config/config.php';

if (envValue('APP_ENV') !== 'testing') {
    throw new RuntimeException('A fixture de atas só pode ser executada em APP_ENV=testing.');
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = file_get_contents(__DIR__ . '/fixtures/ata-schema.sql');
if ($schema === false) {
    throw new RuntimeException('Schema sintético de atas não encontrado.');
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $pdo->exec($statement);
}

$engine = (string) $pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sala_audiencias_ata'")->fetchColumn();
if (strcasecmp($engine, 'InnoDB') !== 0) {
    throw new RuntimeException('A fixture de atas deve usar InnoDB.');
}

$indexes = $pdo->query("SHOW INDEX FROM sala_audiencias_ata WHERE Key_name = 'uk_sala_audiencias_ata_pauta'")->fetchAll(PDO::FETCH_ASSOC);
if (count($indexes) !== 1 || (int) ($indexes[0]['Non_unique'] ?? 1) !== 0) {
    throw new RuntimeException('A fixture de atas deve manter unicidade por pauta.');
}

echo "PASS ata-fixture-setup\n";
