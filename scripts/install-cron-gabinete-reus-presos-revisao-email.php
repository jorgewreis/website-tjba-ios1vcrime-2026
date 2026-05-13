<?php
declare(strict_types=1);

/**
 * Instala no crontab do usuario atual: quinta-feira 18:00 (Linux).
 * Preferir este script em vez do .sh quando o Bash acusar "No such file or directory"
 * (geralmente CRLF no shebang) ou quando o .sh nao existir no deploy.
 *
 * Na raiz do repositorio:
 *   php scripts/install-cron-gabinete-reus-presos-revisao-email.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute apenas em linha de comando (php CLI).\n");
    exit(1);
}

$root = dirname(__DIR__);
$config = $root . '/config/config.php';
$sendScript = $root . '/scripts/send-gabinete-reus-presos-revisao-email.php';
$logFile = $root . '/storage/logs/reus-presos-revisao-cron.log';
$marker = '#tjba-reus-presos-revisao-email';

if (!is_file($config)) {
    fwrite(STDERR, "Erro: raiz invalida (nao achei config/config.php em {$root}).\n");
    fwrite(STDERR, "Execute a partir da pasta do app (ex.: cd /home/.../htdocs/apps).\n");
    exit(1);
}
if (!is_file($sendScript)) {
    fwrite(STDERR, "Erro: nao encontrado: {$sendScript}\n");
    exit(1);
}

if (!is_dir($root . '/storage/logs')) {
    if (!mkdir($root . '/storage/logs', 0755, true) && !is_dir($root . '/storage/logs')) {
        fwrite(STDERR, "Erro: nao foi possivel criar storage/logs.\n");
        exit(1);
    }
}

$phpBin = PHP_BINARY;
if ($phpBin === '' || !is_file($phpBin)) {
    fwrite(STDERR, "Erro: PHP_BINARY invalido. Rode com o php da CLI (ex.: /usr/bin/php ...).\n");
    exit(1);
}

$line = sprintf(
    '0 18 * * 4 cd %s && %s %s >> %s 2>&1 %s',
    escapeshellarg($root),
    escapeshellarg($phpBin),
    escapeshellarg($sendScript),
    escapeshellarg($logFile),
    $marker
);

$existing = shell_exec('crontab -l 2>/dev/null');
$existing = is_string($existing) ? $existing : '';

$outLines = [];
foreach (preg_split("/\r\n|\n|\r/", trim($existing)) as $ln) {
    if ($ln === '') {
        continue;
    }
    if (str_contains($ln, $marker)) {
        continue;
    }
    $outLines[] = $ln;
}
$outLines[] = $line;

$tmp = tempnam(sys_get_temp_dir(), 'tjba-cron');
if ($tmp === false) {
    fwrite(STDERR, "Erro: tempnam falhou.\n");
    exit(1);
}

$content = implode("\n", $outLines) . "\n";
if (file_put_contents($tmp, $content) === false) {
    fwrite(STDERR, "Erro: nao foi possivel gravar arquivo temporario.\n");
    exit(1);
}

$ret = 0;
passthru('crontab ' . escapeshellarg($tmp) . ' 2>&1', $ret);
@unlink($tmp);

if ($ret !== 0) {
    fwrite(STDERR, "Erro: crontab retornou codigo {$ret}.\n");
    exit($ret);
}

echo "OK: crontab atualizado (quinta 18:00) -> {$sendScript}\n";
echo "Log: {$logFile}\n";
exit(0);
