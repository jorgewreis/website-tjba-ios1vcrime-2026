<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';

require dirname(__DIR__, 2) . '/config/config.php';

function ataConcurrencyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root = __DIR__ . DIRECTORY_SEPARATOR . '.runtime-storage-concurrency';
$dir = $root . DIRECTORY_SEPARATOR . 'sala-audiencias-atas';
if (is_dir($root)) {
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        unlink($file);
    }
} elseif (!mkdir($dir, 0700, true) && !is_dir($dir)) {
    throw new RuntimeException('Não foi possível criar o armazenamento concorrente isolado.');
}

$pdo->exec('DELETE FROM sala_audiencias_ata');
$pdo->exec('DELETE FROM sala_audiencias_pauta');
$pdo->prepare('INSERT INTO sala_audiencias_pauta (id_pauta, processo, data_audiencia, status, ativo) VALUES (?, NULL, CURDATE(), ?, 1)')
    ->execute(['PAT-ATA-0000000000000000000001', 'realizada']);
$oldRelative = 'sala-audiencias-atas/ata_antiga_concorrente.pdf';
$oldFull = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldRelative);
file_put_contents($oldFull, "%PDF-1.4\nold ata\n");
$pdo->prepare('INSERT INTO sala_audiencias_ata (id_ata, pauta, arquivo_relativo, nome_original, mime, tamanho_bytes, uploaded_at, uploaded_by, expires_at, identificador_arquivo) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, DATE_ADD(NOW(), INTERVAL 30 DAY), ?)')
    ->execute(['SAT-ATA-0000000000000000000001', 'PAT-ATA-0000000000000000000001', $oldRelative, 'ata-antiga.pdf', 'application/pdf', filesize($oldFull), 'USR-ATA-0000000000000000000001', 'ATA-OLD']);

$startBarrier = __DIR__ . DIRECTORY_SEPARATOR . '.ata-replacement-start.runtime';
$continueBarrier = __DIR__ . DIRECTORY_SEPARATOR . '.ata-replacement-continue.runtime';
@unlink($startBarrier);
@unlink($continueBarrier);
$worker = __DIR__ . DIRECTORY_SEPARATOR . 'ata-replacement.worker.php';
$processes = [];
foreach (['primeira', 'segunda'] as $name) {
    $source = $root . DIRECTORY_SEPARATOR . $name . '.pdf';
    $ready = $root . DIRECTORY_SEPARATOR . $name . '.ready';
    @unlink($ready);
    file_put_contents($source, "%PDF-1.4\n" . $name . "\n");
    $pipes = [];
    $process = proc_open([PHP_BINARY, $worker, $source, $root, $startBarrier, $ready, $continueBarrier, $name], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    ataConcurrencyAssert(is_resource($process), 'Não foi possível iniciar worker concorrente de ata.');
    $processes[] = ['process' => $process, 'pipes' => $pipes];
}

file_put_contents($startBarrier, 'release');
$deadline = microtime(true) + 10;
while (!is_file($root . DIRECTORY_SEPARATOR . 'primeira.ready') || !is_file($root . DIRECTORY_SEPARATOR . 'segunda.ready')) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Workers não alcançaram a barreira após mover os PDFs.');
    }
    usleep(1000);
}
file_put_contents($continueBarrier, 'release');
foreach ($processes as $entry) {
    $stdout = stream_get_contents($entry['pipes'][1]);
    $stderr = stream_get_contents($entry['pipes'][2]);
    fclose($entry['pipes'][1]);
    fclose($entry['pipes'][2]);
    $exitCode = proc_close($entry['process']);
    ataConcurrencyAssert($exitCode === 0, 'Worker concorrente de ata falhou: ' . trim($stderr));
    $result = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
    ataConcurrencyAssert(($result['success'] ?? false) === true, 'Substituição concorrente falhou: ' . (string) ($result['error'] ?? 'sem detalhe'));
}
@unlink($startBarrier);
@unlink($continueBarrier);

$rows = $pdo->query("SELECT * FROM sala_audiencias_ata WHERE pauta = 'PAT-ATA-0000000000000000000001'")->fetchAll(PDO::FETCH_ASSOC);
ataConcurrencyAssert(count($rows) === 1, 'Substituições concorrentes devem manter exatamente uma ata por pauta.');
$relative = (string) $rows[0]['arquivo_relativo'];
$currentFull = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
ataConcurrencyAssert(is_file($currentFull), 'A referência final deve apontar para um PDF existente.');
ataConcurrencyAssert(!is_file($oldFull), 'O PDF anterior deve ser removido somente depois de uma substituição confirmada.');

echo "PASS ata-replacement-concurrency\n";
